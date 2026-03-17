<?php

declare(strict_types=1);

namespace Framework\Handlers;

use Framework\BaseController;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use Framework\Interfaces\RequestHandlerInterface;
use RuntimeException;

/**
 * Final request handler that dispatches to the given controller action.
 *
 * Dispatcher is responsible for constructing the controller instance;
 * this class only:
 *   - attaches the Request to the controller
 *   - validates and invokes the action
 *   - ensures a Response is returned
 */
final class ControllerRequestHandler implements RequestHandlerInterface
{
    /**
     * @param  BaseController  $controller  A concrete controller instance.
     * @param  string  $action  The action method name to call.
     * @param  array<int,mixed>  $args  Positional arguments for the action (route params, etc.).
     */
    public function __construct(
        private BaseController $controller,
        private string $action,
        private array $args
    ) {}

    public function handle(Request $request): Response
    {
        // Attach the Request to the controller.
        $this->controller->setRequest($request);

        // Validate action name to avoid weird/malicious method calls.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $this->action)) {
            throw new PageNotFoundException('Invalid action name.');
        }

        if (!method_exists($this->controller, $this->action)) {
            $controllerClass = get_class($this->controller);

            throw new PageNotFoundException(
                "Action {$controllerClass}::{$this->action}() not found."
            );
        }

        // Call the controller action with the provided args.
        $response = $this->controller->{$this->action}(...$this->args);

        if (!$response instanceof Response) {
            $controllerClass = get_class($this->controller);

            throw new RuntimeException(
                sprintf(
                    'Controller action %s::%s() must return a %s instance.',
                    $controllerClass,
                    $this->action,
                    Response::class
                )
            );
        }

        return $response;
    }
}
