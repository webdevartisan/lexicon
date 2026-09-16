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
    /**
     * @param  array<string, mixed>  $config  Consent configuration (categories, version)
     */
    public function __construct(
        private readonly ConsentCookieStore $store,
        private readonly array $config,
    ) {}

    public function version(): int
    {
        return (int) ($this->config['version'] ?? 1);
    }

    public function current(): ?Consent
    {
        $consent = $this->store->read();
        if ($consent === null) {
            return null;
        }

        if ($consent->version !== $this->version()) {
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
            $this->version(),
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
