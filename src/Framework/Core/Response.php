<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * HTTP Response builder.
 *
 * encapsulate response construction (status, headers, body) to provide
 * a fluent API for controllers and middleware.
 */
class Response
{
    /**
     * Response body content.
     */
    private string $body = '';

    /**
     * Response headers (name => value pairs).
     *
     * store header names as-is but sanitize values to prevent injection.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * HTTP status code.
     *
     * default to 200 OK per HTTP spec. Controllers should explicitly
     * set other codes (404, 302, etc.) when needed.
     */
    private int $statusCode = 200;

    /**
     * Sends the HTTP response to the client, including headers, status code, and body.
     *
     * only send if headers haven't been sent already to avoid PHP warnings.
     */
    public function send(): void
    {
        // prevent duplicate header sending in case send() is called twice.
        if (headers_sent()) {
            error_log('Response::send() called but headers already sent');
            echo $this->body;

            return;
        }

        // Always set status code
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            // sanitize header values in addHeader(), so this is safe.
            header($name.': '.$value);
        }

        echo $this->body;
    }

    /**
     * Set the HTTP status code.
     *
     * @param  int  $code  HTTP status code (200, 404, 302, etc.)
     * @return $this
     */
    public function setStatusCode(int $code): self
    {
        // validate common status codes to catch typos early.
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException("Invalid HTTP status code: {$code}");
        }

        $this->statusCode = $code;

        return $this;
    }

    /**
     * Get the currently configured HTTP status code.
     *
     * @return int HTTP status code (always >= 200)
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Redirect to a target URL.
     *
     * normalize the URL for localization and set the Location header.
     * This method does not call send(); the caller must return/send the response.
     *
     * @param  string  $url  Target URL (relative or absolute)
     * @param  int  $status  HTTP redirect status (302 = temporary, 301 = permanent)
     * @return $this
     */
    public function redirect(string $url, int $status = 302): self
    {
        // Normalize the target for localization.
        // assume a global helper buildLocalizedUrl() exists.
        $final = buildLocalizedUrl($url, true);

        // Basic protection: avoid header injection via newlines in the URL.
        $final = str_replace(["\r", "\n"], '', $final);

        $this->setStatusCode($status);
        $this->addHeader('Location', $final);

        // small HTML body for user agents that do not follow Location.
        $this->body = '<html><head><meta http-equiv="refresh" content="0;url='
                     .htmlspecialchars($final, ENT_QUOTES, 'UTF-8')
                     .'"></head><body>Redirecting...</body></html>';

        return $this;
    }

    /**
     * Add or replace a response header.
     *
     * sanitize header names and values to prevent header injection attacks.
     * Header names are case-sensitive in storage but HTTP treats them case-insensitively.
     *
     * @param  string  $name  Header name (e.g., 'Content-Type', 'X-Frame-Options')
     * @param  string  $value  Header value
     */
    public function addHeader(string $name, string $value): void
    {
        // Strip CR/LF to mitigate header injection / response splitting.
        $safeName = str_replace(["\r", "\n", "\0"], '', $name);
        $safeValue = str_replace(["\r", "\n", "\0"], '', $value);

        // Validate header name format
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $safeName)) {
            throw new \InvalidArgumentException("Invalid header name: {$name}");
        }

        $this->headers[$safeName] = $safeValue;
    }

    /**
     * Remove a previously set header.
     *
     * use this to remove restrictive headers like X-Frame-Options
     * when need to allow iframe embedding.
     *
     * @param  string  $name  Header name to remove
     */
    public function removeHeader(string $name): self
    {
        // remove the header if it exists.
        unset($this->headers[$name]);
        // also remove using header_remove for PHP-set headers
        if (!headers_sent()) {
            header_remove($name);
        }

        return $this;
    }

    /**
     * Set the response body.
     *
     * @param  string  $body  Response body content
     * @return $this
     */
    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get the response body.
     *
     * @return string Response body content
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get a header value by name, or null if not set.
     *
     * perform case-insensitive lookup since HTTP headers are case-insensitive.
     *
     * @param  string  $name  Header name
     * @return string|null Header value or null
     */
    public function getHeader(string $name): ?string
    {
        // Case-insensitive header lookup
        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                return $headerValue;
            }
        }

        return null;
    }

    /**
     * Check if a header is set.
     *
     * perform case-insensitive check per HTTP spec.
     *
     * @param  string  $name  Header name
     * @return bool True if header exists
     */
    public function hasHeader(string $name): bool
    {
        // Case-insensitive header check
        foreach ($this->headers as $headerName => $_) {
            if (strcasecmp($headerName, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all headers.
     *
     * @return array<string, string> Header name => value pairs
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set JSON body and Content-Type header.
     *
     * automatically encode the data and set appropriate headers.
     *
     * @param  array  $data  Data to encode as JSON
     *
     * @throws \JsonException When encoding fails
     */
    public function setJson(array $data): void
    {
        $this->addHeader('Content-Type', 'application/json; charset=utf-8');

        $json = json_encode(
            $data,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        $this->setBody($json);
    }

    /**
     * Send raw HTML response without rendering a view.
     *
     * use this for special cases like iframe content, AJAX responses,
     * or API endpoints where don't want template rendering.
     *
     * @param  string  $html  Raw HTML content
     * @param  int  $statusCode  HTTP status code (default: 200)
     */
    public function html(string $html, int $statusCode = 200): self
    {
        // set the status code
        $this->setStatusCode($statusCode);

        // set content type to HTML
        $this->addHeader('Content-Type', 'text/html; charset=UTF-8');

        // remove X-Frame-Options to allow iframe embedding from same origin
        $this->removeHeader('X-Frame-Options');

        // store the HTML content to be sent
        $this->body = $html;

        return $this;
    }
}
