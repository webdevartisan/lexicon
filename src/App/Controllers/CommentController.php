<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CommentService;
use Framework\Core\Response;

/**
 * Public comment submission from the blog front.
 *
 * Creation rules live in CommentService so the post-login resume of a
 * captured guest reply follows exactly the same path as a direct submit.
 */
class CommentController extends AppController
{
    public function __construct(
        private CommentService $comments,
    ) {}

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $postId = (int) ($this->request->post['post_id'] ?? 0);
        $parentId = (int) ($this->request->post['parent_comment_id'] ?? 0);
        $content = trim((string) ($this->request->post['content'] ?? ''));

        $user = auth()->user();

        // Guest replying: keep the typed reply alive through the login hop
        // instead of throwing it away with an error.
        if ($user === null && $parentId > 0) {
            if ($postId <= 0 || $content === '') {
                $this->flash('error', 'Comment content is required.');

                return $this->redirectBack();
            }

            if ($error = $this->comments->contentLengthError($content)) {
                $this->flash('error', $error);

                return $this->redirectBack();
            }

            $returnPath = $this->refererPath();
            $this->comments->capturePending($postId, $parentId, $content, $returnPath);

            $this->flash('info', 'Log in or create an account to post your reply — we saved it for you.');

            return $this->redirect(lurl('/login').'?return_to='.urlencode($returnPath));
        }

        $result = $this->comments->create($postId, $parentId > 0 ? $parentId : null, $content, $user, $this->request->ip());

        $this->flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->redirectBack();
    }

    /**
     * Local path of the page the comment came from, for return-to routing.
     */
    private function refererPath(): string
    {
        $referer = (string) ($this->request->header('Referer') ?? '');
        $base = rtrim(base_url(), '/');

        if ($referer !== '' && $base !== '' && str_starts_with($referer, $base)) {
            $candidate = safe_return_to(substr($referer, strlen($base)));
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return '/';
    }
}
