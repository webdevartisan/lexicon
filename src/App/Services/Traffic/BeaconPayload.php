<?php

declare(strict_types=1);

namespace App\Services\Traffic;

/**
 * What the page script sent, checked and trimmed. Only the listed keys are
 * read, and the page arrives as a path, never as ids.
 */
final class BeaconPayload
{
    private function __construct(
        public readonly string $viewId,
        public readonly string $path,
        public readonly string $referrer,
        public readonly ?string $utmSource,
        public readonly ?string $utmMedium,
        public readonly ?string $utmCampaign,
        public readonly int $seconds,
        public readonly int $scrollDepth,
    ) {}

    /**
     * A page view: {v, p, r?, us?, um?, uc?}.
     */
    public static function view(string $body): ?self
    {
        $data = self::decode($body);

        if ($data === null || !isset($data['p']) || !is_string($data['p'])) {
            return null;
        }

        $viewId = self::viewId($data['v'] ?? null);
        $path = $data['p'];

        if ($viewId === null || $path === '' || $path[0] !== '/' || strlen($path) > 255) {
            return null;
        }

        return new self(
            viewId: $viewId,
            path: $path,
            referrer: self::text($data['r'] ?? null, 1000) ?? '',
            utmSource: self::text($data['us'] ?? null, 100),
            utmMedium: self::text($data['um'] ?? null, 100),
            utmCampaign: self::text($data['uc'] ?? null, 100),
            seconds: 0,
            scrollDepth: 0,
        );
    }

    /**
     * A leave ping: {v, s, d}.
     */
    public static function engagement(string $body, int $maxSeconds): ?self
    {
        $data = self::decode($body);
        $viewId = self::viewId($data['v'] ?? null);

        if ($data === null || $viewId === null || !is_int($data['s'] ?? null) || !is_int($data['d'] ?? null)) {
            return null;
        }

        return new self(
            viewId: $viewId,
            path: '',
            referrer: '',
            utmSource: null,
            utmMedium: null,
            utmCampaign: null,
            seconds: max(0, min($maxSeconds, $data['s'])),
            scrollDepth: max(0, min(100, $data['d'])),
        );
    }

    /**
     * @return string 16 raw bytes
     */
    public function viewIdBytes(): string
    {
        return (string) hex2bin($this->viewId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        try {
            $data = json_decode($body, true, 3, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) && !array_is_list($data) ? $data : null;
    }

    private static function viewId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $hex = strtolower(str_replace('-', '', $value));

        return preg_match('/^[0-9a-f]{32}$/', $hex) ? $hex : null;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
