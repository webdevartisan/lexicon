<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * What a module puts into one named slot of a page.
 */
interface SlotRenderer
{
    /**
     * Whether there is anything to show here, checked before render().
     */
    public function shouldRender(SlotContext $context): bool;

    /**
     * The HTML for the slot. The module escapes its own output.
     */
    public function render(SlotContext $context): string;

    /**
     * Files the page needs when this slot rendered, relative to public/modules/<key>/.
     *
     * A kind with no files can be left out.
     *
     * @return array{css?: list<string>, js?: list<string>}
     */
    public function assets(): array;
}
