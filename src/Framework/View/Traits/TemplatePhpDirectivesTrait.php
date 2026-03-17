<?php

declare(strict_types=1);

namespace Framework\View\Traits;

trait TemplatePhpDirectivesTrait
{
    private function replacePHP(string $code): string
    {
        return preg_replace_callback(
            '#{%\s*(.+?)\s*%}#',
            fn ($m) => $this->compilePhpDirective(trim($m[1])),
            $code
        );
    }

    private function compilePhpDirective(string $php): string
    {
        if ($this->isSetDirective($php)) {
            return $this->compileSetDirective($php);
        }
        if ($this->isForIn($php)) {
            return $this->compileForIn($php);
        }
        if ($this->isForLoop($php)) {
            return "<?php {$php}: ?>";
        }
        if ($this->isIf($php)) {
            return $this->compileIf($php);
        }
        if ($this->isIfWithModifier($php)) {
            return $this->compileIfWithModifier($php);
        }
        if ($this->isElseIfWithModifier($php)) {
            return $this->compileElseIfWithModifier($php);
        }
        if ($this->isElseIfExpression($php)) {
            return $this->compileElseIfExpression($php);
        }

        return $this->compileSimpleDirective($php);
    }

    // ============================================================
    // SET DIRECTIVE
    // ============================================================

    /**
     * Check if directive is {% set varname = expression %}
     */
    private function isSetDirective(string $php): bool
    {
        return str_starts_with($php, 'set ');
    }

    /**
     * Compile {% set varname = expression %} to PHP assignment.
     *
     * Examples:
     * - {% set blogLabel = t('dashboard.blogSelector.label') %}
     * - {% set userName = user.name %}
     */
    private function compileSetDirective(string $php): string
    {
        // extract variable name and expression
        if (!preg_match('/^set\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/', $php, $m)) {
            throw new \RuntimeException("Invalid set directive: {$php}");
        }

        $varName = $m[1];
        $expression = trim($m[2]);

        // compile the expression to valid PHP
        $compiledExpression = $this->compileSetExpression($expression);

        return "<?php \${$varName} = {$compiledExpression}; ?>";
    }

    /**
     * Compile the right-hand side expression of a set directive.
     *
     * Supported:
     * - Function calls: t('key'), base_url('/path'), csrf_token()
     * - Dot notation: user.name, post.title
     * - String literals: 'hello', "world"
     * - Numbers: 42, 3.14
     * - Booleans: true, false, null
     */
    private function compileSetExpression(string $expression): string
    {
        $expression = trim($expression);

        // Case 1: Function call - t('key'), base_url('/path')
        if (preg_match('/^([a-zA-Z_]\w*)\((.+)\)$/', $expression, $m)) {
            $funcName = $m[1];
            $args = $m[2];

            // check if this function is allowed (security)
            if (isset($this->allowedTemplateFunctions[$funcName])) {
                $meta = $this->allowedTemplateFunctions[$funcName];

                if ($meta['type'] === 'helper_var') {
                    // Helper variable like $t
                    return "\${$funcName}({$args})";
                } else {
                    // Global PHP function
                    return "\\{$funcName}({$args})";
                }
            }

            throw new \RuntimeException("Template function '{$funcName}' is not allowed in set directive.");
        }

        // Case 2: String literal - 'text' or "text"
        if (preg_match('/^(["\']).*\1$/', $expression)) {
            return $expression;
        }

        // Case 3: Number - 42 or 3.14
        if (is_numeric($expression)) {
            return $expression;
        }

        // Case 4: Boolean/null keywords
        if (in_array($expression, ['true', 'false', 'null'], true)) {
            return $expression;
        }

        // Case 5: Variable with dot notation - user.name, post.title
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $expression)) {
            return $this->dotToArrayAccess($expression);
        }

        throw new \RuntimeException("Invalid expression in set directive: {$expression}");
    }

    // ============================================================
    // OTHER METHODS
    // ============================================================

    private function isForIn(string $php): bool
    {
        return preg_match('/^for\s+\w+\s+in\s+\w+$/', $php) === 1;
    }

    private function compileForIn(string $php): string
    {
        preg_match('/^for\s+(\w+)\s+in\s+(\w+)$/', $php, $m);

        return "<?php foreach (\${$m[2]} as \${$m[1]}): ?>";
    }

    private function isForLoop(string $php): bool
    {
        return preg_match('/^(for|foreach)\s*\(.*\)$/', $php) === 1;
    }

    private function isIf(string $php): bool
    {
        return preg_match(
            '/^if\s+([\w\.]+(?:\s*(==|!=|>|<|>=|<=)\s*([\'"][^\'"]+[\'"]|\w+))?)(?:\s+(and|or)\s+([\w\.]+(?:\s*(==|!=|>|<|>=|<=)\s*([\'"][^\'"]+[\'"]|\w+))?))*$/',
            $php
        ) === 1;
    }

    private function compileIf(string $php): string
    {
        // strip leading "if "
        $expr = trim(substr($php, 3));

        // split by logical operators while keeping them
        $parts = preg_split('/\s+(and|or)\s+/', $expr, -1, PREG_SPLIT_DELIM_CAPTURE);

        $compiled = '';
        foreach ($parts as $i => $part) {
            if ($part === 'and' || $part === 'or') {
                $compiled .= " $part ";
                continue;
            }

            // match condition
            if (preg_match('/^([\w\.]+)(?:\s*(==|!=|>|<|>=|<=)\s*([\'"][^\'"]+[\'"]|\w+))?$/', $part, $m)) {
                $var = $this->dotToArrayAccess($m[1]);
                if (!empty($m[2])) {
                    $compiled .= "$var {$m[2]} {$m[3]}";
                } else {
                    $compiled .= '!empty('.$var.')';
                }
            }
        }

        return "<?php if ($compiled): ?>";
    }

    private function isIfWithModifier(string $php): bool
    {
        return preg_match('/^if\s+[\w\.]+\|\w+$/', $php) === 1;
    }

    private function compileIfWithModifier(string $php): string
    {
        preg_match('/^if\s+([\w\.]+)\|(\w+)$/', $php, $m);
        $var = $this->dotToArrayAccess($m[1]);
        $condition = $this->modifierCondition($var, $m[2]);

        return "<?php if ($condition): ?>";
    }

    private function isElseIfWithModifier(string $php): bool
    {
        return preg_match('/^elseif\s+\w+\|\w+$/', $php) === 1;
    }

    private function compileElseIfWithModifier(string $php): string
    {
        preg_match('/^elseif\s+(\w+)\|(\w+)$/', $php, $m);
        $var = '$'.$m[1];
        $condition = $this->modifierCondition($var, $m[2]);

        return "<?php elseif ($condition): ?>";
    }

    private function isElseIfExpression(string $php): bool
    {
        return preg_match('/^elseif\s+.+$/', $php) === 1;
    }

    private function compileElseIfExpression(string $php): string
    {
        preg_match('/^elseif\s+(.+)$/', $php, $m);
        $condition = preg_replace('/\b(\w+)\b/', '\$$1', $m[1]);

        return "<?php elseif ($condition): ?>";
    }

    private function compileSimpleDirective(string $php): string
    {
        return match ($php) {
            'else' => '<?php else: ?>',
            'endif' => '<?php endif; ?>',
            'endfor' => '<?php endforeach; ?>',
            default => "<?php $php ?>",
        };
    }

    private function dotToArrayAccess(string $path): string
    {
        $parts = explode('.', $path);
        $var = '$'.array_shift($parts);
        foreach ($parts as $part) {
            $var .= "['$part']";
        }

        return $var;
    }

    private function modifierCondition(string $var, string $modifier): string
    {
        return match ($modifier) {
            'empty' => "empty($var)",
            'notempty' => "!empty($var)",
            'isset' => "isset($var)",
            'count' => "count($var)",
            'trim' => "trim($var)",
            default => $var,
        };
    }
}
