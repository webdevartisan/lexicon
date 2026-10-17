<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use App\Services\ImpersonationService;
use Framework\Core\App;
use Framework\Core\Request;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Exposes the impersonation banner to every template.
 *
 * Runs before routing, not as global middleware, because an unmatched URL
 * throws before any middleware runs and its 404 page would lose the banner.
 * Missing it on one page is the case where an admin acts as someone else
 * without realising it.
 */
final class ShareImpersonationBanner
{
    public static function handle(Request $request): void
    {
        $container = App::container();

        $container->get(TemplateViewerInterface::class)->addGlobals([
            'impersonation' => $container->get(ImpersonationService::class)->banner(),
        ]);
    }
}
