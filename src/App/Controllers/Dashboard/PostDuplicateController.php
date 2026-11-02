<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\PostModel;
use App\Resources\PostResource;
use App\Services\PostDuplicator;
use App\ValueObjects\PostFieldRules;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\UnauthorizedException;

/**
 * Duplicate a post from the Posts list: offer a free title and address for the
 * copy, then create it as a draft.
 */
final class PostDuplicateController extends AppController
{
    private const MODERATED = 'A moderator hid this post after readers reported it, so it cannot be copied.';

    public function __construct(
        private PostModel $posts,
        private PostDuplicator $duplicator,
    ) {}

    /**
     * The title and address the copy dialog starts with.
     *
     * @param  string  $id  Post ID
     */
    public function suggest(string $id): Response
    {
        $post = $this->copyablePost((int) $id);
        if ($post->status() === 'moderated') {
            return $this->json(['error' => self::MODERATED], 409);
        }

        return $this->json($this->duplicator->suggest($post));
    }

    /**
     * Create the copy and go back to the list it was made from.
     *
     * @param  string  $id  Post ID
     */
    public function store(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->copyablePost((int) $id);
        if ($post->status() === 'moderated') {
            $this->flash('error', self::MODERATED);

            return $this->redirectBack();
        }

        $data = $this->validateOrFail([
            'title' => PostFieldRules::CORE['title'],
            'slug' => PostFieldRules::CORE['slug'],
        ])->validated();

        $user = auth()->user();
        $title = trim((string) $data['title']);
        $copyId = $this->duplicator->duplicate($post, $title, (string) $data['slug'], (int) $user['id']);

        audit()->log(
            $user['id'],
            'post.duplicated',
            'post',
            $copyId,
            ['from_post_id' => $post->id(), 'title' => $title],
            $this->request->ip()
        );

        $this->flash('success', '“'.$title.'” saved as a draft.');

        return $this->redirectBack();
    }

    /**
     * The post, when the user may write in its blog and may read it: anyone who
     * sees every post there, or its own author.
     *
     * @throws PageNotFoundException
     * @throws UnauthorizedException
     */
    private function copyablePost(int $id): PostResource
    {
        $post = $this->posts->findResource($id);
        if ($post === false) {
            throw new PageNotFoundException("Post with ID: '$id' not found.");
        }

        $user = auth()->user();
        Gate::authorize('createPost', $post->blog(), $user);

        if (!Gate::allows('view', $post, $user) && $post->authorId() !== (int) $user['id']) {
            throw new UnauthorizedException('Unauthorized');
        }

        return $post;
    }
}
