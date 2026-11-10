<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * A module that stores rows per blog must be able to tell whether a blog has any and remove them.
 *
 * Required of every module that declares tables, so deleting a blog or uninstalling
 * a module never leaves rows behind.
 */
interface BlogDataEraser
{
    /**
     * Whether the module holds any rows for this blog.
     */
    public function hasBlogData(int $blogId): bool;

    /**
     * Remove every row the module holds for this blog.
     */
    public function eraseBlog(int $blogId): void;
}
