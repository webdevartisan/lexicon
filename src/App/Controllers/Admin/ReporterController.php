<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\ContentReportModel;
use App\Models\ModerationCategoryModel;
use App\Models\UserModel;
use App\Services\AccountErasureService;
use App\Services\ReporterStandingService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use RuntimeException;

/**
 * One person's record as a reporter, and the warning and pause a moderator
 * can apply when their reports keep turning out unfounded.
 */
class ReporterController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'handleReports';

    public function __construct(
        private UserModel $users,
        private ContentReportModel $reports,
        private ReporterStandingService $standing,
        private AccountErasureService $erasure,
        private ModerationCategoryModel $categories,
    ) {}

    public function show(string $id): Response
    {
        $user = $this->findReporter($id);
        $history = $this->standing->history((int) $user['id']);

        return $this->view('report.reporter', [
            'reporter' => $user,
            'summary' => $this->reports->reporterSummary((int) $user['id']),
            'filed' => $this->reports->reporterHistory((int) $user['id']),
            'history' => $history,
            'warned' => in_array(ReporterStandingService::WARNED, array_column($history, 'action'), true),
            'pausedUntil' => ReporterStandingService::pausedUntil($user),
            'categoryLabels' => array_column($this->categories->ordered(), 'label', 'slug'),
        ]);
    }

    public function warn(string $id): Response
    {
        $message = trim((string) $this->validateOrFail(['message' => 'required|min:10|max:2000'])->validated()['message']);

        return $this->act($id, 'Warning sent.', function (int $userId) use ($message): void {
            $this->standing->warn($userId, $this->actorId(), $message, $this->request->ip());
        });
    }

    public function pause(string $id): Response
    {
        $valid = $this->validateOrFail([
            'days' => 'required|in:7,30,90,180,365',
            'note' => 'required|min:3|max:1000',
        ])->validated();

        return $this->act($id, null, function (int $userId) use ($valid): string {
            $until = $this->standing->pause($userId, $this->actorId(), (int) $valid['days'], trim((string) $valid['note']), $this->request->ip());

            return 'Their reports are paused until '.local_datetime($until, 'M j, Y').'.';
        });
    }

    public function resume(string $id): Response
    {
        $note = trim((string) $this->validateOrFail(['note' => 'required|min:3|max:1000'])->validated()['note']);

        return $this->act($id, 'Their reports are accepted again.', function (int $userId) use ($note): void {
            $this->standing->resume($userId, $this->actorId(), $note, $this->request->ip());
        });
    }

    /**
     * Run an action and report its outcome. The service's refusals are
     * written for the moderator, so they are shown as they are.
     *
     * @param  callable(int): (string|void)  $action
     */
    private function act(string $id, ?string $success, callable $action): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) $this->findReporter($id)['id'];

        try {
            $message = $action($userId);
            $this->flash('success', is_string($message) ? $message : (string) $success);
        } catch (RuntimeException $e) {
            $this->flash('error', 'Nothing was changed. '.$e->getMessage());
        }

        return $this->redirect('/admin/reports/reporters/'.$userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function findReporter(string $id): array
    {
        $user = $this->users->findById((int) $id);

        // The shared account erased people's content moves to reports nothing
        if ($user === null || $this->erasure->isDeletedUserAccount((int) $user['id'])) {
            throw new PageNotFoundException("Reporter '{$id}' not found.");
        }

        return $user;
    }

    private function actorId(): int
    {
        return (int) (auth()->user()['id'] ?? 0);
    }
}
