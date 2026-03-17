<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ConsentService;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

class LocaleMiddleware implements MiddlewareInterface
{
    private array $supported;

    private string $default;

    private ConsentService $consent;

    public function __construct(ConsentService $consent)
    {
        $cfg = require ROOT_PATH.'/config/localization.php';
        $this->supported = $cfg['supported'];
        $this->default = $cfg['default'];
        $this->consent = $consent;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // 0) Trust session first (prefix in index.php sets this)
        $lang = $_SESSION['locale'] ?? null;

        // 1) Explicit query param (?lang=xx) overrides if session not set
        if (!$lang && isset($_GET['lang'])) {
            $lang = $_GET['lang'];
        }

        // 2) Cookie (previous preference) if still not decided
        if (
            !$lang
            && $this->consent->allows('preferences')
            && isset($_COOKIE['locale'])
        ) {
            $lang = $_COOKIE['locale'];
        }

        // 3) Accept-Language header (first visit/no preference)
        if (!$lang && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = $this->pickPreferred($_SERVER['HTTP_ACCEPT_LANGUAGE'], $this->supported);
        }

        // 4) Validate or fallback to default
        if (!in_array($lang, $this->supported, true)) {
            $lang = $this->default;
        }

        // Persist for this and future requests
        $_SESSION['locale'] = $lang;

        // only persist the locale cookie if preferences consent is granted.
        if ($this->consent->allows('preferences')) {
            $this->persistCookie($lang);
        } elseif (isset($_COOKIE['locale'])) {
            // clear old preference cookies when the user rejects preferences.
            setcookie('locale', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => false,
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax',
            ]);

            unset($_COOKIE['locale']);
        }

        // Optional: set PHP/Intl locale if you localize dates/numbers
        // setlocale(LC_ALL, $this->toPhpLocale($lang));
        // \Locale::setDefault($lang);

        return $handler->handle($request);
    }

    private function persistCookie(string $lang): void
    {
        setcookie('locale', $lang, [
            'expires' => time() + 31536000,   // 1 year
            'path' => '/',
            'httponly' => false,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
    }

    // Very small parser for "en-US,en;q=0.9,fr;q=0.8"
    private function pickPreferred(string $header, array $supported): ?string
    {
        $langs = array_map('trim', explode(',', $header));
        foreach ($langs as $entry) {
            $parts = explode(';', $entry);
            $code = strtolower(trim($parts[0]));
            // Try exact, then base language
            if (in_array($code, $supported, true)) {
                return $code;
            }
            $base = strtok($code, '-');
            if ($base && in_array($base, $supported, true)) {
                return $base;
            }
        }

        return null;
    }

    private function toPhpLocale(string $lang): string
    {
        return match ($lang) {
            'en' => 'en_US.UTF-8',
            'fr' => 'fr_FR.UTF-8',
            'de' => 'de_DE.UTF-8',
            'gr' => 'el_GR.UTF-8',
            default => 'en_US.UTF-8',
        };
    }
}
