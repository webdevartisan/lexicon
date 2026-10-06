<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * What the page script sent, checked and trimmed. Only the listed keys are
 * read, and the page arrives as a path, never as ids.
 */
final class BeaconPayload
{
    /**
     * @param  array<mixed>  $eventProps  Unchecked here; EventRegistry::clean() decides what is kept
     * @param  array{lcp_ms?: int, inp_ms?: int, cls?: float, ttfb_ms?: int}  $vitals
     */
    private function __construct(
        public readonly string $viewId,
        public readonly string $path = '',
        public readonly string $referrer = '',
        public readonly ?string $utmSource = null,
        public readonly ?string $utmMedium = null,
        public readonly ?string $utmCampaign = null,
        public readonly int $seconds = 0,
        public readonly int $scrollDepth = 0,
        public readonly bool $notFound = false,
        public readonly ?string $searchTerm = null,
        public readonly ?int $searchResults = null,
        public readonly ?string $via = null,
        public readonly ?string $eventName = null,
        public readonly array $eventProps = [],
        public readonly array $vitals = [],
    ) {}

    /** How a page was reached, when the page before knew: a related post link. */
    private const VIA = ['related'];

    /** Page speed values above these are a broken clock, not a slow page. */
    private const MAX_MILLISECONDS = 60000;

    private const MAX_CLS = 10;

    /**
     * A page view: {v, p, r?, us?, um?, uc?, nf?, q?, sr?, via?}. nf marks a page that
     * answered 404, q is what was searched on a page that searches and sr how many
     * results it found, via how the reader got here from the page before.
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

        $results = $data['sr'] ?? null;

        return new self(
            viewId: $viewId,
            path: $path,
            referrer: self::text($data['r'] ?? null, 1000) ?? '',
            utmSource: self::text($data['us'] ?? null, 100),
            utmMedium: self::text($data['um'] ?? null, 100),
            utmCampaign: self::text($data['uc'] ?? null, 100),
            notFound: ($data['nf'] ?? null) === 1,
            searchTerm: self::text($data['q'] ?? null, 100),
            searchResults: is_int($results) && $results >= 0 ? min($results, 100000) : null,
            via: in_array($data['via'] ?? null, self::VIA, true) ? $data['via'] : null,
        );
    }

    /**
     * Something the reader did on a counted view: {v, n, p?}, n being an event the
     * page script may send and p its props, which the event registry checks.
     */
    public static function event(string $body): ?self
    {
        $data = self::decode($body);
        $viewId = self::viewId($data['v'] ?? null);
        $name = $data['n'] ?? null;
        $props = $data['p'] ?? [];

        if ($data === null || $viewId === null || !is_string($name) || !preg_match('/^[a-z_]{1,32}$/', $name) || !is_array($props)) {
            return null;
        }

        return new self(viewId: $viewId, eventName: $name, eventProps: $props);
    }

    /**
     * A leave ping: {v, s, d, lcp?, inp?, cls?, ttfb?}. The page speed values are
     * dropped one by one when out of range, so a bad one never costs the reading time.
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
            seconds: max(0, min($maxSeconds, $data['s'])),
            scrollDepth: max(0, min(100, $data['d'])),
            vitals: self::vitals($data),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lcp_ms?: int, inp_ms?: int, cls?: float, ttfb_ms?: int}
     */
    private static function vitals(array $data): array
    {
        $vitals = [];

        foreach (['lcp' => 'lcp_ms', 'inp' => 'inp_ms', 'ttfb' => 'ttfb_ms'] as $key => $column) {
            $value = $data[$key] ?? null;
            if (is_int($value) && $value >= 0 && $value <= self::MAX_MILLISECONDS) {
                $vitals[$column] = $value;
            }
        }

        $cls = $data['cls'] ?? null;
        if ((is_int($cls) || is_float($cls)) && $cls >= 0 && $cls <= self::MAX_CLS) {
            $vitals['cls'] = round((float) $cls, 4);
        }

        return $vitals;
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
