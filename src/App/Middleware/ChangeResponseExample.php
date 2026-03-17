<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

class ChangeResponseExample implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $next): Response
    {

        $response = $next->handle($request);

        $response->setBody($response->getBody().' Modified in middleware');

        return $response;
    }
}
