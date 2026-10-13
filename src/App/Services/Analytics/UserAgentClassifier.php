<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Reduces a user agent to device, browser and operating system, and spots crawlers.
 */
class UserAgentClassifier
{
    /** Checked in order, since several browsers carry "Chrome" or "Safari" in their string. */
    private const BROWSERS = [
        'Edge' => '/Edg(e|A|iOS)?\//',
        'Opera' => '/OPR\/|Opera/',
        'Samsung Internet' => '/SamsungBrowser\//',
        'Yandex' => '/YaBrowser\//',
        'Vivaldi' => '/Vivaldi\//',
        'Firefox' => '/Firefox\/|FxiOS\//',
        'Chrome' => '/Chrome\/|CriOS\//',
        'Safari' => '/Version\/[\d.]+.*Safari\//',
        'Internet Explorer' => '/MSIE |Trident\//',
    ];

    private const BROWSER_VERSIONS = [
        'Edge' => '/Edg(?:e|A|iOS)?\/(\d+)/',
        'Opera' => '/OPR\/(\d+)/',
        'Samsung Internet' => '/SamsungBrowser\/(\d+)/',
        'Yandex' => '/YaBrowser\/(\d+)/',
        'Vivaldi' => '/Vivaldi\/(\d+)/',
        'Firefox' => '/(?:Firefox|FxiOS)\/(\d+)/',
        'Chrome' => '/(?:Chrome|CriOS)\/(\d+)/',
        'Safari' => '/Version\/(\d+(?:\.\d+)?)/',
        'Internet Explorer' => '/(?:MSIE |rv:)(\d+)/',
    ];

    private const SYSTEMS = [
        'iOS' => '/iPhone|iPad|iPod/',
        'Android' => '/Android/',
        'Windows' => '/Windows/',
        'ChromeOS' => '/CrOS/',
        'macOS' => '/Macintosh|Mac OS X/',
        'Linux' => '/Linux/',
    ];

    /**
     * @param  list<string>  $patterns  Lower-case substrings that mark a crawler
     */
    public function __construct(private array $patterns) {}

    /**
     * @param  list<string>  $extraPatterns  Added by an administrator at runtime
     */
    public function isBot(string $userAgent, array $extraPatterns = []): bool
    {
        // Every real browser sends one. Tools and scripts often don't.
        if (trim($userAgent) === '') {
            return true;
        }

        $ua = strtolower($userAgent);

        foreach ([...$this->patterns, ...$extraPatterns] as $pattern) {
            if ($pattern !== '' && str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{device: string, browser: string, os: string}
     */
    public function classify(string $userAgent): array
    {
        return [
            'device' => $this->device($userAgent),
            'browser' => $this->match(self::BROWSERS, $userAgent),
            'os' => $this->match(self::SYSTEMS, $userAgent),
        ];
    }

    /**
     * Major version of the browser classify() found, where the user agent
     * still carries one.
     */
    public function browserVersion(string $userAgent, string $browser): ?string
    {
        $pattern = self::BROWSER_VERSIONS[$browser] ?? null;

        if ($pattern === null || preg_match($pattern, $userAgent, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /**
     * Version of iOS or Android. Desktop systems are left out because browsers
     * freeze those numbers (every Windows 10 and 11 reports "Windows NT 10.0").
     */
    public function osVersion(string $userAgent, string $os): ?string
    {
        $pattern = match ($os) {
            'iOS' => '/OS (\d+)[_.](\d+)/',
            'Android' => '/Android (\d+)/',
            default => null,
        };

        if ($pattern === null || preg_match($pattern, $userAgent, $m) !== 1) {
            return null;
        }

        return isset($m[2]) ? $m[1].'.'.$m[2] : $m[1];
    }

    private function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|Android(?!.*Mobile)/', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Mobi|iPhone|iPod|Windows Phone/', $ua)) {
            return 'mobile';
        }

        if (preg_match('/Windows|Macintosh|X11|CrOS|Linux/', $ua)) {
            return 'desktop';
        }

        return 'other';
    }

    /**
     * @param  array<string, string>  $families
     */
    private function match(array $families, string $ua): string
    {
        foreach ($families as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return 'Other';
    }
}
