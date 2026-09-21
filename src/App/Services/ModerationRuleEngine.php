<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentReportModel;
use App\Models\ModerationCaseModel;
use App\Models\ModerationCategoryModel;
use App\Models\UserModel;
use Framework\Database;

/**
 * Decides what the system does once reports on a case reach a category's
 * threshold, and does it only when every safeguard agrees.
 *
 * A raw "N reports means action" rule is exactly what a coordinated group of
 * reporters would exploit, so a triggered rule acts alone only for a category
 * set to automatic, below critical severity, with high severity explicitly
 * accepted by an administrator, from reports spread over time, against an
 * account that is not staff. Anything else becomes a proposal a person
 * confirms, and the reason it was held is recorded beside it.
 *
 * Each category's rule fires at most once per case.
 */
class ModerationRuleEngine
{
    /** Why a triggered rule waited for a person, in words a moderator reads on the case. */
    public const HOLD_NOTES = [
        'critical' => 'Critical categories always wait for a person.',
        'confirmation_required' => 'This category is set to wait for a person to confirm.',
        'automation_not_acknowledged' => 'High severity category that an administrator has not approved for automation.',
        'permanent_suspension' => 'A permanent suspension is never applied automatically.',
        'report_burst' => 'The reports arrived close together, which can mean a coordinated campaign.',
        'protected_account' => 'The author holds a staff role, so no rule acts against them on its own.',
        'no_account' => 'There is no account behind this item to suspend.',
        'suspension_cooldown' => 'A rule already suspended this author recently.',
    ];

    public function __construct(
        private Database $database,
        private ModerationCaseModel $cases,
        private ModerationCategoryModel $categories,
        private ContentReportModel $reports,
        private ModerationSettings $settings,
        private ModerationActionService $actions,
        private UserSuspensionService $suspensions,
        private UserModel $users,
    ) {}

    /**
     * Fire every rule the case has newly reached, most severe first.
     *
     * An automatic action that fails is not rethrown: the report that caused
     * it is already saved, and the failure is written onto the case, into the
     * audit log and the error log, where the queue shows it for a person.
     *
     * @return list<array{rule: string, action: string, outcome: string, reason: string|null}>
     */
    public function evaluate(int $caseId): array
    {
        $case = $this->cases->findById($caseId);

        if ($case === null || $case['status'] === 'resolved') {
            return [];
        }

        $outcomes = [];

        foreach ($this->triggeredRules($case) as $trigger) {
            $outcomes[] = $this->fire($caseId, $trigger);
        }

        if ($outcomes !== []) {
            $this->cases->recount($caseId);
        }

        return $outcomes;
    }

    /**
     * Rules whose threshold the counted reports have reached and that have
     * not fired on this case yet, most severe first.
     *
     * @param  array<string, mixed>  $case
     * @return list<array{category: array<string, mixed>, counted: int, span_seconds: int}>
     */
    private function triggeredRules(array $case): array
    {
        $fired = $this->cases->firedRules($case);
        $triggered = [];

        foreach ($this->reports->countedByCategory((int) $case['id']) as $tally) {
            $category = $this->categories->findBySlug($tally['category']);

            if ($category === null
                || $category['auto_action'] === 'none'
                || $category['threshold'] === null
                || in_array($category['slug'], $fired, true)
                || $tally['counted'] < (int) $category['threshold']) {
                continue;
            }

            $triggered[] = [
                'category' => $category,
                'counted' => $tally['counted'],
                'span_seconds' => $tally['span_seconds'],
            ];
        }

        usort($triggered, static fn (array $a, array $b): int => ModerationCategoryModel::severityRank($b['category']['severity'])
            <=> ModerationCategoryModel::severityRank($a['category']['severity']));

        return $triggered;
    }

    /**
     * @param  array{category: array<string, mixed>, counted: int, span_seconds: int}  $trigger
     * @return array{rule: string, action: string, outcome: string, reason: string|null}
     */
    private function fire(int $caseId, array $trigger): array
    {
        // Re-read: a rule fired earlier in this pass may have changed the case.
        $case = $this->cases->findById($caseId) ?? throw new \RuntimeException("Moderation case {$caseId} vanished.");
        $category = $trigger['category'];
        $rule = (string) $category['slug'];
        $action = (string) $category['auto_action'];
        $facts = [
            'action' => $action,
            'severity' => $category['severity'],
            'execution' => $category['execution'],
            'threshold' => (int) $category['threshold'],
            'counted' => $trigger['counted'],
        ];

        if ($action === 'escalate') {
            $this->atomically(function () use ($case, $caseId, $rule, $facts): void {
                $this->cases->markFired($caseId, $rule);
                $this->cases->escalate($caseId);
                $this->actions->record($case, 'moderation.rule_escalated', null, $rule, $facts);
            });

            return $this->outcome($rule, $action, 'escalated', null);
        }

        $skip = $this->alreadyDone($case, $action);

        if ($skip !== null) {
            $this->atomically(function () use ($case, $caseId, $rule, $facts, $skip): void {
                $this->cases->markFired($caseId, $rule);
                $this->actions->record($case, 'moderation.rule_skipped', null, $rule, $facts + ['skipped' => $skip]);
            });

            return $this->outcome($rule, $action, 'skipped', $skip);
        }

        $hold = $this->holdReason($case, $category, $trigger);

        if ($hold !== null) {
            $this->hold($case, $category, $hold, $facts);

            return $this->outcome($rule, $action, 'held', $hold);
        }

        try {
            $this->apply($case, $category, $trigger['counted']);
        } catch (\Throwable $e) {
            error_log("Moderation rule '{$rule}' could not {$action} on case {$caseId}: {$e->getMessage()}");

            $this->atomically(function () use ($case, $caseId, $rule, $action, $facts, $e): void {
                $this->cases->markFired($caseId, $rule);
                $this->cases->recordFailure($caseId, $action, $rule, $e->getMessage());
                $this->actions->record($case, 'moderation.action_failed', null, $rule, $facts + ['error' => $e->getMessage()]);
            });

            return $this->outcome($rule, $action, 'failed', $e->getMessage());
        }

        $this->cases->markFired($caseId, $rule);

        return $this->outcome($rule, $action, 'applied', null);
    }

    /**
     * Why the action should not happen at all, because it already has.
     *
     * @param  array<string, mixed>  $case
     */
    private function alreadyDone(array $case, string $action): ?string
    {
        if ($action === 'hide_content' && $case['content_status'] !== 'visible') {
            return 'already_hidden';
        }

        if ($action === 'suspend'
            && $case['subject_author_id'] !== null
            && $this->suspensions->current((int) $case['subject_author_id']) !== null) {
            return 'already_suspended';
        }

        return null;
    }

    /**
     * The first safeguard that stops this rule acting on its own, or null.
     *
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $category
     * @param  array{counted: int, span_seconds: int}  $trigger
     */
    private function holdReason(array $case, array $category, array $trigger): ?string
    {
        $authorId = $case['subject_author_id'] === null ? null : (int) $case['subject_author_id'];
        $suspends = $category['auto_action'] === 'suspend';

        // Checked here as well as when the settings are saved, so a row edited
        // by hand still cannot turn off the human check.
        return match (true) {
            $category['severity'] === 'critical' => 'critical',
            $category['execution'] !== 'automatic' => 'confirmation_required',
            $category['severity'] === 'high' && $category['automation_acknowledged_at'] === null => 'automation_not_acknowledged',
            $suspends && empty($category['suspension_hours']) => 'permanent_suspension',
            $trigger['span_seconds'] < $this->settings->burstWindowMinutes() * 60 => 'report_burst',
            $authorId !== null && $this->isStaff($authorId) => 'protected_account',
            $suspends && $authorId === null => 'no_account',
            $suspends && $this->suspensions->suspendedByRuleWithin($authorId, $this->settings->autoSuspensionCooldownDays()) => 'suspension_cooldown',
            default => null,
        };
    }

    /**
     * Turn the rule into a proposal. Only one proposal waits on a case at a
     * time, so a weaker one does not displace a stronger one already there.
     *
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $category
     * @param  array<string, mixed>  $facts
     */
    private function hold(array $case, array $category, string $hold, array $facts): void
    {
        $caseId = (int) $case['id'];
        $rule = (string) $category['slug'];
        $waiting = $case['pending_rule'] === null ? null : $this->categories->findBySlug((string) $case['pending_rule']);
        $takesSlot = $waiting === null
            || ModerationCategoryModel::severityRank($category['severity']) > ModerationCategoryModel::severityRank($waiting['severity']);

        $this->atomically(function () use ($case, $caseId, $rule, $category, $hold, $facts, $takesSlot, $waiting): void {
            $this->cases->markFired($caseId, $rule);

            if ($takesSlot) {
                $this->cases->propose($caseId, (string) $category['auto_action'], $rule, self::HOLD_NOTES[$hold]);
            } else {
                $this->cases->escalate($caseId);
            }

            $this->actions->record($case, 'moderation.rule_proposed', null, $rule, $facts + [
                'held_because' => $hold,
                'behind' => $takesSlot ? null : $waiting['slug'],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $category
     */
    private function apply(array $case, array $category, int $counted): void
    {
        $rule = (string) $category['slug'];
        $reason = sprintf(
            '%s: %d reports from readers in good standing reached the threshold of %d.',
            $category['label'],
            $counted,
            (int) $category['threshold']
        );

        match ($category['auto_action']) {
            'hide_content' => $this->actions->hideContent($case, null, $reason, $rule),
            'suspend' => $this->actions->suspendAuthor(
                $case,
                ModerationActionService::endsInHours((int) $category['suspension_hours']),
                null,
                $reason,
                $rule
            ),
            default => throw new \RuntimeException("Unknown automatic action '{$category['auto_action']}'."),
        };
    }

    /**
     * Staff are anyone holding a system role beyond the default reader one.
     */
    private function isStaff(int $userId): bool
    {
        return array_diff($this->users->getUserRoles($userId), ['reader']) !== [];
    }

    /**
     * @param  callable(): void  $work
     */
    private function atomically(callable $work): void
    {
        $this->database->inTransaction() ? $work() : $this->database->transaction($work);
    }

    /**
     * @return array{rule: string, action: string, outcome: string, reason: string|null}
     */
    private function outcome(string $rule, string $action, string $outcome, ?string $reason): array
    {
        return ['rule' => $rule, 'action' => $action, 'outcome' => $outcome, 'reason' => $reason];
    }
}
