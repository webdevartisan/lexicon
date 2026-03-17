<?php

declare(strict_types=1);

namespace App\Privacy;

/**
 * Consent value object stored in a signed cookie.
 */
final class Consent
{
    /**
     * @param  array<string,bool>  $categories
     */
    public function __construct(
        public readonly int $version,
        public readonly int $timestamp,
        public readonly array $categories,
    ) {}

    public function allows(string $category): bool
    {
        return !empty($this->categories[$category]);
    }

    /**
     * @return array{v:int,ts:int,c:array<string,bool>}
     */
    public function toPayload(): array
    {
        return [
            'v' => $this->version,
            'ts' => $this->timestamp,
            'c' => $this->categories,
        ];
    }

    public static function fromPayload(array $payload): ?self
    {
        if (!isset($payload['v'], $payload['ts'], $payload['c']) || !is_array($payload['c'])) {
            return null;
        }

        $c = $payload['c'];

        return new self(
            (int) $payload['v'],
            (int) $payload['ts'],
            [
                'necessary' => (bool) ($c['necessary'] ?? true),
                'preferences' => (bool) ($c['preferences'] ?? false),
                'analytics' => (bool) ($c['analytics'] ?? false),
                'marketing' => (bool) ($c['marketing'] ?? false),
            ]
        );
    }
}
