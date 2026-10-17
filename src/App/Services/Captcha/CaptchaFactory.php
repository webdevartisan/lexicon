<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use AltchaOrg\Altcha\Altcha;
use Framework\Cache\RateLimitStore;
use Framework\Core\ErrorHandler;

/**
 * Builds the configured sign-in check from config/captcha.php.
 */
final class CaptchaFactory
{
    /**
     * @param  array<string, mixed>  $config  config/captcha.php
     * @param  string  $appKey  APP_KEY, used when no dedicated key is set
     *
     * @throws \RuntimeException If the provider is unknown, or no secret is set outside local development
     */
    public static function make(array $config, string $appKey, RateLimitStore $store): CaptchaInterface
    {
        if ($config['provider'] !== 'altcha') {
            throw new \RuntimeException("CAPTCHA_PROVIDER '{$config['provider']}' is not supported. Use 'altcha'.");
        }

        $secret = $config['hmac_key'] !== '' ? (string) $config['hmac_key'] : $appKey;

        if (!ErrorHandler::showsDetails() && ($secret === '' || $secret === 'change-me')) {
            throw new \RuntimeException('Set CAPTCHA_HMAC_KEY or APP_KEY before running the application: the sign-in check needs a secret.');
        }

        // Three keys from one secret with separate labels, so none can stand in for another
        return new AltchaCaptcha(
            new Altcha(
                hmacSignatureSecret: hash_hmac('sha256', 'captcha:signature', $secret),
                hmacKeySignatureSecret: hash_hmac('sha256', 'captcha:key-signature', $secret),
            ),
            $store,
            hash_hmac('sha256', 'captcha:subject', $secret),
            $config
        );
    }
}
