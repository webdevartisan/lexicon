<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\PostModel;
use App\Resources\PostResource;
use App\Services\ExternalMediaGuard;
use App\Services\MediaService;
use App\Services\PostContentSanitizer;
use App\ValueObjects\PostFieldRules;
use Framework\Core\Response;
use InvalidArgumentException;

/**
 * Saves what an author edited on the post page itself: title, text and cover.
 *
 * Everything else about the post (address, tags, category, SEO, status) is
 * left as it is, which is why this does not share the full editor's update.
 */
final class PostInPlaceController extends AppController
{
    public function __construct(
        private PostModel $posts,
        private PostContentSanitizer $sanitizer,
        private ExternalMediaGuard $mediaGuard,
        private MediaService $media
    ) {}

    /**
     * Save the edits and answer with the title, text and cover as stored.
     *
     * @param  string  $id  Post ID
     */
    public function save(string $id): Response
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
            return $this->json(['error' => 'You cannot edit this post.'], 403);
        }

        // Checked even when nothing was typed: an untouched old copy would still undo the newer save.
        if ($post->textChangedSince((string) $this->request->postParam('version', ''))) {
            return $this->json(['error' => PostFieldRules::TEXT_CHANGED_ELSEWHERE, 'conflict' => true], 409);
        }

        $title = trim((string) $this->request->postParam('title', ''));
        $content = (string) $this->request->postParam('content', '');

        $problem = $this->invalidText($title, $content);
        if ($problem === null) {
            $content = $this->sanitizer->clean($content);
            $problem = $this->unpublishableText($post, $content);
        }
        if ($problem !== null) {
            return $this->json(['error' => $problem], 422);
        }

        try {
            $cover = $this->coverChange($post);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        $changes = array_diff_assoc(
            ['title' => $title, 'content' => $content],
            ['title' => $post->title(), 'content' => $post->content()]
        ) + $cover;

        if ($changes !== []) {
            $this->posts->update($post->id(), $changes);

            audit()->log($user['id'], 'post.updated', 'post', $post->id(), ['title' => $title], $this->request->ip());
        }

        return $this->json([
            'title' => $title,
            'content' => $content,
            'cover' => array_key_exists('featured_image', $cover) ? $cover['featured_image'] : $this->currentCover($post),
            'version' => PostResource::versionOf($title, $content),
        ]);
    }

    /**
     * Why the title or text as typed cannot be accepted, or null when they can.
     */
    private function invalidText(string $title, string $content): ?string
    {
        $validator = $this->validator(['title' => $title, 'content' => $content]);
        $validator->rules([
            'title' => PostFieldRules::CORE['title'],
            'content' => PostFieldRules::CORE['content'],
        ]);

        if (!$validator->fails()) {
            return null;
        }

        return isset($validator->errors()['title'])
            ? 'Use 2 to 100 characters for the title.'
            : 'The text is too long to save.';
    }

    /**
     * Why the cleaned text cannot go on this post, or null when it can.
     */
    private function unpublishableText(PostResource $post, string $content): ?string
    {
        if (PostFieldRules::missingText($post->status(), $content)) {
            return 'A live post needs some text.';
        }

        $outside = $this->mediaGuard->newExternalSources($content, $post->content());

        return $outside === [] ? null : $this->mediaGuard->rejectionMessage($outside);
    }

    /**
     * The cover the author picked or removed, as a column change. Empty when the
     * cover was left alone, which the page signals by not sending it at all.
     *
     * @return array{featured_image?: string|null}
     *
     * @throws InvalidArgumentException When the picked image is not in this blog's library
     */
    private function coverChange(PostResource $post): array
    {
        $picked = $this->request->postParam('cover');
        if ($picked === null) {
            return [];
        }

        $picked = trim((string) $picked);
        if ($picked === (string) $this->currentCover($post)) {
            return [];
        }

        return ['featured_image' => $picked === '' ? null : $this->media->usePicked($post->blog(), $picked, 'post_image')];
    }

    private function currentCover(PostResource $post): ?string
    {
        $cover = $post->toArray()['featured_image'] ?? null;

        return $cover === null || $cover === '' ? null : (string) $cover;
    }
}
