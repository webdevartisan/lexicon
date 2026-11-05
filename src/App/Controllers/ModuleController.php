<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModuleModel;
use Framework\Exceptions\PageNotFoundException;

/**
 * Base for module controllers. A module's pages exist only on blogs that switched the module on.
 *
 * Module controllers that take their own dependencies pass BlogModuleModel on to this constructor.
 */
abstract class ModuleController extends AppController
{
    public function __construct(protected BlogModuleModel $blogModules)
    {
    }

    /**
     * The key of the module this controller belongs to.
     */
    abstract protected function moduleKey(): string;

    /**
     * Answer 404 when the blog has not switched this module on.
     *
     * @throws PageNotFoundException When the module is off for the blog
     */
    protected function requireActive(int $blogId): void
    {
        if (!in_array($this->moduleKey(), $this->blogModules->activeKeys($blogId), true)) {
            throw new PageNotFoundException('This page is not available on this blog.');
        }
    }
}
