<?php

declare(strict_types=1);

namespace Framework\View;

final class RouteContext
{
    private ?string $controllerFqcn = null;

    private ?string $action = null;

    /**
     * We should store the resolved controller/action here so the view resolver can infer names
     * without BaseController knowing any naming conventions.
     */
    public function setControllerFqcn(string $controllerFqcn): void
    {
        $this->controllerFqcn = $controllerFqcn;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function controllerFqcn(): ?string
    {
        return $this->controllerFqcn;
    }

    public function action(): ?string
    {
        return $this->action;
    }
}
