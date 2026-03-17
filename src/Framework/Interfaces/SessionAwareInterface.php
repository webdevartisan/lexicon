<?php

declare(strict_types=1);

namespace Framework\Interfaces;

use Framework\Session;

/**
 * Interface for controllers that require session access.
 *
 * Controllers implementing this interface will automatically receive
 * the Session service via dependency injection in the Dispatcher.
 */
interface SessionAwareInterface
{
    /**
     * Set the session service.
     *
     * @param Session $session The session service instance
     * @return void
     */
    public function setSession(Session $session): void;
}
