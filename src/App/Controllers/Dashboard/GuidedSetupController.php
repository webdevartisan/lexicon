<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\GuidedSetupMarkModel;
use App\Services\GuidedSetupService;
use Framework\Core\Response;

/**
 * The "Your next steps" card's buttons: hide, show, finish and copy link.
 *
 * The card's script posts with fetch and gets the new progress back as JSON.
 * Without JavaScript the same forms post normally and land back on the Overview.
 */
final class GuidedSetupController extends AppController
{
    public function __construct(
        private GuidedSetupService $setup,
        private GuidedSetupMarkModel $marks,
    ) {}

    /**
     * @param  string  $blogId  Blog ID
     */
    public function hide(string $blogId): Response
    {
        return $this->apply((int) $blogId, fn (int $userId, int $id) => $this->marks->mark($userId, $id, 'hidden'));
    }

    /**
     * @param  string  $blogId  Blog ID
     */
    public function show(string $blogId): Response
    {
        return $this->apply((int) $blogId, fn (int $userId, int $id) => $this->marks->unmark($userId, $id, 'hidden'));
    }

    /**
     * Only a finished card can be put away for good.
     *
     * @param  string  $blogId  Blog ID
     */
    public function dismiss(string $blogId): Response
    {
        return $this->apply((int) $blogId, function (int $userId, int $id, array $setup): ?Response {
            if ($setup['state'] !== 'finished') {
                return $this->respond(['error' => 'not_finished'] + $this->progress($setup), 422);
            }

            $this->marks->mark($userId, $id, 'dismissed');

            return null;
        });
    }

    /**
     * @param  string  $blogId  Blog ID
     */
    public function copied(string $blogId): Response
    {
        return $this->apply((int) $blogId, fn (int $userId, int $id) => $this->marks->mark($userId, $id, 'link_copied'));
    }

    /**
     * Check access, run the change, and answer with the progress after it.
     *
     * @param  callable(int, int, array<string, mixed>): (Response|null)  $change
     */
    private function apply(int $blogId, callable $change): Response
    {
        $userId = (int) auth()->user()['id'];
        $setup = $this->setup->forUser($userId, $blogId);
        if ($setup === null) {
            return $this->respond(['error' => 'forbidden'], 403);
        }

        $refused = $change($userId, $blogId, $setup);
        if ($refused instanceof Response) {
            return $refused;
        }

        return $this->respond($this->progress($this->setup->forUser($userId, $blogId)));
    }

    /**
     * @param  array<string, mixed>  $setup
     * @return array{state: string, done: int, total: int}
     */
    private function progress(array $setup): array
    {
        return ['state' => $setup['state'], 'done' => $setup['done'], 'total' => $setup['total']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(array $data, int $status = 200): Response
    {
        if ($this->request->isAjax()) {
            return $this->json($data, $status);
        }

        if ($status === 403) {
            return $this->forbidden();
        }

        return $this->redirect('/dashboard');
    }
}
