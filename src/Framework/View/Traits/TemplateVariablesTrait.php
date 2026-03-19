<?php

declare(strict_types=1);

namespace Framework\View\Traits;

trait TemplateVariablesTrait
{
    private function removeComments(string $code): string
    {
        return preg_replace_callback(
            '#{\#\s*(.+?)\s*\#}#',
            function ($m) {
                return '';
            },
            $code
        );
    }

    private function replaceVariables(string $code): string
    {
        return preg_replace_callback(
            '#{{\s*(.+?)\s*}}#',
            fn ($m) => $this->compileVariable($m[1]),
            $code
        );
    }

    private function compileVariable(string $expression): string
    {
        [$variable, $filter] = array_pad(explode('|', $expression), 2, null);
        $variable = $this->dotNotationToArrayAccess(trim($variable));

        $raw = "<?= \$$variable ?? '' ?>";

        return $filter === 'raw'
            ? $raw
            : "<?= e(\$$variable ?? '') ?>";
    }

    private function dotNotationToArrayAccess(string $variable): string
    {
        return preg_replace_callback(
            '/([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)/',
            fn ($m) => $m[1]."['".$m[2]."']",
            $variable
        );
    }
}
