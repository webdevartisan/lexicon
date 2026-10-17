<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Framework\Cache\RateLimitStore;

/**
 * Proof-of-work challenges with the ALTCHA library, verified locally.
 *
 * The browser has to try PBKDF2 derives until it reaches the counter hidden in
 * the challenge, so each answer costs CPU time. Nothing leaves this server.
 */
final class AltchaCaptcha implements CaptchaInterface
{
    // A real answer is under 1 KB; anything far bigger is not worth decoding.
    private const MAX_PAYLOAD_BYTES = 4096;

    /**
     * @param  string  $bindingKey  Secret for hashing the subject into the challenge
     * @param  array{cost: int, counter_min: int, counter_max: int, max_level: int, expires_seconds: int}  $config
     */
    public function __construct(
        private Altcha $altcha,
        private RateLimitStore $store,
        private string $bindingKey,
        private array $config,
    ) {}

    public function issue(string $purpose, string $subject = '', int $level = 0): array
    {
        $scale = 2 ** max(0, min($level, $this->config['max_level']));

        $challenge = $this->altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: $this->config['cost'],
            counter: random_int($this->config['counter_min'] * $scale, $this->config['counter_max'] * $scale),
            expiresAt: time() + $this->config['expires_seconds'],
            data: ['purpose' => $purpose, 'subject' => $this->bind($subject)],
        ));

        return $challenge->toArray();
    }

    public function verify(string $payload, string $purpose, string $subject = ''): CaptchaResult
    {
        if ($payload === '') {
            return CaptchaResult::Missing;
        }

        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            return CaptchaResult::Malformed;
        }

        try {
            $decoded = Payload::fromBase64($payload);
        } catch (\InvalidArgumentException) {
            return CaptchaResult::Malformed;
        }

        $result = $this->altcha->verifySolution(new VerifySolutionOptions(payload: $decoded, algorithm: new Pbkdf2()));

        if ($result->expired) {
            return CaptchaResult::Expired;
        }

        if (!$result->verified) {
            return CaptchaResult::Invalid;
        }

        $data = $decoded->challenge->parameters->data ?? [];
        if (($data['purpose'] ?? null) !== $purpose || !hash_equals($this->bind($subject), (string) ($data['subject'] ?? ''))) {
            return CaptchaResult::Mismatch;
        }

        // The signature is unique per challenge, and the claim lives as long as the challenge can
        if (!$this->store->claim('captcha:used:'.(string) $decoded->challenge->signature, $this->config['expires_seconds'])) {
            return CaptchaResult::Replayed;
        }

        return CaptchaResult::Passed;
    }

    /**
     * Hash the subject so the challenge shows the browser no address, only a tag it cannot reuse elsewhere.
     */
    private function bind(string $subject): string
    {
        if ($subject === '') {
            return '';
        }

        return hash_hmac('sha256', mb_strtolower(trim($subject)), $this->bindingKey);
    }
}
