<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Gate;
use App\Models\ContentReportModel;
use App\Models\ModerationCaseModel;
use App\Models\ModerationCategoryModel;
use App\Models\UserModel;
use App\Presenters\ModerationCasePresenter;
use App\Resources\SystemResource;
use App\Services\ModerationCaseService;
use App\Services\ModerationRuleEngine;
use App\Services\ModerationSettings;
use App\Services\PostContentSanitizer;
use App\Services\UserSuspensionService;
use App\ValueObjects\TableSort;
use DateTimeImmutable;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use RuntimeException;

/**
 * The reports queue and the case page where moderators decide.
 *
 * Every action is authorized server side by the handleReports ability, and
 * suspension additionally by manageUsers on the regular suspend page. What the
 * case page shows or hides is only a convenience on top of that.
 */
class ReportController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'handleReports';

    private const BASE = '/admin/reports';

    private const TABS = ['active', 'open', 'in_review', 'escalated', 'resolved', 'all'];

    public function __construct(
        private ModerationCaseModel $cases,
        private ContentReportModel $reports,
        private ModerationCategoryModel $categories,
        private ModerationCaseService $moderation,
        private UserModel $users,
        private UserSuspensionService $suspensions,
        private PostContentSanitizer $sanitizer,
        private ModerationSettings $settings,
    ) {}

    public function index(): Response
    {
        $filters = $this->queueFilters();
        $sort = TableSort::fromRequest($this->request, [
            'priority' => 'mc.priority',
            'recent' => 'mc.last_reported_at',
            'reports' => 'mc.report_count',
            'opened' => 'mc.created_at',
        ], defaultKey: 'priority', defaultDirection: 'desc', tiebreaker: 'mc.id DESC');

        $page = max(1, (int) ($this->request->get['page'] ?? 1));
        $result = $this->cases->findForQueue($filters['query'], $page, 20, $sort->orderBy());

        return $this->view('report.index', [
            'cases' => array_map(ModerationCasePresenter::present(...), $result['data']),
            'pagination' => $result['pagination'],
            'counts' => $this->cases->statusCounts(),
            'filters' => $filters['input'],
            'unknownHandles' => $filters['unknown'],
            'overLimit' => $this->reports->reportersOverLimit(
                $this->settings->unfoundedLimit(),
                $this->settings->unfoundedWindowDays()
            ),
            'unfoundedWindow' => $this->settings->unfoundedWindowDays(),
            'categories' => $this->categories->ordered(),
            'tabs' => self::TABS,
            'sort' => $sort,
            'basePath' => self::BASE,
            'canViewUsers' => Gate::allows('manageUsers', SystemResource::class, auth()->user() ?? []),
            'canConfigure' => Gate::allows('configureModeration', SystemResource::class, auth()->user() ?? []),
        ]);
    }

    public function show(string $id): Response
    {
        $case = $this->findCase($id);
        $authorId = $case['subject_author_id'] === null ? null : (int) $case['subject_author_id'];
        $author = $authorId === null ? null : $this->users->findById($authorId);

        return $this->view('report.show', [
            'case' => ModerationCasePresenter::present($case),
            'reports' => $this->reports->forCase((int) $case['id']),
            'timeline' => $this->cases->timeline((int) $case['id']),
            'categoryLabels' => array_column($this->categories->ordered(), 'label', 'slug'),
            'author' => $author,
            'authorRecord' => $authorId === null ? null : $this->cases->authorRecord($authorId, (int) $case['id']),
            'authorWarnings' => $authorId === null ? 0 : $this->cases->warningsFor($authorId),
            'authorSuspensions' => $authorId === null ? [] : $this->suspensions->history($authorId),
            'canSuspend' => $this->canSuspend($author),
            'canViewUsers' => Gate::allows('manageUsers', SystemResource::class, auth()->user() ?? []),
            'holdNotes' => ModerationRuleEngine::HOLD_NOTES,
            // Re-sanitized here, not trusted from storage: bodies saved before
            // the sanitizer existed would otherwise run in an administrator's session.
            'postHtml' => $case['post_content'] === null ? null : $this->sanitizer->clean((string) $case['post_content']),
        ]);
    }

    public function review(string $id): Response
    {
        return $this->decide($id, 'Case marked as in review.', function (array $case): void {
            $this->moderation->startReview($case, $this->actorId());
        });
    }

    public function escalate(string $id): Response
    {
        $note = trim((string) $this->validateOrFail(['note' => 'required|min:3|max:1000'])->validated()['note']);

        return $this->decide($id, 'Case escalated.', function (array $case) use ($note): void {
            $this->moderation->escalate($case, $this->actorId(), $note);
        });
    }

    public function dismiss(string $id): Response
    {
        $note = trim((string) $this->validateOrFail(['note' => 'required|min:3|max:1000'])->validated()['note']);
        $unfounded = $this->request->post['unfounded'] ?? [];
        $unfoundedIds = is_array($unfounded) ? array_map('intval', $unfounded) : [];

        return $this->decide($id, null, function (array $case) use ($note, $unfoundedIds): string {
            $restored = $this->moderation->dismiss($case, $this->actorId(), $note, $unfoundedIds);
            $marked = count(array_filter($unfoundedIds));

            return 'Case dismissed.'
                .($restored ? ' The hidden content is visible again.' : '')
                .($marked > 0 ? " {$marked} report(s) marked unfounded." : '');
        });
    }

    public function uphold(string $id): Response
    {
        $hide = (string) ($this->request->post['hide'] ?? '') === '1';
        $warn = (string) ($this->request->post['warn'] ?? '') === '1';
        $rules = ['note' => 'required|min:3|max:1000'] + ($warn ? ['warning' => 'required|min:10|max:2000'] : []);

        $valid = $this->validateOrFail($rules, [
            'warning.required' => 'Write the author a warning, or untick the warning.',
            'warning.min' => 'Write the author a warning of at least 10 characters, or untick the warning.',
        ])->validated();

        $note = trim((string) $valid['note']);
        $warning = trim((string) ($valid['warning'] ?? ''));

        return $this->decide($id, null, function (array $case) use ($note, $hide, $warn, $warning): string {
            $this->moderation->uphold($case, $this->actorId(), $note, $hide, $warn ? $warning : null);

            return 'Case upheld.'
                .($hide && $case['content_status'] === 'visible' ? ' The content is now hidden.' : '')
                .($warn ? ' The author has been warned.' : '');
        });
    }

    /**
     * Run a decision and report its outcome. A refusal from the service is
     * already written for the moderator, so it is shown as it is.
     *
     * @param  callable(array<string, mixed>): (string|void)  $decision
     */
    private function decide(string $id, ?string $success, callable $decision): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $case = $this->findCase($id);

        try {
            $message = $decision($case);
            $this->flash('success', is_string($message) ? $message : (string) $success);
        } catch (RuntimeException $e) {
            $this->flash('error', 'Nothing was changed. '.$e->getMessage());
        }

        return $this->redirect(self::BASE.'/'.(int) $case['id']);
    }

    /**
     * Read and validate the queue filters. A tag that matches no account
     * filters to nothing, and says so, rather than being dropped.
     *
     * @return array{query: array<string, mixed>, input: array<string, string>, unknown: string[]}
     */
    private function queueFilters(): array
    {
        $get = fn (string $key): string => is_scalar($this->request->get[$key] ?? null) ? trim((string) $this->request->get[$key]) : '';

        $input = [
            'status' => in_array($get('status'), self::TABS, true) ? $get('status') : 'active',
            'type' => in_array($get('type'), ['post', 'comment'], true) ? $get('type') : '',
            'category' => $this->categories->findBySlug($get('category')) !== null ? $get('category') : '',
            'from' => $this->validDate($get('from')),
            'to' => $this->validDate($get('to')),
            'author' => ltrim($get('author'), '@'),
            'reporter' => ltrim($get('reporter'), '@'),
        ];

        $query = $input;
        $unknown = [];

        foreach (['author' => 'author_id', 'reporter' => 'reporter_id'] as $field => $column) {
            if ($input[$field] === '') {
                continue;
            }

            $account = $this->users->findByHandle($input[$field])[0] ?? null;
            $query[$column] = $account === null ? 0 : (int) $account['id'];

            if ($account === null) {
                $unknown[] = '@'.$input[$field];
            }
        }

        return ['query' => $query, 'input' => $input, 'unknown' => $unknown];
    }

    private function validDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function findCase(string $id): array
    {
        return $this->cases->findDetail((int) $id)
            ?? throw new PageNotFoundException("Report case '{$id}' not found.");
    }

    /**
     * Whether this moderator may suspend the author, by the same rules the
     * suspend page itself enforces.
     *
     * @param  array<string, mixed>|null  $author
     */
    private function canSuspend(?array $author): bool
    {
        $actor = auth()->user() ?? [];

        if ($author === null || (int) $author['id'] === $this->actorId()
            || !Gate::allows('manageUsers', SystemResource::class, $actor)) {
            return false;
        }

        $isAdministrator = in_array('administrator', $this->users->getUserRoles((int) $author['id']), true);

        return !$isAdministrator || Gate::allows('actOnAdministrators', SystemResource::class, $actor);
    }

    private function actorId(): int
    {
        return (int) (auth()->user()['id'] ?? 0);
    }
}
