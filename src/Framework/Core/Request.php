<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * Lightweight HTTP request value object.
 *
 * Purpose:
 * - Capture the HTTP request state (URI, method, query, body, files, cookies,
 *   server vars, headers) in a single, testable object.
 * - Provide small convenience methods (method checks, input lookup, headers)
 *   without mixing in validation or business logic.
 */
class Request
{
    /**
     * @param  string  $uri  Raw request URI (e.g. "/en/blogs?page=2").
     * @param  string  $method  HTTP method in uppercase (GET, POST, ...).
     * @param  array<string,mixed>  $get  $_GET
     * @param  array<string,mixed>  $post  $_POST
     * @param  array<string,mixed>  $files  $_FILES
     * @param  array<string,mixed>  $cookie  $_COOKIE
     * @param  array<string,mixed>  $server  $_SERVER
     * @param  array<string,string>  $headers  Normalized header names (lowercase) → value.
     */
    public function __construct(
        public string $uri,
        public string $method,
        public array $get,
        public array $post,
        public array $files,
        public array $cookie,
        public array $server,
        public array $headers
    ) {}

    /**
     * Creates a Request object from PHP superglobals.
     */
    public static function createFromGlobals(): static
    {
        $rawHeaders = self::readHeaders();

        // Normalize header keys to lowercase for case-insensitive lookup.
        $headers = [];
        foreach ($rawHeaders as $key => $value) {
            $headers[strtolower($key)] = $value;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return new static(
            $uri,
            $method,
            $_GET ?? [],
            $_POST ?? [],
            $_FILES ?? [],
            $_COOKIE ?? [],
            $_SERVER ?? [],
            $headers
        );
    }

    /**
     * Portable header reader that works even if getallheaders() is missing.
     *
     * @return array<string,string>
     */
    private static function readHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            return is_array($headers) ? $headers : [];
        }

        // Fallback for environments without getallheaders() (e.g. CLI server, some SAPIs).
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                // HTTP_FOO_BAR => Foo-Bar
                $key = str_replace('_', '-', substr($name, 5));
                $headers[$key] = (string) $value;
            } elseif ($name === 'CONTENT_TYPE' || $name === 'CONTENT_LENGTH') {
                // These are not prefixed with HTTP_ but are still headers.
                $key = str_replace('_', '-', $name);
                $headers[$key] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * Generic method check.
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Convenience: true if method is POST.
     */
    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * Get a query (GET) parameter.
     *
     * @param  mixed  $default  Value to return if key is not present.
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Get a POST/body parameter.
     *
     * @param  mixed  $default  Value to return if key is not present.
     */
    public function postParam(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get a parameter from POST first, then GET as a fallback.
     *
     * @param  mixed  $default  Value to return if key is not present.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if (array_key_exists($key, $this->get)) {
            return $this->get[$key];
        }

        return $default;
    }

    /**
     * Get all input from GET + POST merged (POST wins on conflicts).
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    /**
     * Retrieve a header by name (case-insensitive).
     *
     * Examples:
     *   $request->header('Content-Type');
     *   $request->header('content-type');
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = strtolower($key);

        return $this->headers[$normalized] ?? $default;
    }

    /**
     * Determine whether the request is over HTTPS.
     *
     * Uses common server vars and proxy headers; proxy headers should only be
     * trusted if your app is behind a known, configured reverse proxy.
     */
    public function isSecure(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        $proto = $this->server['HTTP_X_FORWARDED_PROTO'] ?? '';
        if ($proto !== '' && stripos($proto, 'https') !== false) {
            return true;
        }

        return (string) ($this->server['SERVER_PORT'] ?? '') === '443';
    }

    /**
     * Return the client IP address if available.
     *
     * Note: for apps behind proxies, we would likely introduce a separate
     * trusted-proxy IP resolver instead of reading HTTP_X_FORWARDED_FOR here.
     */
    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }

    /**
     * True if the request was made via AJAX (XMLHttpRequest).
     */
    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Path portion of the URI (without query string).
     *
     * Always returns at least "/".
     */
    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH) ?: '/';

        return $path === '' ? '/' : $path;
    }

    /**
     * Full URL including scheme, host, and URI.
     *
     * Useful for redirects and canonical tags.
     */
    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';

        return $scheme.'://'.$host.$this->uri;
    }
}
