<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\SampleExtra;

use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;
use RuntimeException;

/**
 * Prints a short note under a post. Set $fail to make it throw.
 */
final class NoteSlot implements SlotRenderer
{
    public static bool $fail = false;

    public function shouldRender(SlotContext $context): bool
    {
        return $context->post !== null;
    }

    public function render(SlotContext $context): string
    {
        if (self::$fail) {
            throw new RuntimeException('The note could not be drawn');
        }

        return '<p class="sample-extra-note">A note</p>';
    }

    public function assets(): array
    {
        return ['css' => ['note.css'], 'js' => ['note.js']];
    }
}
