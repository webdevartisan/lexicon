<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\PageModel;
use App\Models\SettingModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Public static pages: about, legal texts, contact and the guides.
 *
 * Content comes from the admin-managed pages table; the routes stay
 * explicit so only intended slugs are reachable.
 */
class PageController extends AppController
{
    /**
     * Guides listed on the getting started index, in display order.
     */
    private const GUIDE_SLUGS = [
        'start-your-first-blog',
        'write-posts-people-read',
        'blog-with-your-team',
    ];

    public function __construct(
        private PageModel $pages,
        private SettingModel $settings
    ) {}

    /**
     * Render a static page by slug (route-baked, never user input).
     */
    public function show(string $slug): Response
    {
        return $this->view('public.Page.show', [
            'page' => $this->findPageOrFail($slug),
        ]);
    }

    /**
     * Getting started index: the guide collection for newcomers.
     */
    public function gettingStarted(): Response
    {
        return $this->view('public.Page.getting-started', [
            'guides' => $this->pages->findManyPublished(self::GUIDE_SLUGS, locale()),
        ]);
    }

    /**
     * A single guide. Slugs outside the guide list 404 so this route never
     * becomes a side door to other pages.
     */
    public function guide(string $slug): Response
    {
        if (!in_array($slug, self::GUIDE_SLUGS, true)) {
            throw new PageNotFoundException('Guide not found.', 404);
        }

        return $this->view('public.Page.show', [
            'page' => $this->findPageOrFail($slug),
            'backToGuides' => true,
        ]);
    }

    /**
     * Contact page: editable intro text plus the message form.
     */
    public function contact(): Response
    {
        return $this->view('public.Page.contact', [
            'page' => $this->findPageOrFail('contact'),
        ]);
    }

    /**
     * Handle the contact form submission.
     *
     * Rate limited per IP and protected by a honeypot field, because a
     * public mail-sending endpoint is a spam magnet otherwise.
     */
    public function sendContact(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Bots fill every field; humans never see this one. Pretend success
        // so the bot learns nothing.
        if (trim((string) $this->request->postParam('website')) !== '') {
            $this->flash('success', 'Thanks for your message. We will get back to you soon.');

            return $this->redirect('/contact');
        }

        $ip = $this->request->ip();
        if (rateLimiter()->tooManyAttempts("contact:ip:{$ip}", 5, 3600)) {
            $this->flash('error', 'Too many messages. Please try again later.');

            return $this->redirect('/contact');
        }

        $validator = $this->validateOrFail([
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'subject' => 'required|min:3|max:150',
            'message' => 'required|min:10|max:5000',
        ]);

        $data = $validator->validated();

        rateLimiter()->hit("contact:ip:{$ip}", 3600);

        $adminEmail = $this->settings->get('admin_email', (string) env('MAIL_FROM_ADDRESS', ''));

        $sent = $adminEmail !== '' && mailer()->send(new ContactMessageMail(
            $adminEmail,
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message']
        ));

        if (!$sent) {
            error_log('Contact form delivery failed (admin email: '.($adminEmail ?: 'unset').')');
            $this->flash('error', 'Sorry, your message could not be sent right now. Please try again later.');

            return $this->redirect('/contact');
        }

        $this->flash('success', 'Thanks for your message. We will get back to you soon.');

        return $this->redirect('/contact');
    }

    /**
     * Load a published page for the visitor's locale or fail with a 404.
     *
     * @return array<string, mixed> The page row
     */
    private function findPageOrFail(string $slug): array
    {
        $page = $this->pages->findPublished($slug, locale());

        if ($page === null) {
            throw new PageNotFoundException('Page not found.', 404);
        }

        return $page;
    }
}
