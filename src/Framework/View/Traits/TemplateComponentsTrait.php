<?php

declare(strict_types=1);

namespace Framework\View\Traits;

/**
 * Trait TemplateComponentsTrait
 *
 * We should keep this trait framework-level and CSS-agnostic:
 * - It compiles component tags into PHP calls.
 * - It dispatches rendering to view files under views/components/.
 * - Styling (Tailwind/classes/variants) belongs in the component view files.
 */
trait TemplateComponentsTrait
{
    /**
     * Replace all {% cmp="..." ... %} tags in the template source.
     *
     * Example:
     * {% cmp="btn" variant="green" label="Save" %}
     *
     * @param  string  $code  Raw template code before eval
     * @return string PHP-ready template code
     */
    private function replaceComponents(string $code): string
    {
        return preg_replace_callback(
            '#\{\%\s*cmp\s*=\s*["\']([^"\']+)["\']\s*([^%]*)\%\}#s',
            [$this, 'compileComponentToPhp'],
            $code
        );
    }

    /**
     * Turn {% cmp="btn" variant="warning" %} into:
     * <?php echo $this->renderComponent('btn', ['variant' => 'warning']); ?>
     *
     * @param  array  $matches  Regex matches
     * @return string PHP code
     */
    private function compileComponentToPhp(array $matches): string
    {
        $componentName = trim((string) ($matches[1] ?? ''));
        $attrsString = trim((string) ($matches[2] ?? ''));

        $attrs = $this->parseComponentAttributes($attrsString);
        $attrsPhp = $this->serializeAttributesPhp($attrs);

        // should always call $this here because TemplateRenderer uses this trait.
        return "<?php echo \$this->renderComponent('{$componentName}', {$attrsPhp}); ?>";
    }

    /**
     * Parse attributes from the tag into an associative array.
     *
     * Supported:
     * - key="value"
     * - key='value'
     * - key=true|false|1|0 (unquoted)
     * - key (boolean true)
     *
     * @return array<string, mixed>
     */
    private function parseComponentAttributes(string $attrsString): array
    {
        $attrsString = trim($attrsString);
        if ($attrsString === '') {
            return [];
        }

        $attrs = [];

        // 1) Quoted values: key="..." or key='...'
        preg_match_all(
            '/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(["\'])(.*?)\2/u',
            $attrsString,
            $quotedMatches,
            PREG_SET_ORDER
        );

        foreach ($quotedMatches as $m) {
            $attrs[(string) $m[1]] = (string) $m[3];
        }

        // Remove quoted pairs so we can parse remaining tokens (bools / flags).
        $attrsStringNoQuoted = preg_replace(
            '/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(["\'])(.*?)\2/u',
            ' ',
            $attrsString
        ) ?? $attrsString;

        // 2) Unquoted bool-ish: key=true|false|1|0
        preg_match_all(
            '/\b([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(true|false|1|0)\b/i',
            $attrsStringNoQuoted,
            $boolMatches,
            PREG_SET_ORDER
        );

        foreach ($boolMatches as $m) {
            $key = (string) $m[1];
            $val = strtolower((string) $m[2]);
            $attrs[$key] = in_array($val, ['true', '1'], true);
        }

        // Remove bool pairs so we can parse remaining standalone flags.
        $attrsStringNoPairs = preg_replace(
            '/\b([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(true|false|1|0)\b/i',
            ' ',
            $attrsStringNoQuoted
        ) ?? $attrsStringNoQuoted;

        // 3) Standalone flags: "disabled" => true
        preg_match_all(
            '/\b([a-zA-Z][a-zA-Z0-9_-]*)\b/u',
            $attrsStringNoPairs,
            $flagMatches
        );

        foreach (($flagMatches[1] ?? []) as $flag) {
            $flag = (string) $flag;
            if (!array_key_exists($flag, $attrs)) {
                $attrs[$flag] = true;
            }
        }

        return $attrs;
    }

    /**
     * Convert attributes array into PHP array syntax for eval stage.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function serializeAttributesPhp(array $attrs): string
    {
        if ($attrs === []) {
            return '[]';
        }

        $pairs = [];

        foreach ($attrs as $key => $value) {
            $safeKey = str_replace("'", "\\'", (string) $key);

            if (is_bool($value)) {
                $pairs[] = "'".$safeKey."' => ".($value ? 'true' : 'false');
                continue;
            }

            $pairs[] = "'".$safeKey."' => ".$this->serializeAttributeValuePhp($value);
        }

        return '['.implode(', ', $pairs).']';
    }

    /**
     * Serialize a single attribute value into valid PHP code.
     *
     * We support:
     * - "{$var}"                       -> ($var ?? '')
     * - "/profile/{$username}"         -> ('/profile/' . ($username ?? ''))
     * - "Hello {$name}, id={$id}"      -> ('Hello ' . ($name ?? '') . ', id=' . ($id ?? ''))
     */
    private function serializeAttributeValuePhp(mixed $value): string
    {
        $value = (string) $value;

        // keep the fast-path for the simple case "{$var}".
        $runtimeVar = $this->phpVariableFromPlaceholder($value);
        if ($runtimeVar !== null) {
            // use "?? ''" so missing variables do not raise notices and components get a string.
            return '('.$runtimeVar." ?? '')";
        }

        $tokens = $this->tokenizeAttributePlaceholders($value);

        // If there are no placeholders, we return a plain quoted string like before.
        $hasVar = false;
        foreach ($tokens as $t) {
            if ($t['type'] === 'var') {
                $hasVar = true;
                break;
            }
        }

        if (!$hasVar) {
            // should escape single quotes to keep PHP string literal valid.
            $safeValue = str_replace("'", "\\'", $value);

            return "'".$safeValue."'";
        }

        $parts = [];
        foreach ($tokens as $t) {
            if ($t['type'] === 'text') {
                if ($t['value'] === '') {
                    continue;
                }

                // should escape single quotes to keep PHP string literal valid.
                $safeText = str_replace("'", "\\'", $t['value']);
                $parts[] = "'".$safeText."'";
                continue;
            }

            // treat missing vars as empty strings to avoid notices.
            $parts[] = '('.$t['value']." ?? '')";
        }

        // wrap in parentheses so operator precedence is always correct inside arrays/calls.
        return '('.implode(' . ', $parts).')';
    }

    /**
     * Tokenize a string into text/var parts using "{$name}" placeholders.
     *
     * We only turn placeholders into variables if they pass phpVariableFromPlaceholder()
     * (which also enforces our allowed-variable policy).
     *
     * @return array<int, array{type: 'text'|'var', value: string}>
     */
    private function tokenizeAttributePlaceholders(string $value): array
    {
        // capture placeholders so preg_split returns them as tokens too.
        $chunks = preg_split(
            '/(\{\$[A-Za-z_][A-Za-z0-9_]*\})/',
            $value,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if (!is_array($chunks) || $chunks === []) {
            return [['type' => 'text', 'value' => $value]];
        }

        $tokens = [];
        foreach ($chunks as $chunk) {
            $chunk = (string) $chunk;

            $runtimeVar = $this->phpVariableFromPlaceholder($chunk);
            if ($runtimeVar !== null) {
                // only allow safe variables to become runtime values.
                $tokens[] = ['type' => 'var', 'value' => $runtimeVar];
                continue;
            }

            // keep non-matching chunks as plain text.
            $tokens[] = ['type' => 'text', 'value' => $chunk];
        }

        return $tokens;
    }

    /**
     * Convert a "{$name}" placeholder into a safe PHP variable like "$name".
     */
    private function phpVariableFromPlaceholder(string $value): ?string
    {
        if (!preg_match('/^\{\$([A-Za-z_][A-Za-z0-9_]*)\}$/', $value, $m)) {
            return null;
        }

        $var = '$'.$m[1];

        if (!$this->isAllowedRuntimeVariable($var)) {
            return null;
        }

        return $var;
    }

    /**
     * Decide which runtime variables are allowed in component attributes.
     *
     * We should block superglobals and $GLOBALS because allowing them inside templates makes data leaks easier.
     */
    private function isAllowedRuntimeVariable(string $var): bool
    {
        return !in_array($var, [
            '$GLOBALS',
            '$_GET',
            '$_POST',
            '$_COOKIE',
            '$_REQUEST',
            '$_SERVER',
            '$_FILES',
            '$_ENV',
            '$_SESSION',
        ], true);
    }

    /**
     * Runtime: Render component by name and attributes.
     *
     * This method assumes you have a view file at:
     * - views/components/{name}.php
     *
     * @param  array<string, mixed>  $attrs
     */
    public function renderComponent(string $name, array $attrs = []): string
    {
        $name = strtolower(trim($name));

        // should block path traversal and weird names early.
        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            return $this->renderUnknownComponent($name);
        }

        // intentionally do NOT htmlspecialchars() here to avoid double-escaping.
        // Component view files should escape output using $this->e(...).
        try {
            return $this->render('components/'.$name.'.lex.php', $attrs);
        } catch (\Throwable $e) {
            // should log this so missing components are visible in production logs.
            error_log("Component render failed: '{$name}' (".$e->getMessage().')');

            return $this->renderUnknownComponent($name);
        }
    }

    /**
     * Escape helper for component view files.
     */
    protected function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Whitelist helper for component view files (variants, sizes, etc.).
     *
     * Example in views/components/btn.php:
     * $variant = $this->sanitizeAttr($variant ?? 'slate', ['slate','green','red']);
     *
     * @param  string[]  $allowed
     */
    protected function sanitizeAttr(mixed $value, array $allowed, string $default = ''): string
    {
        $v = strtolower(trim((string) $value));

        if ($default === '') {
            $default = $allowed[0] ?? '';
        }

        return in_array($v, $allowed, true) ? $v : $default;
    }

    /**
     * Unknown component fallback.
     */
    private function renderUnknownComponent(string $name): string
    {
        return '<!-- Unknown component: '.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').' -->';
    }
}
