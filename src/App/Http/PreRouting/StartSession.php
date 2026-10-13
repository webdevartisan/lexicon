<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\App;
use Framework\Core\Request;
use Framework\Session;

/**
 * Opens the session once the steps that must work without one have run:
 * maintenance mode and health checks answer even while the session store is
 * down, and static files never get a session.
 */
final class StartSession
{
    public static function handle(Request $request): void
    {
        App::container()->get(Session::class)->start($request);
    }
}
