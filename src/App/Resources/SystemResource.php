<?php

declare(strict_types=1);

namespace App\Resources;

/**
 * Marker resource for system-level abilities.
 *
 * System actions (cache management, diagnostics, maintenance) have no
 * database record to wrap, so policies are resolved from the class name:
 *
 *     Gate::authorize('manageCache', SystemResource::class, $user);
 */
class SystemResource
{
}
