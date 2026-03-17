<?php

declare(strict_types=1);

namespace App\Services;

use App\Privacy\Consent;
use App\Privacy\ConsentCookieStore;

/**
 * We keep all consent decisions in one place so views/controllers remain DRY.
 */
final class ConsentService
{
    public function __construct(
        private readonly ConsentCookieStore $store,
        private readonly array $config,
    ) {}

    public function current(): ?Consent
    {
        $consent = $this->store->read();
        if ($consent === null) {
            return null;
        }

        $expectedVersion = (int) ($this->config['version'] ?? 1);
        if ($consent->version !== $expectedVersion) {
            // force a re-prompt after consent schema changes.
            return null;
        }

        return $consent;
    }

    public function allows(string $category): bool
    {
        if ($category === 'necessary') {
            return true;
        }

        $consent = $this->current();
        if ($consent === null) {
            return false;
        }

        return $consent->allows($category);
    }

    /**
     * @param  array<string,bool>  $categories
     */
    public function save(array $categories): Consent
    {
        $base = (array) ($this->config['categories'] ?? []);

        $final = ['necessary' => true];

        foreach ($base as $key => $default) {
            if ($key === 'necessary') {
                $final['necessary'] = true;
                continue;
            }

            $final[$key] = (bool) ($categories[$key] ?? false);
        }

        $consent = new Consent(
            (int) ($this->config['version'] ?? 1),
            time(),
            $final
        );

        $this->store->write($consent);

        return $consent;
    }

    public function withdraw(): void
    {
        $this->store->clear();
    }
}
