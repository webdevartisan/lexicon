<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps only presentational declarations in a style attribute.
 *
 * The HTML sanitizer treats a style attribute as one opaque string, so without
 * this a post could still paint a full-screen overlay over the page or pull a
 * background image from another site. Declarations are matched against a list
 * of properties writers actually use, and anything else is dropped.
 */
final class InlineStyleSanitizer implements AttributeSanitizerInterface
{
    private const ALLOWED_PROPERTIES = [
        'background', 'background-color', 'border', 'border-bottom', 'border-collapse', 'border-color',
        'border-left', 'border-radius', 'border-right', 'border-spacing', 'border-style', 'border-top',
        'border-width', 'color', 'display', 'float', 'font-family', 'font-size', 'font-style', 'font-variant',
        'font-weight', 'gap', 'grid-template-columns', 'height', 'letter-spacing', 'line-height',
        'list-style-type', 'margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top',
        'max-height', 'max-width', 'min-height', 'min-width', 'overflow', 'overflow-x', 'overflow-y',
        'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top', 'table-layout',
        'text-align', 'text-decoration', 'text-indent', 'text-transform', 'vertical-align', 'white-space',
        'width', 'word-break',
    ];

    /** Values that reach outside the element, whatever property they are given to. */
    private const FORBIDDEN_IN_VALUE = ['url(', 'expression(', 'javascript:', 'image-set(', '@import', '/*', '\\'];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function getSupportedAttributes(): array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];

        foreach (explode(';', $value) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $declaredValue = trim($parts[1]);

            if ($property === '' || $declaredValue === '' || !$this->isAllowed($property, $declaredValue)) {
                continue;
            }

            $kept[] = $property.': '.$declaredValue;
        }

        return $kept === [] ? null : implode('; ', $kept);
    }

    private function isAllowed(string $property, string $value): bool
    {
        // Custom properties are how themes pass a colour into a block, and they can only
        // be read back by a stylesheet this site ships.
        $known = str_starts_with($property, '--') || in_array($property, self::ALLOWED_PROPERTIES, true);
        if (!$known) {
            return false;
        }

        $haystack = strtolower($value);

        foreach (self::FORBIDDEN_IN_VALUE as $needle) {
            if (str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }
}
