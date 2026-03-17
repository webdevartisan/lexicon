<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Session;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Exceptions\ValidationException;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Catch validation exceptions and redirect with errors.
 * 
 * We intercept ValidationException thrown by controllers during
 * validation failures and automatically redirect back with error
 * messages and preserved input. This keeps controllers clean from
 * explicit validation error handling.
 */
final class HandleValidationExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Session $session
    ) {}

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        try {
            // Delegate to the next handler (controller)
            return $next->handle($request);
            
        } catch (ValidationException $e) {
            // Store validation errors for display in forms
            $this->session->set('_errors', $e->errors());
            
            // Preserve old input for form repopulation (exclude sensitive fields)
            $oldInput = $request->all();
            unset($oldInput['password'], $oldInput['confirm_password'], $oldInput['_token']);
            $this->session->set('_old_input', $oldInput);
            
            // Flash error message
            $flash = $this->session->get('_flash', []);
            if (!isset($flash['error'])) {
                $flash['error'] = [];
            }
            $flash['error'][] = 'Please correct the errors and try again.';
            $this->session->set('_flash', $flash);
            
            // Redirect back to previous page
            $referer = $request->header('referer') ?? '/';
            
            $response = new Response();

            $response->redirect($referer);
            
            return $response;
        }
    }
}
