<?php

declare(strict_types=1);

namespace Framework\Interfaces;

use Framework\Core\Request;
use Framework\Core\Response;

/**
 * Adds the app's security headers to a response that never went through the
 * middleware stack, such as an error page. Optional: the error handler only
 * calls it when the app binds one.
 */
interface ResponseHardenerInterface
{
    /**
     * Must never throw: it runs inside the error handler.
     */
    public function harden(Response $response, Request $request): void;
}
