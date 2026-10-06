<?php

declare(strict_types=1);

namespace App\Mail\Templates;

use RuntimeException;

/**
 * An email template and the data it was given do not fit together.
 *
 * Raised for a placeholder nobody fills, a block or template that no longer
 * exists, or an email with no template set up. The message is written for the
 * admin who will read it in the editor, so it names the email and the part.
 */
final class TemplateDataException extends RuntimeException
{
    public static function missingPlaceholder(string $name, string $where): self
    {
        return new self("{$where} uses {{ {$name} }}, but nothing provides it.");
    }

    public static function noBinding(string $mailable): self
    {
        return new self('No template is set up for '.self::shortName($mailable).'.');
    }

    public static function missingTemplate(string $slug, string $mailable): self
    {
        return new self(self::shortName($mailable)." uses the template '{$slug}', which does not exist.");
    }

    public static function missingComponent(string $slug, string $template): self
    {
        return new self("The template '{$template}' uses the block '{$slug}', which does not exist.");
    }

    public static function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
