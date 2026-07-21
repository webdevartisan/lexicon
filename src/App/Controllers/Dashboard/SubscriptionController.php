<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\BlogSubscriberModel;
use Framework\Core\Response;

/**
 * The reader's subscriptions: every blog they follow, in one place.
 *
 * Unsubscribing here is the account-side counterpart of the token links in
 * notification emails; rows are matched by user id or email so pre-account
 * subscriptions stay manageable too.
 */
class SubscriptionController extends AppController
{
    public function __construct(
        private BlogSubscriberModel $subscribers,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        return $this->view('subscription.index', [
            'subscriptions' => $this->subscribers->forUser((int) $user['id'], (string) ($user['email'] ?? '')),
        ]);
    }

    public function unsubscribe(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();

        $removed = $this->subscribers->deleteByIdForUser(
            (int) $id,
            (int) $user['id'],
            (string) ($user['email'] ?? '')
        );

        if ($removed) {
            $this->flash('success', 'Unsubscribed. You will no longer get emails from this blog.');
        } else {
            $this->flash('error', 'That subscription could not be found.');
        }

        return $this->redirect('/library/subscriptions');
    }
}
