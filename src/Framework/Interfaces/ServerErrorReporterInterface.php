<?php

declare(strict_types=1);

namespace Framework\Interfaces;

use Framework\Core\Request;

/**
 * Told about every page that answered with a server error, after the error page
 * is ready. Optional: the error handler only calls it when the app binds one.
 */
interface ServerErrorReporterInterface
{
    /**
     * Must never throw: it runs inside the error handler.
     */
    public function report(Request $request, int $status): void;
}
