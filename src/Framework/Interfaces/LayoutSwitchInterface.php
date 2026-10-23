<?php

declare(strict_types=1);

namespace Framework\Interfaces;

/**
 * Lets a request frame its page in a different layout than the one the view extends.
 */
interface LayoutSwitchInterface
{
    /**
     * Render every view that extends a layout inside this one instead.
     *
     * @param  string  $layout  Layout file name under views/layouts, e.g. panel.lex.php
     */
    public function useLayout(string $layout): void;
}
