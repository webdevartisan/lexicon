<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * A module that keeps rows per post and wants a copied post to keep them too.
 */
interface PostCopyParticipant
{
    /**
     * Give the new copy the module's rows of the original post. Runs inside the copy's transaction,
     * so it must not open one of its own.
     */
    public function copyPost(int $fromPostId, int $toPostId): void;
}
