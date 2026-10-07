<?php

declare(strict_types=1);

namespace App\Mail\Templates;

use RuntimeException;

/**
 * An email's words and the data it was given do not fit together.
 *
 * Raised for a placeholder nothing fills, a layout that does not exist, or an
 * email with no words in a language. The message is written for the admin who
 * will read it in the control panel, so it names the email and the part.
 */
final class TemplateDataException extends RuntimeException
{
    public static function missingPlaceholder(string $name, string $where): self
    {
        return new self("{$where} uses {{ {$name} }}, but nothing provides it.");
    }

    public static function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
