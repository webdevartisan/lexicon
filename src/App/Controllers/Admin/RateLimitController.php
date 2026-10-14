<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Services\RateLimitAdminService;
use Framework\Core\Response;

/**
 * Shows who the rate limiters have locked out and lets an admin lift it.
 *
 * Thin HTTP layer over RateLimitAdminService.
 */
class RateLimitController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageRateLimits';

    public function __construct(
        protected Response $response,
        private RateLimitAdminService $rateLimits
    ) {}

    /**
     * List current lockouts and offer the unlock form.
     */
    public function index(): Response
    {
        return $this->view('areas/admin/RateLimit/index.lex.php', [
            'blocked' => $this->rateLimits->blocked(),
            'durations' => RateLimitAdminService::DURATIONS,
        ]);
    }

    /**
     * Block an email or IP by hand for a chosen time.
     */
    public function block(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Named apart from the unlock field so the two modals never share an element id
        $subject = trim((string) $this->request->postParam('block_subject'));

        try {
            $this->rateLimits->block($subject, (string) $this->request->postParam('duration'), $this->request->ip());
            $this->flash('success', "Blocked {$subject}.");
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/rate-limits');
    }

    /**
     * Lift every limiter entry held against an email or IP.
     */
    public function unlock(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $subject = trim((string) $this->request->postParam('subject'));

        try {
            $removed = $this->rateLimits->unlock($subject, $this->request->ip());
            $this->flash('success', $removed > 0
                ? "Unlocked {$subject}."
                : "Nothing was holding {$subject}; it was already clear.");
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/rate-limits');
    }
}
