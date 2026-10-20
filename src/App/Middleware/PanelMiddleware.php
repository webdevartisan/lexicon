<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\LayoutSwitchInterface;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Lets a page open in the site modal. Used via the "panel" alias.
 *
 * When panel-dialog.js asks with X-Panel, the page renders in the bare panel
 * layout and the answer is marked X-Panel, so the script can tell it apart from
 * a redirect that left the panel routes (sign-out, a post link). Any other
 * request gets the full page as usual.
 */
final class PanelMiddleware implements MiddlewareInterface
{
    public function __construct(private LayoutSwitchInterface $layouts) {}

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $asPanel = $request->header('X-Panel') === '1';

        if ($asPanel) {
            $this->layouts->useLayout('panel.lex.php');
        }

        $response = $next->handle($request);
        // The same address answers with a page or a panel, so a browser must not swap them
        $response->addHeader('Vary', 'X-Panel');

        if ($asPanel) {
            $response->addHeader('X-Panel', '1');
        }

        return $response;
    }
}
