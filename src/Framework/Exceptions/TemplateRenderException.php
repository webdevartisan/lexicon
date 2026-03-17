<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when rendering a template fails.
 *
 * Carries the template path, the original line number,
 * and an optional snippet of the affected source.
 */
final class TemplateRenderException extends RuntimeException
{
    public function __construct(
        public readonly string $templateFile,
        public readonly int $templateLine,
        public readonly string $templateSource,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Error rendering template '{$templateFile}' on line {$templateLine}: ".($previous?->getMessage() ?? ''),
            0,
            $previous
        );
    }

    /**
     * Return a short snippet around the error line (escaped, with line numbers).
     */
    public function snippet(): string
    {
        $lines = explode("\n", $this->templateSource);

        $keys = [$this->templateLine - 1, $this->templateLine, $this->templateLine + 1];
        $selected = array_intersect_key($lines, array_flip($keys));

        $out = [];

        foreach ($selected as $index => $line) {
            $out[] = $index.': '.htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        }

        return implode("\n", $out);
    }
}
