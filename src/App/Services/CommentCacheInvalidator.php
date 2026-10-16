<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommentModel;
use Framework\Database;

/**
 * Forgets a post's cached comment thread once a comment or vote changes it.
 *
 * Runs after the surrounding transaction commits, so a reader in between
 * cannot rebuild the thread from rows that are about to change.
 */
class CommentCacheInvalidator
{
    /**
     * Clears the keys written by CommentModel::forPostThreaded() (localized=false).
     *
     * Votes count too: the totals ride inside the cached rows and "Top" orders
     * by them, so a stale thread would show both the wrong number and the
     * wrong order.
     */
    public function forgetThread(int $postId): void
    {
        app(Database::class)->afterCommit(function () use ($postId): void {
            // One entry per sort order, so dropping a single key would leave the
            // other view of the same thread serving a stale tree.
            foreach (CommentModel::ALLOWED_SORTS as $sort) {
                fragment()->forget('post-comments:'.$postId.':'.$sort, false);
            }
        });
    }
}
