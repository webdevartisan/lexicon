<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use InvalidArgumentException;

/**
 * Hands a blog to one of its collaborators, from the dashboard or the control panel.
 */
class BlogOwnershipService
{
    public function __construct(
        private BlogModel $blogs,
        private PublicCacheInvalidator $cacheInvalidator,
    ) {}

    /**
     * @param  int  $actorId  Who performed the transfer, for the audit log
     *
     * @throws InvalidArgumentException If the new owner is not an active collaborator on the blog
     */
    public function transfer(int $blogId, int $currentOwnerId, int $newOwnerId, int $actorId, ?string $ip): void
    {
        if ($this->blogs->storedRoleFor($blogId, $newOwnerId) === null) {
            throw new InvalidArgumentException('The new owner has to be an active collaborator on this blog.');
        }

        $this->blogs->transferOwnership($blogId, $newOwnerId, $currentOwnerId);

        audit()->log(
            $actorId,
            'blog.ownership_transferred',
            'blog',
            $blogId,
            ['from_user_id' => $currentOwnerId, 'to_user_id' => $newOwnerId],
            $ip
        );

        $this->cacheInvalidator->purgeBlogSurfaces();
    }
}
