<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * A module whose blog rows travel with the blog's backup and come back on restore.
 */
interface BlogArchiveParticipant
{
    /**
     * The blog's rows as JSON-ready data. Post ids may be kept; restore maps them to the new posts.
     *
     * @return list<array<string, mixed>>
     */
    public function exportBlog(int $blogId): array;

    /**
     * Write rows from a backup into a blog.
     *
     * @param  int  $version  The module version that wrote the backup, so older rows can be read
     * @param  list<mixed>  $rows  Rows as exportBlog() returned them; treat them as untrusted input
     * @param  array<int, int>  $postIdMap  Post id in the backup => id of the restored post; missing means not restored
     * @return int Rows written
     */
    public function importBlog(int $blogId, int $version, array $rows, array $postIdMap): int;
}
