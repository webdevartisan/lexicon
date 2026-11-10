<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Mail\ContactMessageMail;
use App\Mail\Mailable;
use App\Models\PageModel;
use App\Models\SettingModel;
use App\Services\RecipientLocale;
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
    private const CONTACT_SENT = 'contact_sent';

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
            'guides' => $this->pages->findManyPublished(PageModel::GUIDE_SLUGS, locale()),
        ]);
    }

    /**
     * A single guide. Slugs outside the guide list 404 so this route never
     * becomes a side door to other pages.
     */
    public function guide(string $slug): Response
    {
        if (!in_array($slug, PageModel::GUIDE_SLUGS, true)) {
            throw new PageNotFoundException('Guide not found.', 404);
        }

        return $this->view('public.Page.show', [
            'page' => $this->findPageOrFail($slug),
            'backToGuides' => true,
        ]);
    }

    /**
     * Contact page: editable intro text plus the message form, or the
     * confirmation once a message has gone.
     */
    public function contact(): Response
    {
        $sent = (bool) $this->session->pull(self::CONTACT_SENT);

        $response = $this->view('public.Page.contact', [
            'page' => $this->findPageOrFail('contact'),
            'sent' => $sent,
            'contactEmail' => (string) site_content('contact.email', ''),
        ]);

        // The confirmation has no form, so the page cache would otherwise keep it and show it to every visitor.
        if ($sent) {
            $response->addHeader('Cache-Control', 'private, no-store');
        }

        return $response;
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
            return $this->contactSent();
        }

        $ip = $this->request->ip();
        if (rateLimiter()->tooManyAttempts("contact:ip:{$ip}", 5, 3600)) {
            $this->flash('error', chrome_translate('pages.contactForm.tooMany'));

            return $this->redirect(lurl('/contact'));
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

        // Queued, so a provider refusing right now no longer loses the message.
        // enqueue() returns how many rows it wrote, one per recipient.
        // Written for the admin who reads it, not the visitor who sent it.
        $sent = $adminEmail !== '' && mail_queue()->enqueue(Mailable::inLocale(
            app(RecipientLocale::class)->forAddress($adminEmail),
            fn (): ContactMessageMail => new ContactMessageMail(
                $adminEmail,
                $data['name'],
                $data['email'],
                $data['subject'],
                $data['message']
            )
        )) > 0;

        if (!$sent) {
            error_log('Contact form could not be queued (admin email: '.($adminEmail ?: 'unset').')');
            $this->flash('error', chrome_translate('pages.contactForm.notSent'));

            return $this->redirect(lurl('/contact'));
        }

        return $this->contactSent();
    }

    /**
     * Send the visitor back to the contact page, which then shows the confirmation once.
     */
    private function contactSent(): Response
    {
        $this->session->set(self::CONTACT_SENT, true);

        return $this->redirect(lurl('/contact'));
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
