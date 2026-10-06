<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Turns a referrer URL into a channel, a host and a readable source name.
 * Only the host is kept, since a referrer's path can carry a search query or a private link.
 */
class ReferrerClassifier
{
    private const HOST_PREFIXES = ['www.', 'm.', 'l.', 'lm.', 'mobile.', 'amp.'];

    /**
     * @param  array<string, array{0: string, 1: string}>  $sources  Host pattern => [name, channel]
     * @param  list<string>  $spamHosts
     * @param  list<string>  $emailMediums
     */
    public function __construct(
        private array $sources,
        private array $spamHosts,
        private array $emailMediums,
    ) {}

    /**
     * The source names of one channel, e.g. every search engine.
     *
     * @return list<string>
     */
    public function sourcesIn(string $channel): array
    {
        $names = [];
        foreach ($this->sources as [$name, $sourceChannel]) {
            if ($sourceChannel === $channel) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  string  $ownHost  The site's own host, so moving between its pages isn't a source
     * @return array{channel: string, host: ?string, source: ?string}|null Null when the referrer is spam
     */
    public function classify(string $referrer, string $ownHost, ?string $utmMedium): ?array
    {
        $host = $this->hostOf($referrer);

        if ($host === null) {
            $channel = in_array(strtolower((string) $utmMedium), $this->emailMediums, true) ? 'email' : 'direct';

            return ['channel' => $channel, 'host' => null, 'source' => null];
        }

        if ($host === $this->normalizeHost($ownHost)) {
            return ['channel' => 'internal', 'host' => null, 'source' => null];
        }

        foreach ($this->spamHosts as $spam) {
            if ($host === $spam || str_ends_with($host, '.'.$spam)) {
                return null;
            }
        }

        foreach ($this->sources as $pattern => [$name, $channel]) {
            if (preg_match($pattern, $host)) {
                return ['channel' => $channel, 'host' => $host, 'source' => $name];
            }
        }

        return ['channel' => 'referral', 'host' => $host, 'source' => $host];
    }

    private function hostOf(string $referrer): ?string
    {
        $referrer = trim($referrer);

        if ($referrer === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($referrer, PHP_URL_SCHEME));

        // android-app:// is how Android apps identify themselves. Other schemes
        // (file:, about:, chrome:) say nothing about where the reader came from.
        if (!in_array($scheme, ['http', 'https', 'android-app'], true)) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        return substr($this->normalizeHost($host), 0, 100);
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));

        $colon = strrpos($host, ':');
        if ($colon !== false && !str_contains($host, ']')) {
            $host = substr($host, 0, $colon);
        }

        foreach (self::HOST_PREFIXES as $prefix) {
            if (str_starts_with($host, $prefix)) {
                return substr($host, strlen($prefix));
            }
        }

        return $host;
    }
}
