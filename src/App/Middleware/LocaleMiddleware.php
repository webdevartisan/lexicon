<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ConsentService;
use App\Services\LocaleRegistry;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Session;

class LocaleMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $supported;

    private string $default;

    private ConsentService $consent;

    private Session $session;

    public function __construct(ConsentService $consent, LocaleRegistry $registry, Session $session)
    {
        $this->supported = $registry->supported();
        $this->default = $registry->default();
        $this->consent = $consent;
        $this->session = $session;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // 0) Trust session first (prefix in index.php sets this)
        $lang = $this->session->get('locale');

        // 1) Explicit query param (?lang=xx) overrides if session not set
        if (!$lang && $request->getParam('lang') !== null) {
            $lang = $request->getParam('lang');
        }

        // 2) Cookie (previous preference) if still not decided
        if (
            !$lang
            && $this->consent->allows('preferences')
            && isset($request->cookie['locale'])
        ) {
            $lang = $request->cookie['locale'];
        }

        // 3) Accept-Language header (first visit/no preference)
        $acceptLanguage = $request->header('accept-language');
        if (!$lang && $acceptLanguage !== null) {
            $lang = $this->pickPreferred($acceptLanguage, $this->supported);
        }

        // 4) Validate or fallback to default
        if (!in_array($lang, $this->supported, true)) {
            $lang = $this->default;
        }

        // Persist for this and future requests
        $this->session->set('locale', $lang);

        // only persist the locale cookie if preferences consent is granted.
        if ($this->consent->allows('preferences')) {
            $this->persistCookie($lang, $request);
        } elseif (isset($request->cookie['locale'])) {
            // clear old preference cookies when the user rejects preferences.
            setcookie('locale', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => false,
                'secure' => $this->isHttps($request),
                'samesite' => 'Lax',
            ]);

            unset($request->cookie['locale']);
        }

        // Optional: set PHP/Intl locale here if we ever localize dates/numbers,
        // e.g. setlocale(LC_ALL, 'el_GR.UTF-8') and \Locale::setDefault($lang).

        return $handler->handle($request);
    }

    private function persistCookie(string $lang, Request $request): void
    {
        setcookie('locale', $lang, [
            'expires' => time() + 31536000,   // 1 year
            'path' => '/',
            'httponly' => false,
            'secure' => $this->isHttps($request),
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Only the server's own HTTPS flag counts here, not a forwarded-proto header.
     */
    private function isHttps(Request $request): bool
    {
        return isset($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off';
    }

    /**
     * Very small parser for "en-US,en;q=0.9,fr;q=0.8".
     *
     * @param  string[]  $supported  Supported locale codes
     */
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
}
