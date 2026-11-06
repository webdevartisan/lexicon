<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;
use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * Builds a slot renderer the first time a page needs it.
 *
 * Modules boot on every request while routes load, so the real renderer and its
 * services are only built on pages of blogs that switched the journey map on.
 */
final class DeferredSlot implements SlotRenderer
{
    private ?SlotRenderer $slot = null;

    /**
     * @param Closure(): SlotRenderer $make
     */
    public function __construct(private Closure $make)
    {
    }

    /**
     * Ask the real slot, building it on first use.
     */
    public function shouldRender(SlotContext $context): bool
    {
        return $this->slot()->shouldRender($context);
    }

    /**
     * Draw the real slot.
     */
    public function render(SlotContext $context): string
    {
        return $this->slot()->render($context);
    }

    /**
     * The real slot's files.
     */
    public function assets(): array
    {
        return $this->slot()->assets();
    }

    /**
     * The real slot, built once per request.
     */
    private function slot(): SlotRenderer
    {
        return $this->slot ??= ($this->make)();
    }
}
