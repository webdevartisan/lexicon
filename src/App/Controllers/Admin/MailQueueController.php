<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\MailQueueModel;
use App\Models\ScheduledTaskModel;
use App\Services\MailQueueService;
use App\ValueObjects\TableSort;
use Framework\Core\Response;

/**
 * Inspect the outbound mail queue and recover failed sends.
 *
 * Delivery itself belongs to the mail:queue-work cron command; this only
 * reports what the queue holds and lets an admin put a failed row back in
 * line. Nothing here sends mail directly, so a stuck provider cannot be
 * worked around by hammering a button.
 */
class MailQueueController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageMailQueue';

    /** Statuses a row may be filtered by, used to reject anything else. */
    private const STATUSES = ['pending', 'sending', 'sent', 'failed'];

    /** Tiers a row may be filtered by, in the order they drain. */
    private const TIERS = ['critical', 'standard', 'bulk'];

    public function __construct(
        protected Response $response,
        private MailQueueModel $model,
        private MailQueueService $mailQueue,
        private ScheduledTaskModel $tasks,
    ) {}

    /**
     * List the queue with status and recipient filters.
     */
    public function index(): Response
    {
        $status = trim((string) ($this->request->get['status'] ?? ''));
        $search = trim((string) ($this->request->get['q'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        // An unrecognised status would otherwise silently return an empty list
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $tier = trim((string) ($this->request->get['tier'] ?? ''));

        if (!in_array($tier, self::TIERS, true)) {
            $tier = '';
        }

        $sort = TableSort::fromRequest($this->request, [
            'id' => 'id',
            'recipient' => 'to_email',
            'subject' => 'subject',
            'status' => 'status',
            'tier' => 'tier',
            'attempts' => 'attempts',
            'created' => 'created_at',
        ], defaultKey: 'id', defaultDirection: 'desc', tiebreaker: 'id DESC');

        $result = $this->model->findWithFilters($status, $search, $page, 25, $tier, $sort->orderBy());

        return $this->view('areas/admin/MailQueue/index.lex.php', [
            'entries' => $result['data'],
            'pagination' => $result['pagination'],
            'sort' => $sort,
            'counts' => $this->mailQueue->statusCounts(),
            'statusFilter' => $status,
            'searchFilter' => $search,
            'statusOptions' => self::STATUSES,
            'tierFilter' => $tier,
            'tierOptions' => self::TIERS,
            'undrainedTiers' => $this->undrainedTiers(),
            'mailEnabled' => mailer()->enabled(),
        ]);
    }

    /**
     * Tiers holding mail that no active scheduled worker will ever pick up.
     *
     * Mail sitting in a tier with nothing draining it looks exactly like mail
     * being broken, and without this the only clue is a number that quietly
     * stops going down.
     *
     * @return array<int, string> Tier names, empty when everything is covered
     */
    private function undrainedTiers(): array
    {
        $pending = $this->mailQueue->pendingByTier();
        $covered = $this->tasks->activeArgumentValues('mail:queue-work', 'tier');

        $undrained = [];

        foreach (self::TIERS as $tier) {
            if (($pending[$tier] ?? 0) > 0 && !in_array($tier, $covered, true)) {
                $undrained[] = $tier;
            }
        }

        return $undrained;
    }

    /**
     * Requeue one failed email.
     *
     * @param  string  $id  Queue row ID
     */
    public function retry(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($this->model->retry((int) $id)) {
            $this->flash('success', 'Email #'.(int) $id.' is queued again and will go out on the next run.');
        } else {
            // retry() only touches failed rows, so a miss means it was already
            // sent, already pending, or gone.
            $this->flash('error', 'That email is not in a failed state, so there is nothing to retry.');
        }

        return $this->redirectBack();
    }

    /**
     * Requeue every failed email at once.
     */
    public function retryAll(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $requeued = $this->model->retryAllFailed();

        if ($requeued > 0) {
            $this->flash('success', "Requeued {$requeued} failed email(s) for the next run.");
        } else {
            $this->flash('info', 'There are no failed emails to retry.');
        }

        return $this->redirectBack();
    }

    /**
     * Delete delivered rows past the retention window.
     */
    public function prune(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $deleted = $this->model->pruneSent(30);

        $this->flash('success', "Pruned {$deleted} delivered email(s) older than 30 days.");

        return $this->redirectBack();
    }
}
