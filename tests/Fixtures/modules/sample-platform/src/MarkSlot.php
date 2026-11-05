<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\SamplePlatform;

use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * Leaves an empty marker at the end of the page, so tests can tell the module rendered.
 */
final class MarkSlot implements SlotRenderer
{
    public function shouldRender(SlotContext $context): bool
    {
        return true;
    }

    public function render(SlotContext $context): string
    {
        return '<span class="sample-platform-mark"></span>';
    }

    public function assets(): array
    {
        return [];
    }
}
