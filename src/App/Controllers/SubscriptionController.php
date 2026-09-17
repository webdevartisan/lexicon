<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Mail\SubscriptionConfirmMail;
use App\Models\BlogModel;
use App\Models\BlogSubscriberModel;
use Framework\Core\Response;

/**
 * Public blog subscriptions: subscribe by email, confirm and unsubscribe by token.
 */
class SubscriptionController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private BlogSubscriberModel $subscriberModel,
    ) {}

    public function subscribe(string $blogSlug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Guests can subscribe, so throttle by IP to keep list stuffing in check
        $throttleKey = 'subscribe:'.($this->request->ip() ?? 'unknown');

        if (rateLimiter()->tooManyAttempts($throttleKey, 10)) {
            $this->flash('error', 'Too many attempts. Please try again later.');

            return $this->redirectBack();
        }

        rateLimiter()->hit($throttleKey);

        $email = strtolower(trim((string) ($this->request->post['email'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $this->flash('error', 'Please enter a valid email address.');

            return $this->redirectBack();
        }

        $blog = $this->blogModel->getBlogBySlug($blogSlug);

        if (!$blog || ($blog['status'] ?? '') !== 'published') {
            $this->flash('error', 'Blog not found.');

            return $this->redirect('/');
        }

        $user = auth()->user();
        $ownAddress = $user !== null && strcasecmp((string) $user['email'], $email) === 0;

        $subscription = $this->subscriberModel->subscribe(
            (int) $blog['id'],
            $email,
            $user ? (int) $user['id'] : null,
            $ownAddress
        );

        audit()->log(
            $user ? (int) $user['id'] : 0,
            'blog.subscribed',
            'blog',
            (int) $blog['id'],
            ['is_guest' => $user === null],
            $this->request->ip()
        );

        if ($subscription['confirmed_at'] === null) {
            mail_queue()->enqueue(
                new SubscriptionConfirmMail($email, (string) $blog['blog_name'], $subscription['token']),
                'blog',
                (int) $blog['id']
            );
            $this->flash('success', 'Almost done. Follow the link we sent to your inbox to confirm the subscription.');

            return $this->redirectBack();
        }

        $this->flash('success', 'You are subscribed. New posts will land in your inbox.');

        return $this->redirectBack();
    }

    /**
     * The emailed link only shows a button. Mail scanners open links on their own,
     * and a click they make is not the subscriber agreeing.
     */
    public function showConfirm(string $token): Response
    {
        $subscriber = $this->subscriberModel->findByToken($token);
        $blog = $subscriber ? $this->blogModel->find((int) $subscriber['blog_id']) : null;

        if ($subscriber === null || $blog === null) {
            return $this->view('subscription.invalid')->setStatusCode(404);
        }

        return $this->view('subscription.confirm', [
            'token' => $token,
            'email' => (string) $subscriber['email'],
            'blogName' => (string) $blog['blog_name'],
        ]);
    }

    public function confirm(string $token): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $subscriber = $this->subscriberModel->confirmByToken($token);
        $blog = $subscriber ? $this->blogModel->find((int) $subscriber['blog_id']) : null;

        if ($subscriber === null || $blog === null) {
            return $this->view('subscription.invalid')->setStatusCode(404);
        }

        $this->flash('success', 'Subscription confirmed. New posts will land in your inbox.');

        return $this->redirect(lurl('/blog/'.rawurlencode((string) $blog['blog_slug'])));
    }

    public function unsubscribe(string $token): Response
    {
        $subscriber = $this->subscriberModel->findByToken($token);

        if (!$subscriber) {
            $this->flash('error', 'That unsubscribe link is no longer valid.');

            return $this->redirect('/');
        }

        $this->subscriberModel->deleteByToken($token);

        $this->flash('success', 'You have been unsubscribed.');

        return $this->redirect('/');
    }
}
