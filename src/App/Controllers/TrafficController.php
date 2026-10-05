<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ConsentService;
use App\Services\ImpersonationService;
use App\Services\Traffic\BeaconPayload;
use App\Services\Traffic\TrafficRateLimiter;
use App\Services\Traffic\TrafficRecorder;
use App\Services\Traffic\VisitorCookie;
use App\Services\Traffic\VisitorLink;
use Framework\Core\Response;

/**
 * Receives the page view beacon and the leave ping from public pages.
 *
 * CSRF-exempt because cached public pages can't carry a per-visitor token.
 * Instead it takes same-origin requests only, a small strictly parsed body,
 * a per-IP limit and a path the server resolves itself. Every accepted request
 * gets 204, counted or not.
 */
final class TrafficController extends AppController
{
    public function __construct(
        private TrafficRecorder $recorder,
        private TrafficRateLimiter $limiter,
        private ConsentService $consent,
        private VisitorCookie $visitorCookie,
        private VisitorLink $visitorLink,
        private ImpersonationService $impersonation,
    ) {}

    public function hit(): Response
    {
        $rejected = $this->rejectUntrusted();
        if ($rejected !== null) {
            return $rejected;
        }

        $payload = BeaconPayload::view($this->request->rawBody($this->recorder->maxBodyBytes()));
        if ($payload === null) {
            return $this->status(400);
        }

        $viewer = auth()->user();
        $impersonating = $this->impersonation->isImpersonating();

        $this->recorder->record(
            $this->request,
            $payload,
            $viewer,
            $impersonating,
            // Never counted, and must not move this browser's views onto the account being acted as.
            $impersonating ? null : $this->visitorCookieId($viewer)
        );

        return $this->status(204);
    }

    public function engage(): Response
    {
        $rejected = $this->rejectUntrusted();
        if ($rejected !== null) {
            return $rejected;
        }

        $body = $this->request->rawBody($this->recorder->maxBodyBytes());
        $payload = BeaconPayload::engagement($body, $this->recorder->maxEngagedSeconds());
        if ($payload === null) {
            return $this->status(400);
        }

        $this->recorder->recordEngagement($payload);

        return $this->status(204);
    }

    /**
     * Cross-site senders, floods and oversized bodies, before anything is parsed.
     */
    private function rejectUntrusted(): ?Response
    {
        if (!$this->isSameOrigin()) {
            return $this->status(403);
        }

        if ($this->limiter->hitAndCheck($this->request->ip() ?? 'unknown')) {
            return $this->status(429);
        }

        if ((int) $this->request->header('Content-Length', '0') > $this->recorder->maxBodyBytes()) {
            return $this->status(413);
        }

        return null;
    }

    /**
     * The analytics cookie for a visitor who accepted analytics, issued on their
     * first view after accepting. Anyone else loses a cookie left from an earlier choice.
     *
     * @param  array<string, mixed>|null  $viewer
     */
    private function visitorCookieId(?array $viewer): ?string
    {
        $userId = $viewer === null ? null : (int) $viewer['id'];

        if (!$this->consent->allows('analytics') || TrafficRecorder::optedOut($this->request)) {
            $this->visitorLink->dropCookie($this->request, $userId);

            return null;
        }

        $cookieId = $this->visitorCookie->read($this->request);
        if ($cookieId !== null) {
            return $cookieId;
        }

        $cookieId = $this->visitorCookie->issue();

        // A signed-in reader is counted by account from here on, so this visit joins the account.
        if ($userId !== null) {
            $this->visitorLink->toAccount($this->request, $userId);
        } else {
            $this->visitorLink->toCookie($this->request, $cookieId);
        }

        return $cookieId;
    }

    /**
     * Browsers send Origin on every POST, and Sec-Fetch-Site where they support it.
     * A request with neither didn't come from a page on this site.
     */
    private function isSameOrigin(): bool
    {
        $origin = $this->request->header('Origin');

        if ($origin !== null && $origin !== '') {
            return rtrim($origin, '/') === $this->ownOrigin();
        }

        return $this->request->header('Sec-Fetch-Site') === 'same-origin';
    }

    private function ownOrigin(): string
    {
        $parts = parse_url((string) base_url());
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }

    private function status(int $code): Response
    {
        $this->response->setStatusCode($code);
        $this->response->addHeader('Cache-Control', 'no-store');

        return $this->response;
    }
}
