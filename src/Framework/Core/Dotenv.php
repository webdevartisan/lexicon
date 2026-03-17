<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * Environment Variable Loader
 *
 * Parses .env files and populates the $_ENV superglobal with configuration values.
 * Handles comments, empty lines, quoted values, and variable expansion.
 *
 * All values are stored as strings in $_ENV. Type conversion happens at retrieval
 * time via the get() method, following Laravel conventions.
 *
 * Supported formats:
 * - Simple: KEY=value
 * - Quoted: KEY="value with spaces"
 * - Comments: # This is a comment
 * - Empty lines: (ignored)
 * - Inline comments: KEY=value # comment
 * - Variable expansion: KEY=${OTHER_KEY}
 *
 * Security considerations:
 * - Never expose .env files to the web (keep outside public root)
 * - File path validation prevents directory traversal attacks
 * - File size limits prevent memory exhaustion
 * - Key name validation prevents malformed entries
 * - Circular reference protection prevents infinite loops
 */
class Dotenv
{
    /**
     * Maximum allowed .env file size (1MB)
     *
     * Enforced to prevent memory exhaustion attacks
     */
    private const MAX_FILE_SIZE = 1048576;

    /**
     * Maximum variable expansion depth
     *
     * Used to detect and prevent circular reference infinite loops
     */
    private const MAX_EXPANSION_DEPTH = 10;

    /**
     * Track variable expansion depth to prevent circular references
     */
    private int $expansionDepth = 0;

    /**
     * Load environment variables from a .env file.
     *
     * Parses each line, handling comments, empty lines, and quoted values.
     * Variables are populated into $_ENV and optionally into $_SERVER.
     *
     * @param  string  $path  Absolute path to .env file
     * @param  bool  $overwrite  Whether to overwrite existing environment variables
     * @param  bool  $populateServer  Whether to also populate $_SERVER (default: false)
     *
     * @throws \RuntimeException If .env file doesn't exist, isn't readable, or is invalid
     * @throws \InvalidArgumentException If file path contains directory traversal attempts
     */
    public function load(string $path, bool $overwrite = false, bool $populateServer = false): void
    {
        $this->validatePath($path);

        if (!file_exists($path)) {
            throw new \RuntimeException("Environment file not found: {$path}");
        }

        if (!is_readable($path)) {
            throw new \RuntimeException("Environment file is not readable: {$path}");
        }

        // Enforce file size limit to prevent memory exhaustion
        $fileSize = filesize($path);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            throw new \RuntimeException(
                sprintf(
                    "Environment file exceeds maximum size of %d bytes: {$path}",
                    self::MAX_FILE_SIZE
                )
            );
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new \RuntimeException("Failed to read environment file: {$path}");
        }

        foreach ($lines as $lineNumber => $line) {
            $this->processLine($line, $lineNumber, $overwrite, $populateServer);
        }
    }

    /**
     * Validate file path to prevent directory traversal attacks.
     *
     * Ensures the path doesn't contain suspicious patterns that could
     * be used to access files outside the intended directory.
     *
     * @param  string  $path  Path to validate
     *
     * @throws \InvalidArgumentException If path contains directory traversal patterns
     */
    private function validatePath(string $path): void
    {
        if (preg_match('#(\.\./|\.\.\\\\)#', $path)) {
            throw new \InvalidArgumentException(
                'Invalid .env path: directory traversal detected'
            );
        }

        $realPath = realpath(dirname($path));
        if ($realPath === false) {
            throw new \InvalidArgumentException(
                'Invalid .env path: directory does not exist'
            );
        }
    }

    /**
     * Process a single line from the .env file.
     *
     * Parses the line and populates environment variables, handling
     * comments, empty lines, and invalid formats gracefully.
     *
     * @param  string  $line  Line content
     * @param  int  $lineNumber  Line number for error reporting
     * @param  bool  $overwrite  Whether to overwrite existing variables
     * @param  bool  $populateServer  Whether to populate $_SERVER
     */
    private function processLine(
        string $line,
        int $lineNumber,
        bool $overwrite,
        bool $populateServer
    ): void {
        $line = trim($line);

        if ($line === '' || $this->isComment($line)) {
            return;
        }

        $parsed = $this->parseLine($line);

        if ($parsed === null) {
            // Log but don't fail entire process for invalid lines
            error_log('Invalid .env line '.($lineNumber + 1).": {$line}");

            return;
        }

        [$name, $value] = $parsed;

        if (!$this->isValidKey($name)) {
            error_log('Invalid .env key on line '.($lineNumber + 1).": {$name}");

            return;
        }

        if ($overwrite || !isset($_ENV[$name])) {
            $_ENV[$name] = $value;

            if ($populateServer) {
                $_SERVER[$name] = $value;
            }

            // Use putenv for compatibility with legacy getenv() calls
            putenv("{$name}={$value}");
        }
    }

    /**
     * Check if a line is a comment.
     *
     * Lines starting with # are considered comments.
     *
     * @param  string  $line  The line to check
     * @return bool True if line is a comment
     */
    private function isComment(string $line): bool
    {
        return str_starts_with($line, '#');
    }

    /**
     * Parse a single line from the .env file.
     *
     * Handles various formats:
     * - Simple: KEY=value
     * - Quoted: KEY="value" or KEY='value'
     * - With inline comments: KEY=value # comment
     * - Empty values: KEY= or KEY=""
     *
     * @param  string  $line  The line to parse
     * @return array{0: string, 1: string}|null Array of [key, value] or null if invalid
     */
    private function parseLine(string $line): ?array
    {
        $separatorPos = strpos($line, '=');

        if ($separatorPos === false) {
            return null;
        }

        $name = trim(substr($line, 0, $separatorPos));
        $value = trim(substr($line, $separatorPos + 1));
        $value = $this->parseValue($value);

        return [$name, $value];
    }

    /**
     * Parse and clean the value portion of an env line.
     *
     * Handles:
     * - Quoted strings: "value" or 'value'
     * - Inline comments: value # comment
     * - Escaped characters: \"
     * - Empty values
     *
     * @param  string  $value  Raw value from .env line
     * @return string Parsed and cleaned value (always returns string)
     */
    private function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];
        $isQuoted = in_array($firstChar, ['"', "'"]);

        if ($isQuoted) {
            $value = $this->extractQuotedValue($value, $firstChar);
        } else {
            $value = $this->removeInlineComment($value);
        }

        // Reset expansion depth for each new value to track circular references
        $this->expansionDepth = 0;
        $value = $this->expandVariables($value);

        return $value;
    }

    /**
     * Extract value from within quotes.
     *
     * Finds the closing quote and extracts everything in between,
     * handling escaped quotes.
     *
     * @param  string  $value  Full value string including quotes
     * @param  string  $quote  Quote character (either " or ')
     * @return string Extracted value without quotes
     */
    private function extractQuotedValue(string $value, string $quote): string
    {
        $length = strlen($value);
        $result = '';
        $escaped = false;

        // Start from position 1 to skip opening quote
        for ($i = 1; $i < $length; $i++) {
            $char = $value[$i];

            if ($escaped) {
                $result .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === $quote) {
                break;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Remove inline comments from unquoted values.
     *
     * Strips everything after # character in unquoted values.
     *
     * @param  string  $value  Value potentially containing inline comment
     * @return string Value with inline comment removed
     */
    private function removeInlineComment(string $value): string
    {
        $commentPos = strpos($value, '#');

        if ($commentPos !== false) {
            $value = substr($value, 0, $commentPos);
        }

        return trim($value);
    }

    /**
     * Expand variable references in values.
     *
     * Replaces ${VAR_NAME} or $VAR_NAME with the actual value from $_ENV.
     * This allows referencing other environment variables.
     *
     * Protects against circular references by tracking expansion depth.
     *
     * Example: DATABASE_URL=${DB_HOST}:${DB_PORT}/${DB_NAME}
     *
     * @param  string  $value  Value potentially containing variable references
     * @return string Value with variables expanded (always returns string)
     *
     * @throws \RuntimeException If circular reference detected (expansion depth exceeded)
     */
    private function expandVariables(string $value): string
    {
        // Prevent infinite loops from circular variable references
        if ($this->expansionDepth >= self::MAX_EXPANSION_DEPTH) {
            throw new \RuntimeException(
                'Circular reference detected in environment variable expansion'
            );
        }

        $this->expansionDepth++;

        $value = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', function ($matches) {
            $varName = $matches[1];

            return $_ENV[$varName] ?? '';
        }, $value);

        $value = preg_replace_callback('/\$([A-Z0-9_]+)\b/', function ($matches) {
            $varName = $matches[1];

            return $_ENV[$varName] ?? '';
        }, $value);

        $this->expansionDepth--;

        return $value;
    }

    /**
     * Validate environment variable key name.
     *
     * Ensures keys follow PHP variable naming rules:
     * - Start with letter or underscore
     * - Contain only letters, numbers, and underscores
     * - By convention, use UPPERCASE_SNAKE_CASE
     *
     * @param  string  $key  The key to validate
     * @return bool True if key is valid
     */
    private function isValidKey(string $key): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key) === 1;
    }

    /**
     * Get an environment variable with type conversion and optional default.
     *
     * Provides Laravel-style type conversion for common boolean and null values:
     * - 'true', '(true)' → boolean true
     * - 'false', '(false)' → boolean false
     * - 'null', '(null)' → null
     * - 'empty', '(empty)' → empty string ''
     * - Numeric strings → int or float
     * - Everything else → string as-is
     *
     * This approach maintains $_ENV as strings while providing typed access.
     *
     * @param  string  $key  Variable name
     * @param  mixed  $default  Default value if variable doesn't exist
     * @return mixed Variable value with type conversion or default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!isset($_ENV[$key])) {
            return is_callable($default) ? $default() : $default;
        }

        $value = $_ENV[$key];

        return self::convertType($value);
    }

    /**
     * Convert string environment value to appropriate PHP type.
     *
     * Handles Laravel-compatible special values and numeric conversion.
     * This follows the same logic as Laravel's env() helper.
     *
     * @param  string  $value  Raw string value from environment
     * @return mixed Converted value (bool|int|float|string|null)
     */
    private static function convertType(string $value): mixed
    {
        $lower = strtolower($value);

        switch ($lower) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'null':
            case '(null)':
                return null;

            case 'empty':
            case '(empty)':
                return '';
        }

        // Handle quoted strings by removing surrounding quotes
        if (strlen($value) >= 2) {
            $firstChar = $value[0];
            $lastChar = $value[strlen($value) - 1];

            if (($firstChar === '"' && $lastChar === '"') ||
                ($firstChar === "'" && $lastChar === "'")) {
                return substr($value, 1, -1);
            }
        }

        // Convert numeric strings to int or float
        if (is_numeric($value)) {
            if (str_contains($value, '.') || stripos($value, 'e') !== false) {
                return (float) $value;
            }

            return (int) $value;
        }

        return $value;
    }

    /**
     * Check if an environment variable exists.
     *
     * @param  string  $key  Variable name
     * @return bool True if variable exists
     */
    public static function has(string $key): bool
    {
        return isset($_ENV[$key]);
    }

    /**
     * Get all environment variables.
     *
     * Returns raw string values without type conversion.
     * Use get() method for individual typed access.
     *
     * @return array<string, string> All loaded environment variables
     */
    public static function all(): array
    {
        return $_ENV;
    }

    /**
     * Get environment variable as boolean.
     *
     * Provides explicit boolean casting with extended truthy value support.
     * Truthy values: 'true', '(true)', '1', 'yes', 'on'
     * Falsy values: everything else
     *
     * @param  string  $key  Variable name
     * @param  bool  $default  Default value if variable doesn't exist
     * @return bool Boolean value
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        if (!isset($_ENV[$key])) {
            return $default;
        }

        $value = self::get($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            return in_array($lower, ['true', '(true)', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Get environment variable as integer.
     *
     * @param  string  $key  Variable name
     * @param  int  $default  Default value if variable doesn't exist
     * @return int Integer value
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    /**
     * Get environment variable as float.
     *
     * @param  string  $key  Variable name
     * @param  float  $default  Default value if variable doesn't exist
     * @return float Float value
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        $value = self::get($key);

        return $value === null ? $default : (float) $value;
    }
}
