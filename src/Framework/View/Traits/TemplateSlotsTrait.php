<?php

declare(strict_types=1);

namespace Framework\View\Traits;

use Framework\View\SlotRendererInterface;

/**
 * Named slots: places in a template where optional modules may add HTML.
 *
 * A template never knows which module fills a slot, or whether any does.
 */
trait TemplateSlotsTrait
{
    private ?SlotRendererInterface $slotRenderer = null;

    public function setSlotRenderer(SlotRendererInterface $renderer): void
    {
        $this->slotRenderer = $renderer;
    }

    /**
     * Called from compiled templates.
     *
     * @param  array<string, mixed>  $vars
     */
    public function renderSlot(string $slot, array $vars): string
    {
        return $this->slotRenderer?->render($slot, $vars) ?? '';
    }

    private function replaceSlots(string $code): string
    {
        // The name pattern allows no quotes or backslashes, so it is safe inside the PHP string below.
        return preg_replace(
            '#\{%\s*slot\s+"(?<name>[a-z][a-z0-9.-]{0,63})"\s*%\}#',
            '<?php echo $this->renderSlot(\'$1\', get_defined_vars()); ?>',
            $code
        );
    }
}
