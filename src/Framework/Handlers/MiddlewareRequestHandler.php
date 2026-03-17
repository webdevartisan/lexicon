<?php

declare(strict_types=1);

namespace Framework\Handlers;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Executes a stack of middleware and finally delegates to the controller handler.
 *
 * Each call to handle() consumes one middleware and passes itself as the "next"
 * RequestHandlerInterface implementation until the stack is exhausted.
 */
final class MiddlewareRequestHandler implements RequestHandlerInterface
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middlewares;

    public function __construct(
        array $middlewares,
        private ControllerRequestHandler $controllerHandler
    ) {
        $this->middlewares = $middlewares;
    }

    public function handle(Request $request): Response
    {
        /** @var MiddlewareInterface|null $middleware */
        $middleware = array_shift($this->middlewares);

        if ($middleware === null) {
            return $this->controllerHandler->handle($request);
        }

        return $middleware->process($request, $this);
    }
}
