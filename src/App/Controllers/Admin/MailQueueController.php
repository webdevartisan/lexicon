<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\MailQueueModel;
use App\Models\ScheduledTaskModel;
use App\Services\MailQueueService;
use App\Traits\SandboxesPreviewHtml;
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
    use SandboxesPreviewHtml;

    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageMailQueue';

    /** Statuses a row may be filtered by, used to reject anything else. */
    private const STATUSES = ['pending', 'sending', 'sent', 'failed', 'cancelled'];

    /** Tiers a row may be filtered by, in the order they drain. */
    private const TIERS = ['critical', 'standard', 'bulk'];

    /** Bulk actions this page offers, mapped to the model call that runs them. */
    private const BULK_ACTIONS = ['cancel', 'retry', 'resend', 'restore'];

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
        $filters = $this->filters($this->request->get);
        $status = $filters['status'];
        $search = $filters['search'];
        $tier = $filters['tier'];
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

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
            // What "select all matching" would reach, and what each action
            // within it could actually touch.
            'matchTotal' => (int) ($result['pagination']['total_records'] ?? 0),
            'matchCounts' => $this->model->statusCountsMatching($filters),
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
     * Stop a pending email before the worker claims it.
     *
     * @param  string  $id  Queue row ID
     */
    public function cancel(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($this->model->cancel((int) $id, (int) auth()->user()['id'])) {
            $this->flash('success', 'Email #'.(int) $id.' was cancelled and will not be sent.');
        } else {
            // cancel() only touches pending rows, so a miss means it is
            // already sending, already resolved, or gone.
            $this->flash('error', 'That email is not pending, so there is nothing to cancel.');
        }

        return $this->redirectBack();
    }

    /**
     * Undo a cancel and put the email back in line.
     *
     * @param  string  $id  Queue row ID
     */
    public function restore(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($this->model->restore((int) $id)) {
            $this->flash('success', 'Email #'.(int) $id.' is back in the queue and will go out on the next run.');
        } else {
            // restore() only touches cancelled rows, so a miss means it was
            // never cancelled, already back in line, or gone.
            $this->flash('error', 'That email is not cancelled, so there is nothing to put back.');
        }

        return $this->redirectBack();
    }

    /**
     * Queue a fresh copy of a delivered email.
     *
     * @param  string  $id  Queue row ID
     */
    public function resend(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $newId = $this->model->resend((int) $id);

        if ($newId !== null) {
            $this->flash('success', 'Email #'.(int) $id.' was queued again as #'.$newId.'.');
        } else {
            // resend() only copies sent rows, so a miss means it never went
            // out, or is gone.
            $this->flash('error', 'That email has not been sent, so there is nothing to resend.');
        }

        return $this->redirectBack();
    }

    /**
     * Apply one bulk action to a set of selected rows.
     *
     * Each model call only touches rows in the matching state, so an id for
     * a row the admin's selection no longer applies to is silently skipped
     * rather than failing the whole request.
     */
    public function bulk(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $action = (string) ($this->request->post['bulk_action'] ?? '');

        if (!in_array($action, self::BULK_ACTIONS, true)) {
            $this->flash('error', 'Choose at least one email and a valid action.');

            return $this->redirectBack();
        }

        $verb = [
            'cancel' => 'cancelled',
            'retry' => 'requeued',
            'resend' => 'resent',
            'restore' => 'put back in the queue',
        ][$action];

        // A queue with thousands of rows cannot be worked through twenty-five
        // ids at a time, so the page can hand back the filter it was showing
        // instead of a selection. The filter is re-read and re-validated here;
        // what the browser sends is a request, not an instruction.
        if (($this->request->post['select_scope'] ?? '') === 'filter') {
            $filters = $this->filters($this->request->post);
            $affected = match ($action) {
                'cancel' => $this->model->cancelAllMatching($filters, (int) auth()->user()['id']),
                'retry' => $this->model->retryAllMatching($filters),
                'resend' => $this->model->resendAllMatching($filters),
                'restore' => $this->model->restoreAllMatching($filters),
            };

            if ($affected > 0) {
                $this->flash('success', number_format($affected)." email(s) {$verb}.");
            } else {
                $this->flash('info', 'No emails matching this filter were eligible for that action.');
            }

            return $this->redirectBack();
        }

        $ids = array_filter(array_map('intval', (array) ($this->request->post['mail_ids'] ?? [])));

        if ($ids === []) {
            $this->flash('error', 'Choose at least one email and a valid action.');

            return $this->redirectBack();
        }

        $affected = match ($action) {
            'cancel' => $this->model->cancelMany($ids, (int) auth()->user()['id']),
            'retry' => $this->model->retryMany($ids),
            'resend' => $this->model->resendMany($ids),
            'restore' => $this->model->restoreMany($ids),
        };

        if ($affected > 0) {
            $this->flash('success', "{$affected} of ".count($ids)." selected email(s) {$verb}.");
        } else {
            $this->flash('info', 'None of the selected emails were eligible for that action.');
        }

        return $this->redirectBack();
    }

    /**
     * The status/search/tier filter carried by a request.
     *
     * One reader for both directions: the listing takes it off the query
     * string, a whole-filter bulk action takes it off the form. An
     * unrecognised status or tier is dropped rather than passed through, which
     * on a listing would silently return nothing and on a bulk action would
     * silently widen what it touches.
     *
     * @param  array<string, mixed>  $source  The request's get or post bag
     * @return array{status: string, search: string, tier: string}
     */
    private function filters(array $source): array
    {
        // `?status[]=x` hands over an array, and casting one to string is fatal.
        $read = static function (mixed $value): string {
            return is_scalar($value) ? trim((string) $value) : '';
        };

        $status = $read($source['status'] ?? '');
        $tier = $read($source['tier'] ?? '');

        return [
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
            'search' => $read($source['q'] ?? ''),
            'tier' => in_array($tier, self::TIERS, true) ? $tier : '',
        ];
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

    /**
     * Show one queued email's metadata alongside a sandboxed render of its body.
     *
     * @param  string  $id  Queue row ID
     */
    public function preview(string $id): Response
    {
        $entry = $this->model->find((int) $id);

        if ($entry === null) {
            $this->flash('error', 'That email no longer exists.');

            return $this->redirect('/admin/mail-queue');
        }

        return $this->view('areas/admin/MailQueue/preview.lex.php', [
            'entry' => $entry,
        ]);
    }

    /**
     * Raw HTML body for the preview iframe.
     *
     * Served on its own, outside the admin layout, so the email's own styles
     * cannot collide with the control panel's. The body is whatever a Mailable
     * wrote, so it goes out sandboxed.
     *
     * @param  string  $id  Queue row ID
     */
    public function renderHtml(string $id): Response
    {
        $entry = $this->model->find((int) $id);

        if ($entry === null) {
            return $this->sandboxedHtml('<p>Email not found.</p>', 404);
        }

        return $this->sandboxedHtml((string) ($entry['body_html'] ?? ''));
    }
}
