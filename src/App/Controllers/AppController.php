<?php

declare(strict_types=1);

namespace App\Controllers;

use Framework\BaseController;
use Framework\Core\Response;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\SessionAwareInterface;
use Framework\Session;
use Framework\Validation\DatabaseValidator;

/**
 * Application-level controller for blog-specific helpers.
 *
 * Provides conveniences that all blog controllers need:
 * - Authentication shortcuts
 * - Flash message handling
 * - Validation helpers
 *
 * avoid putting business logic here; this is purely for reducing
 * boilerplate in feature controllers.
 */
abstract class AppController extends BaseController implements SessionAwareInterface
{
    protected Session $session;

    /**
     * Inject Session service via setter (called by Dispatcher).
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    // ============== Authentication Helpers ==============

    /**
     * Require specific permission or throw 403.
     *
     * @deprecated Use Gate::authorize() instead for proper policy-based authorization
     * @see Gate::authorize()
     *
     * @param  string  $permission  Permission name to check
     *
     * @throws UnauthorizedException If user lacks the permission
     */
    protected function requirePermission(string $permission): void
    {
        if (!auth()->hasPermission($permission)) {
            throw new UnauthorizedException(
                "Permission '{$permission}' is required for this action."
            );
        }
    }

    /**
     * Require at least one of the specified roles.
     *
     * We check if user has ANY of the provided roles (OR logic).
     *
     * @deprecated Use Gate::authorize() with policies or 'role:name' middleware instead
     * @see Gate::authorize()
     *
     * @param  string|string[]  $roles  Role name(s) to check
     *
     * @throws UnauthorizedException If user has none of the specified roles
     */
    protected function requireRole(string|array $roles): void
    {
        $rolesToCheck = is_array($roles) ? $roles : [$roles];

        foreach ($rolesToCheck as $role) {
            if (auth()->hasRole($role)) {
                return; // One match is enough
            }
        }

        throw new UnauthorizedException('You do not have permission to access this resource.');
    }

    // ============== Flash Message Helpers ==============

    /**
     * Store a flash message for the next request.
     *
     * We use the Session service to ensure proper session handling.
     *
     * @param  string  $type  Message type: 'success', 'error', 'warning', 'info'
     * @param  string  $message  The message content
     */
    protected function flash(string $type, string $message): void
    {
        $messages = $this->session->get('_flash', []);

        if (!isset($messages[$type])) {
            $messages[$type] = [];
        }

        $messages[$type][] = $message;
        $this->session->set('_flash', $messages);
    }

    /**
     * Get all flash messages and clear them.
     *
     * @return array<string, array<string>>
     */
    protected function getFlashMessages(): array
    {
        $messages = $this->session->get('_flash', []);
        $this->session->remove('_flash');

        return $messages;
    }

    // ============== Validation Helpers ==============

    /**
     * Validate request and return to previous page with errors on failure.
     *
     * @param  array<string,string|array<string>>  $rules
     * @param  array<string,string>  $messages  Custom error messages
     * @return DatabaseValidator|Response Validator instance if passes, Response if fails
     */
    protected function validateOrFail(array $rules, array $messages = []): DatabaseValidator|Response
    {
        $validator = $this->validate($rules, $messages);

        if ($validator->fails()) {
            // Just throw - middleware will catch and redirect
            throw new \Framework\Exceptions\ValidationException($validator);
        }

        return $validator;
    }

    /**
     * Redirect back to previous page with old input preserved.
     */
    protected function redirectBack(): Response
    {
        // Preserve old input for form repopulation
        $this->session->set('_old_input', $this->request->all());

        // Redirect back to previous page
        $referer = $this->request->header('Referer') ?? '/';

        // Validate referer is from our domain
        if ($referer && !str_starts_with($referer, base_url())) {
            $referer = '/';
        }

        // Return Response instead of sending and exiting
        return $this->redirect($referer);
    }

    // ============== Error Response Helpers ==============

    /**
     * Render 404 Not Found page.
     *
     * @param  string  $message  Error message to display
     */
    protected function notFound(string $message = 'Page not found'): Response
    {
        $this->response->setStatusCode(404);

        return $this->view('errors.404', ['message' => $message]);
    }

    /**
     * Render 403 Forbidden page.
     *
     * @param  string  $message  Error message to display
     */
    protected function forbidden(string $message = 'Access denied'): Response
    {
        $this->response->setStatusCode(403);

        return $this->view('errors/403', ['message' => $message]);
    }

    // ============== Domain-Specific Helpers ==============

    /**
     * Transform social links array to flat key-value pairs for form display.
     *
     * We keep this in AppController as a shared utility for ProfileController
     * and AuthorController. Extract to SocialLinkHelper if more controllers need it.
     *
     * We convert database format [['network' => 'twitter', 'url' => '...']]
     * to form input format ['twitter' => '...', 'github' => '...'].
     *
     * @param  array<int, array{network: string, url: string}>  $links  Social links from database
     * @return array<string, string> Flat array keyed by network name
     */
    protected function linksToFlatInputs(array $links): array
    {
        $out = [
            'website' => '',
            'twitter' => '',
            'instagram' => '',
            'linkedin' => '',
            'github' => '',
        ];

        foreach ($links as $row) {
            $network = $row['network'] ?? '';
            if (isset($out[$network])) {
                $out[$network] = $row['url'] ?? '';
            }
        }

        return $out;
    }
}
