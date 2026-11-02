<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\PostModel;
use App\ValueObjects\PostFieldRules;
use Framework\Core\Response;

/**
 * Rename a post from the Posts list without leaving the page.
 *
 * Only the title changes. The address stays, so links already shared keep working.
 */
final class PostRenameController extends AppController
{
    public function __construct(private PostModel $posts) {}

    /**
     * Save a new title and answer with the title as stored.
     *
     * @param  string  $id  Post ID
     */
    public function rename(string $id): Response
    {
        if (!csrf()->isTokenValid($this->request->postParam('_token'))) {
            return $this->json(['error' => 'Your session expired. Reload the page and try again.'], 419);
        }

        $user = auth()->user();
        $post = $this->posts->findResource((int) $id);
        if ($post === false) {
            return $this->json(['error' => 'This post no longer exists.'], 404);
        }
        if (!Gate::allows('update', $post, $user)) {
            return $this->json(['error' => 'You cannot rename this post.'], 403);
        }

        $title = trim((string) $this->request->postParam('title', ''));
        $validator = $this->validator(['title' => $title]);
        $validator->rules(['title' => PostFieldRules::CORE['title']]);
        if ($validator->fails()) {
            return $this->json(['error' => 'Use 2 to 100 characters for the title.'], 422);
        }

        if ($title !== $post->title()) {
            $this->posts->update($post->id(), ['title' => $title]);

            audit()->log(
                $user['id'],
                'post.renamed',
                'post',
                $post->id(),
                ['from' => $post->title(), 'to' => $title],
                $this->request->ip()
            );
        }

        return $this->json(['title' => $title]);
    }
}
