<?php

declare(strict_types=1);

namespace App\Services\Captcha;

/**
 * Outcome of checking a challenge answer. The value goes into the audit log.
 */
enum CaptchaResult: string
{
    case Passed = 'passed';
    case Missing = 'missing';
    case Malformed = 'malformed';
    case Expired = 'expired';
    case Invalid = 'invalid';
    case Mismatch = 'mismatch';
    case Replayed = 'replayed';

    public function passed(): bool
    {
        return $this === self::Passed;
    }
}
