<?php

declare(strict_types=1);

namespace App\Database\Seeders;

/**
 * Carries the ids produced during a seed run between the sub-seeders.
 *
 * Each seeder reads what earlier phases created and records what it produced,
 * so the graph wiring (which user owns which blog, which posts belong to which
 * blog) stays explicit rather than hidden inside cross-seeder queries.
 */
final class SeedContext
{
    /** @var array<int, int> All created user ids */
    public array $userIds = [];

    /**
     * Created blogs with the data later phases need.
     *
     * @var array<int, array{id:int, owner_id:int, workflow:bool, members:array<int,int>}>
     */
    public array $blogs = [];

    /** @var array<int, array<int, int>> Category ids keyed by blog id */
    public array $categoriesByBlog = [];

    /** @var array<int, array<int, int>> Tag ids keyed by blog id */
    public array $tagsByBlog = [];

    /**
     * Published posts, used by the comment phase.
     *
     * @var array<int, array{id:int, blog_id:int, author_id:int}>
     */
    public array $publishedPosts = [];

    /**
     * Draft/in-review post ids for workflow-enabled blogs, keyed by blog id.
     *
     * @var array<int, array<int, int>>
     */
    public array $reviewablePostsByBlog = [];
}
