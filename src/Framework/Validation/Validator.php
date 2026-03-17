<?php

declare(strict_types=1);

namespace Framework\Validation;

/**
 * Validation service for structured input validation.
 *
 * decouple validation logic from controllers and models to maintain SRP.
 * This service is stateless and can be injected anywhere via the container.
 *
 * Usage:
 * $validator = new Validator($request->all());
 * $validator->rules([
 *     'email' => ['required', 'email'],
 *     'password' => ['required', 'min:8']
 * ]);
 * if (!$validator->passes()) {
 *     return $this->json(['errors' => $validator->errors()], 422);
 * }
 */
class Validator
{
    protected array $data;

    protected array $rules = [];

    protected array $errors = [];

    protected array $messages = [];

    /**
     * accept the raw input data to validate.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Define validation rules for fields.
     *
     * use a fluent interface so you can chain rules().messages().passes().
     *
     * @param  array<string, array<string>|string>  $rules
     * @return $this
     */
    public function rules(array $rules): self
    {
        foreach ($rules as $field => $fieldRules) {
            $this->rules[$field] = is_string($fieldRules)
                ? explode('|', $fieldRules)
                : $fieldRules;
        }

        return $this;
    }

    /**
     * Set custom error messages.
     *
     * @param  array<string, string>  $messages  Format: 'field.rule' => 'message'
     * @return $this
     */
    public function messages(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Execute validation and return success status.
     *
     * collect all errors before returning so the user gets complete feedback.
     */
    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                if (!$this->applyRule($field, $value, $rule)) {
                    // stop at first failed rule per field
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Inverse of passes() for convenience.
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get only the validated (and present) fields.
     *
     * return only fields that were defined in rules and exist in data.
     * This prevents mass-assignment vulnerabilities.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    /**
     * Apply a single validation rule to a field value.
     *
     * parse parameterized rules (e.g., "min:8") and delegate to specific methods.
     */
    protected function applyRule(string $field, mixed $value, string $rule): bool
    {
        [$ruleName, $parameter] = $this->parseRule($rule);

        $method = 'validate'.snakeToCamel($ruleName);

        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException("Validation rule '{$ruleName}' is not defined.");
        }

        $passes = $this->$method($value, $parameter, $field);

        if (!$passes) {
            $this->addError($field, $ruleName, $parameter);
        }

        return $passes;
    }

    /**
     * Parse rule string into name and parameter.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            return explode(':', $rule, 2);
        }

        return [$rule, null];
    }

    /**
     * Add an error for a field.
     */
    protected function addError(string $field, string $rule, ?string $parameter): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $message = $this->getErrorMessage($field, $rule, $parameter);
        $this->errors[$field][] = $message;
    }

    /**
     * Get custom or default error message.
     */
    protected function getErrorMessage(string $field, string $rule, ?string $parameter): string
    {
        $key = "{$field}.{$rule}";

        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        // humanize field names for better readability (e.g., "first_name" → "First name")
        $fieldName = $this->humanizeFieldName($field);

        // provide sensible defaults for common rules
        return match ($rule) {
            // Basic validation
            'required' => "{$fieldName} is required.",

            // String validation
            'min' => "{$fieldName} must be at least {$parameter} characters.",
            'max' => "{$fieldName} must not exceed {$parameter} characters.",
            'alpha' => "{$fieldName} may only contain letters.",
            'alphaNum', 'alpha_num' => "{$fieldName} may only contain letters and numbers.",

            // Format validation
            'email' => "{$fieldName} must be a valid email address.",
            'url' => "{$fieldName} must be a valid URL.",
            'numeric' => "{$fieldName} must be a number.",
            'integer' => "{$fieldName} must be an integer.",
            'datetime' => "{$fieldName} must be a valid date in format: {$parameter}.",

            // Password validation
            'password' => $this->getPasswordErrorMessage($parameter),
            'regex' => "{$fieldName} format is invalid.",

            // Comparison validation
            'same' => "{$fieldName} must match {$parameter}.",
            'confirmed' => "{$fieldName} confirmation does not match.",
            'in' => "{$fieldName} must be one of: {$parameter}.",

            // Database validation
            'unique' => "{$fieldName} is already taken.",
            'exists' => "The selected {$fieldName} is invalid.",

            // Boolean validation
            'accepted' => "{$fieldName} must be accepted.",
            'boolean' => "{$fieldName} must be true or false.",

            // Date validation
            'date' => "{$fieldName} must be a valid date.",
            'before' => "{$fieldName} must be before {$parameter}.",
            'after' => "{$fieldName} must be after {$parameter}.",

            // Array validation
            'array' => "{$fieldName} must be an array.",

            // File validation
            'file' => "{$fieldName} must be a file.",
            'image' => "{$fieldName} must be an image.",
            'mimes' => "{$fieldName} must be a file of type: {$parameter}.",
            'size' => "{$fieldName} must be less than {$parameter} kilobytes.",

            // Search validation
            'search_query' => "{$fieldName} contains invalid characters.",

            default => "{$fieldName} is invalid.",
        };
    }

    /**
     * Generate user-friendly password error message based on requirements.
     */
    protected function getPasswordErrorMessage(?string $param): string
    {
        $requirements = $this->parsePasswordRequirements($param);
        $messages = [];

        $messages[] = "at least {$requirements['min']} characters";

        if ($requirements['uppercase'] > 0) {
            $messages[] = "{$requirements['uppercase']} uppercase letter".
                ($requirements['uppercase'] > 1 ? 's' : '');
        }

        if ($requirements['lowercase'] > 0) {
            $messages[] = "{$requirements['lowercase']} lowercase letter".
                ($requirements['lowercase'] > 1 ? 's' : '');
        }

        if ($requirements['numbers'] > 0) {
            $messages[] = "{$requirements['numbers']} number".
                ($requirements['numbers'] > 1 ? 's' : '');
        }

        if ($requirements['symbols'] > 0) {
            $messages[] = "{$requirements['symbols']} special character".
                ($requirements['symbols'] > 1 ? 's' : '');
        }

        return 'Password must contain '.$this->joinWithAnd($messages).'.';
    }

    /**
     * Join array items with commas and "and" before the last item.
     */
    protected function joinWithAnd(array $items): string
    {
        if (count($items) === 0) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    /**
     * Convert field name to human-readable format.
     *
     * replace underscores with spaces and capitalize the first word.
     * Examples: "first_name" → "First name", "email" → "Email"
     */
    protected function humanizeFieldName(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }

    // ==================== Validation Rules ====================

    protected function validateRequired(mixed $value, ?string $param, string $field): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null && $value !== '';
    }

    protected function validateEmail(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true; // skip validation for empty unless 'required' is also set
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value)) {
            return true;
        }

        $length = mb_strlen($value);
        $min = (int) $param;

        return $length >= $min;
    }

    protected function validateMax(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value)) {
            return true;
        }

        $length = mb_strlen($value);
        $max = (int) $param;

        return $length <= $max;
    }

    protected function validateConfirmed(mixed $value, ?string $param, string $field): bool
    {
        $confirmationField = $field.'_confirmation';

        if (!isset($this->data[$confirmationField])) {
            return false;
        }

        return $value === $this->data[$confirmationField];
    }

    protected function validateNumeric(mixed $value, ?string $param, string $field): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_numeric($value);
    }

    protected function validateInteger(mixed $value, ?string $param, string $field): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateUrl(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateSame(mixed $value, ?string $param, string $field): bool
    {
        if (!$param || !isset($this->data[$param])) {
            return false;
        }

        return $value === $this->data[$param];
    }

    protected function validateAlpha(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true;
        }

        return ctype_alpha($value);
    }

    protected function validateTitle(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false; // Required, cannot be empty
        }

        // Allow letters, numbers, punctuation, symbols, and spaces
        return preg_match('/^[\p{L}\p{N}\p{P}\p{S} ]+$/u', $value) === 1;
    }

    /**
     * Validate datetime string against a specific format.
     *
     * Parameter format: "d.m.y H:i" (the expected datetime format)
     *
     * We perform strict validation by:
     * - Checking if DateTime::createFromFormat succeeds
     * - Verifying no warnings or errors occurred during parsing
     * - Ensuring the formatted output matches the input (prevents overflow like day 32)
     *
     * Examples:
     * - 'published_at' => 'datetime:d.m.y H:i'
     * - 'scheduled_for' => 'datetime:Y-m-d H:i:s'
     */
    protected function validateDatetime(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true;
        }

        if (!$param) {
            return false;
        }

        $dt = \DateTime::createFromFormat($param, $value);

        if ($dt === false) {
            return false;
        }

        // Get errors - this returns false on successful parsing
        $errors = \DateTime::getLastErrors();

        // Only check warnings/errors if getLastErrors returned an array
        if (is_array($errors)) {
            if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
                return false;
            }
        }

        // ensure reformatted date matches input (catches overflow like day 32)
        if ($dt->format($param) !== $value) {
            return false;
        }

        return true;
    }

    protected function validateTimezone(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true;
        }

        return in_array($value, \DateTimeZone::listIdentifiers(), true);
    }

    protected function validateSlug(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        // Lowercase letters, numbers, hyphens (no consecutive hyphens, no leading/trailing)
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }

    protected function validateAlphaNum(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true;
        }

        return ctype_alnum($value);
    }

    protected function validateIn(mixed $value, ?string $param, string $field): bool
    {
        if (!$param) {
            return false;
        }

        $allowedValues = explode(',', $param);

        return in_array((string) $value, $allowedValues, true);
    }

    /**
     * Validate boolean values.
     *
     * We accept multiple representations of true/false for flexibility
     * with HTML forms (checkboxes send nothing when unchecked).
     *
     * Accepted values:
     * - true, false (boolean)
     * - 1, 0 (integer)
     * - "1", "0", "true", "false", "yes", "no", "on", "off" (string)
     */
    protected function validateBoolean(mixed $value, ?string $param, string $field): bool
    {
        // consider null/empty as valid (checkbox not sent = false)
        if ($value === null || $value === '') {
            return true;
        }

        // accept common boolean representations
        $acceptable = [
            true, false,          // PHP booleans
            1, 0,                 // Integers
            '1', '0',             // String numbers
            'true', 'false',      // String booleans
            'yes', 'no',          // User-friendly
            'on', 'off',          // HTML checkbox values
        ];

        return in_array($value, $acceptable, true);
    }

    /**
     * Validate accepted checkbox/agreement.
     *
     * We require the value to be truthy (for terms acceptance, etc).
     * This is stricter than boolean - it must be explicitly true.
     */
    protected function validateAccepted(mixed $value, ?string $param, string $field): bool
    {
        // accept common "yes" representations
        $acceptable = [true, 1, '1', 'yes', 'on', 'true'];

        return in_array($value, $acceptable, true);
    }

    /**
     * Validate against a regular expression pattern.
     *
     * use this for complex validation patterns like passwords,
     * phone numbers, postal codes, etc.
     */
    protected function validateRegex(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true; // skip validation for empty unless 'required' is also set
        }

        if (!$param) {
            return false;
        }

        // expect parameter to be a regex pattern
        return preg_match($param, $value) === 1;
    }

    /**
     * Validate search query input with minimal restrictions.
     *
     * We apply permissive validation here because security is enforced at
     * the database and output layers (defense-in-depth). This validation
     * only blocks edge cases that could break functionality, not potential attacks.
     *
     * We allow: All printable characters including Unicode for international support.
     * We block: Only null bytes and control characters (no legitimate search use).
     *
     * Security note: This is intentionally permissive. SQL injection prevention
     * happens via parameterized queries, and XSS prevention happens via output escaping.
     *
     * @param  mixed  $value  The search query input
     * @param  string|null  $param  Not used for this rule
     * @param  string  $field  Field name being validated
     * @return bool True if valid search format
     */
    protected function validateSearchQuery(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true; // let 'required' rule handle empty checks
        }

        // only block null bytes (\x00) and control characters that could
        // interfere with string processing or database storage
        // \x00-\x08: Null byte through backspace
        // \x0B, \x0C: Vertical tab, form feed
        // \x0E-\x1F: Shift out through unit separator
        // \x7F: Delete character
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Validate password strength.
     *
     * Parameter format: "min:8,uppercase:1,lowercase:1,numbers:1,symbols:1"
     * Or shorthand: "strong" (equivalent to min:8 with all requirements)
     *
     * check for:
     * - Minimum length
     * - Uppercase letters
     * - Lowercase letters
     * - Numbers
     * - Special symbols
     *
     * Examples:
     * - 'password' => 'password:strong'
     * - 'password' => 'password:min:12,uppercase:1,numbers:1'
     */
    protected function validatePassword(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return true;
        }

        // parse password requirements
        $requirements = $this->parsePasswordRequirements($param);

        // check minimum length
        if (strlen($value) < $requirements['min']) {
            return false;
        }

        // check for uppercase letters
        if ($requirements['uppercase'] > 0) {
            if (preg_match_all('/[A-Z]/', $value) < $requirements['uppercase']) {
                return false;
            }
        }

        // check for lowercase letters
        if ($requirements['lowercase'] > 0) {
            if (preg_match_all('/[a-z]/', $value) < $requirements['lowercase']) {
                return false;
            }
        }

        // check for numbers
        if ($requirements['numbers'] > 0) {
            if (preg_match_all('/[0-9]/', $value) < $requirements['numbers']) {
                return false;
            }
        }

        // check for special symbols
        if ($requirements['symbols'] > 0) {
            if (preg_match_all('/[^a-zA-Z0-9]/', $value) < $requirements['symbols']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that input matches the current authenticated user's password.
     *
     * We verify the provided value against the password hash stored in the database
     * for the currently authenticated user. This is used when users need to confirm
     * their identity before sensitive operations like password changes.
     *
     * Security note: We use constant-time comparison via password_verify() to prevent
     * timing attacks that could reveal information about the password.
     *
     * @param  mixed  $value  User-provided password to verify
     * @param  string|null  $param  Not used for this rule
     * @param  string  $field  Field name being validated
     * @return bool True if password matches, false otherwise
     */
    protected function validateCurrentPassword(mixed $value, ?string $param, string $field): bool
    {
        // check if user is authenticated
        $user = auth()->user();
        if (!$user || !isset($user['password'])) {
            return false;
        }

        // verify using password_verify for constant-time comparison
        return password_verify((string) $value, (string) $user['password']);
    }

    /**
     * Parse password requirements from parameter string.
     *
     * @return array{min: int, uppercase: int, lowercase: int, numbers: int, symbols: int}
     */
    protected function parsePasswordRequirements(?string $param): array
    {
        $defaults = [
            'min' => 8,
            'uppercase' => 0,
            'lowercase' => 0,
            'numbers' => 0,
            'symbols' => 0,
        ];

        if ($param === null || $param === '') {
            return $defaults;
        }

        // handle shorthand presets
        if ($param === 'strong') {
            return [
                'min' => 8,
                'uppercase' => 1,
                'lowercase' => 1,
                'numbers' => 1,
                'symbols' => 1,
            ];
        }

        if ($param === 'medium') {
            return [
                'min' => 8,
                'uppercase' => 1,
                'lowercase' => 1,
                'numbers' => 1,
                'symbols' => 0,
            ];
        }

        if ($param === 'basic') {
            return [
                'min' => 6,
                'uppercase' => 0,
                'lowercase' => 0,
                'numbers' => 0,
                'symbols' => 0,
            ];
        }

        // parse custom format: "min:12,uppercase:1,numbers:2"
        $parts = explode(',', $param);
        $requirements = $defaults;

        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, ':') !== false) {
                [$key, $value] = explode(':', $part, 2);
                $key = trim($key);
                $value = (int) trim($value);

                if (isset($requirements[$key])) {
                    $requirements[$key] = $value;
                }
            }
        }

        return $requirements;
    }
}
