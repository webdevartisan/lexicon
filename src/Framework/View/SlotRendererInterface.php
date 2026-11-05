<?php

declare(strict_types=1);

namespace Framework\View;

/**
 * Fills the named slots that templates mark with a slot tag.
 */
interface SlotRendererInterface
{
    /**
     * The HTML for one slot on the page being rendered.
     *
     * @param array<string, mixed> $vars The template's variables at the slot
     */
    public function render(string $slot, array $vars): string;
}
