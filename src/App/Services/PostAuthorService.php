<?php

declare(strict_types=1);

namespace App\Services;

use App\Gate;
use App\Models\BlogModel;
use App\Resources\BlogResource;
use Framework\Exceptions\UnauthorizedException;
use InvalidArgumentException;

/**
 * Decides who a post can be credited to and who may change it.
 */
final class PostAuthorService
{
    public function __construct(private BlogModel $blogs) {}

    /**
     * People who can be credited with a post in this blog: the owner plus every
     * active collaborator allowed to write posts there.
     *
     * @return array<int, string> user id => handle, owner first
     */
    public function candidates(BlogResource $blog): array
    {
        $candidates = [];

        $owner = $this->blogs->getBlogOwner($blog->id());
        if ($owner !== null) {
            $candidates[(int) $owner['id']] = (string) $owner['handle'];
        }

        foreach ($blog->users() as $member) {
            $memberId = (int) $member['user_id'];
            if ($blog->userCan($memberId, 'create_posts')) {
                $candidates[$memberId] = (string) $member['handle'];
            }
        }

        return $candidates;
    }

    /**
     * Work out the author a save should store.
     *
     * An absent or unchanged value keeps the current author. A change needs the
     * assignPostAuthor ability and a real candidate, and is refused loudly otherwise.
     *
     * @param  array<string, mixed>  $user  The person saving
     *
     * @throws UnauthorizedException When the user may not change authors
     * @throws InvalidArgumentException When the requested person cannot write in this blog
     */
    public function resolve(BlogResource $blog, array $user, mixed $requested, int $currentAuthorId): int
    {
        if ($requested === null || $requested === '' || (int) $requested === $currentAuthorId) {
            return $currentAuthorId;
        }

        if (!Gate::allows('assignPostAuthor', $blog, $user)) {
            throw new UnauthorizedException('You are not allowed to change who this post is credited to.');
        }

        $requestedId = filter_var($requested, FILTER_VALIDATE_INT);
        if ($requestedId === false || !array_key_exists($requestedId, $this->candidates($blog))) {
            throw new InvalidArgumentException('Choose an author from this blog\'s team.');
        }

        return $requestedId;
    }
}
