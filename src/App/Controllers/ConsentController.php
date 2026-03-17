<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ConsentService;
use Framework\Security\Csrf;

/**
 * Handle cookie consent preferences.
 *
 * Manages user consent for preferences, analytics, and marketing cookies
 * in compliance with GDPR requirements.
 */
final class ConsentController extends AppController
{
    private const VALID_ACTIONS = ['accept_all', 'reject_all', 'save'];

    public function __construct(
        private readonly ConsentService $consent,
        private readonly Csrf $csrf,
    ) {}

    /**
     * Store consent preferences.
     *
     * Accepts three actions: accept_all, reject_all, or save (custom preferences).
     *
     * @return mixed JSON response with saved consent or error
     */
    public function store()
    {
        $this->csrf->assertValid($this->readCsrfToken());

        $action = $_POST['action'] ?? '';
        if (!is_string($action) || !in_array($action, self::VALID_ACTIONS, true)) {
            return $this->json(['ok' => false, 'error' => 'Invalid action'], 422);
        }

        if ($action === 'accept_all') {
            $saved = $this->consent->save([
                'preferences' => true,
                'analytics' => true,
                'marketing' => true,
            ]);
        } elseif ($action === 'reject_all') {
            $saved = $this->consent->save([
                'preferences' => false,
                'analytics' => false,
                'marketing' => false,
            ]);
        } else { // action === 'save'
            $saved = $this->consent->save([
                'preferences' => !empty($_POST['preferences']),
                'analytics' => !empty($_POST['analytics']),
                'marketing' => !empty($_POST['marketing']),
            ]);
        }

        return $this->json(['ok' => true, 'consent' => $saved->toPayload()], 200);
    }

    /**
     * Withdraw all consent and delete consent cookie.
     *
     * @return mixed JSON response
     */
    public function withdraw()
    {
        $this->csrf->assertValid($this->readCsrfToken());
        $this->consent->withdraw();

        return $this->json(['ok' => true], 200);
    }

    /**
     * Read CSRF token from POST body or HTTP header.
     *
     * Supports both traditional form submissions and fetch API requests.
     *
     * @return string|null CSRF token or null if not found
     */
    private function readCsrfToken(): ?string
    {
        // Check POST body first
        $bodyToken = $_POST['_token'] ?? null;
        if (is_string($bodyToken) && $bodyToken !== '') {
            return $bodyToken;
        }

        // Fall back to X-CSRF-TOKEN header for fetch() requests
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return (is_string($headerToken) && $headerToken !== '') ? $headerToken : null;
    }
}
