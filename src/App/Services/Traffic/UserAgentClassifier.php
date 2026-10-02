<?php

declare(strict_types=1);

namespace App\Services\Traffic;

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
