<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Exceptions\ValidationException;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Interfaces\TranslatorInterface;
use Framework\Session;

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
        private Session $session,
        private TranslatorInterface $translator
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
            $this->session->set('_old_input', sanitize_old_input($request->all()));

            // Flash error message
            $flash = $this->session->get('_flash', []);
            if (!isset($flash['error'])) {
                $flash['error'] = [];
            }
            $flash['error'][] = $this->translator->translate('common.flash.validationFailed');
            $this->session->set('_flash', $flash);

            $response = new Response();

            $response->redirect(referer_path($request->header('referer')) ?? '/');

            return $response;
        }
    }
}
