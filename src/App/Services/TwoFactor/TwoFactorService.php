<?php

declare(strict_types=1);

namespace App\Services\TwoFactor;

use App\Models\UserModel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Setup, checking and removal of the authenticator-app second step.
 *
 * Two-factor counts as on only once a code from the app has been accepted
 * (two_factor_confirmed_at), so a setup that is abandoned half way never
 * stands between the owner and their account.
 */
final class TwoFactorService
{
    private const RECOVERY_COUNT = 10;

    private const RECOVERY_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(
        private UserModel $users,
        private Totp $totp,
        private SecretBox $box,
    ) {}

    /**
     * @param  array<string, mixed>  $user
     */
    public function isEnabled(array $user): bool
    {
        return !empty($user['two_factor_confirmed_at']) && !empty($user['two_factor_secret']);
    }

    /**
     * Start (or restart) setup with a fresh secret and return it.
     */
    public function beginSetup(int $userId): string
    {
        $secret = $this->totp->generateSecret();

        $this->users->updateById($userId, [
            'two_factor_secret' => $this->box->seal($secret),
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return $secret;
    }

    /**
     * The secret of a setup that has not been confirmed yet.
     */
    public function pendingSecret(int $userId): ?string
    {
        $user = $this->users->findById($userId);
        if (!$user || $this->isEnabled($user) || empty($user['two_factor_secret'])) {
            return null;
        }

        return $this->box->open((string) $user['two_factor_secret']);
    }

    /**
     * Accept the first code from the app and switch two-factor on.
     *
     * @return string[]|null The recovery codes to show once, or null when the code was wrong
     */
    public function confirmSetup(int $userId, string $code): ?array
    {
        $secret = $this->pendingSecret($userId);
        if ($secret === null) {
            return null;
        }

        $step = $this->totp->matchingStep($secret, $code, time());
        if ($step === null) {
            return null;
        }

        $codes = $this->newRecoveryCodes();
        $this->users->updateById($userId, [
            'two_factor_confirmed_at' => gmdate('Y-m-d H:i:s'),
            'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => $this->hashed($codes),
        ]);

        return $codes;
    }

    /**
     * Check what was typed at sign-in: a code from the app, or a recovery code.
     * Either works once.
     */
    public function verify(int $userId, string $input): bool
    {
        $user = $this->users->findById($userId);
        if (!$user || !$this->isEnabled($user)) {
            return false;
        }

        $secret = $this->box->open((string) $user['two_factor_secret']);
        if ($secret !== null) {
            $step = $this->totp->matchingStep($secret, $input, time());
            if ($step !== null) {
                return $this->users->claimTwoFactorStep($userId, $step);
            }
        }

        return $this->spendRecoveryCode($user, $input);
    }

    /**
     * Replace the recovery list with ten new codes, returned once.
     *
     * @return string[]
     */
    public function regenerateRecoveryCodes(int $userId): array
    {
        $codes = $this->newRecoveryCodes();
        $this->users->updateById($userId, ['two_factor_recovery_codes' => $this->hashed($codes)]);

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function recoveryCodesLeft(array $user): int
    {
        return count($this->storedHashes($user['two_factor_recovery_codes'] ?? null));
    }

    public function disable(int $userId): void
    {
        $this->users->updateById($userId, [
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    /**
     * An inline SVG QR code that adds the account to an authenticator app.
     */
    public function qrSvg(string $secret, string $account, string $issuer): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd()));

        $svg = $writer->writeString($this->totp->provisioningUri($secret, $account, $issuer));

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }

    public function groupedSecret(string $secret): string
    {
        return $this->totp->grouped($secret);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function spendRecoveryCode(array $user, string $input): bool
    {
        $typed = $this->normalise($input);
        if (strlen($typed) !== 10) {
            return false;
        }

        $stored = $user['two_factor_recovery_codes'] ?? null;
        $hash = hash('sha256', $typed);

        $kept = [];
        $matched = false;
        foreach ($this->storedHashes($stored) as $candidate) {
            if (!$matched && hash_equals($candidate, $hash)) {
                $matched = true;

                continue;
            }
            $kept[] = $candidate;
        }

        if (!$matched) {
            return false;
        }

        return $this->users->replaceRecoveryCodes((int) $user['id'], $stored, json_encode($kept, JSON_THROW_ON_ERROR));
    }

    /**
     * @return string[]
     */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        $max = strlen(self::RECOVERY_ALPHABET) - 1;

        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $raw = '';
            for ($c = 0; $c < 10; $c++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, $max)];
            }
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        return $codes;
    }

    /**
     * @param  string[]  $codes
     */
    private function hashed(array $codes): string
    {
        return json_encode(
            array_map(fn (string $code) => hash('sha256', $this->normalise($code)), $codes),
            JSON_THROW_ON_ERROR
        );
    }

    private function normalise(string $code): string
    {
        return preg_replace('/[\s-]+/', '', strtolower($code)) ?? '';
    }

    /**
     * @return string[]
     */
    private function storedHashes(mixed $json): array
    {
        $list = is_string($json) && $json !== '' ? json_decode($json, true) : [];

        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }
}
