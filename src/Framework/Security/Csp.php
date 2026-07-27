<?php

declare(strict_types=1);

namespace Framework\Security;

/**
 * Content-Security-Policy nonce service.
 *
 * Generates one random nonce per request and hands the same value to both
 * the CSP header and any inline <script nonce="..."> tags rendered by
 * templates. Registered as a shared singleton so every caller within a
 * request sees the same nonce.
 */
final class Csp
{
    private ?string $nonce = null;

    /**
     * Get the nonce for the current request, generating it on first use.
     */
    public function getNonce(): string
    {
        if ($this->nonce === null) {
            $this->nonce = base64_encode(random_bytes(16));
        }

        return $this->nonce;
    }
}
