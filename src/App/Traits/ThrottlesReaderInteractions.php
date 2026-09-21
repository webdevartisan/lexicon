<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\CommentRateLimiter;
use Framework\Core\Response;

/**
 * The per-IP limit on reader votes and reports, shared by the comment thread
 * and the post action bar so both refuse in the same shape.
 *
 * The using controller holds the limiter in a `$throttle` property.
 *
 * @property CommentRateLimiter $throttle
 */
trait ThrottlesReaderInteractions
{
    /**
     * Record a vote/report attempt and build the 429 when the caller is over.
     *
     * @return Response|null The refusal to return, or null to carry on
     */
    private function interactionThrottleResponse(): ?Response
    {
        $ip = $this->clientIp();

        if (!$this->throttle->hitInteraction($ip)) {
            return null;
        }

        $wait = $this->throttle->interactionAvailableIn($ip);
        $this->response->addHeader('Retry-After', (string) $wait);

        return $this->jsonError($this->waitMessage($wait), 429);
    }

    private function clientIp(): string
    {
        return $this->request->ip() ?? 'unknown';
    }

    /**
     * Phrase the refusal in minutes so it reads like a pause, not a failure.
     *
     * @param  int  $seconds  Seconds left on the throttle
     */
    private function waitMessage(int $seconds): string
    {
        if ($seconds < 60) {
            return 'You are going a little fast. Try again in a few seconds.';
        }

        $minutes = (int) ceil($seconds / 60);

        return "You are going a little fast. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.';
    }
}
