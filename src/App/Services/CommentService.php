<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use Framework\Session;

/**
 * Comment creation shared by the public controller and the post-login resume.
 *
 * Guests who try to reply get their typed comment captured in the session,
 * bounce through login/registration, and the reply is posted automatically
 * the moment they authenticate — no retyping, no lost context.
 */
class CommentService
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 2000;

    private const PENDING_KEY = 'pending_comment';

    public function __construct(
        private PostModel $postModel,
        private CommentModel $commentModel,
        private BlogSettingsModel $blogSettings,
        private BlogModel $blogModel,
        private NotificationService $notifications,
        private Session $session,
    ) {}

    /**
     * Validate and store a comment, then notify the blog team.
     *
     * @param  int  $postId  Target post
     * @param  int|null  $parentId  Parent comment for replies, null for top-level
     * @param  string  $content  Raw comment text
     * @param  array<string, mixed>|null  $user  Authenticated user row, null for guests
     * @param  string|null  $ip  Request IP for the audit trail
     * @return array{ok: bool, message: string}
     */
    public function create(int $postId, ?int $parentId, string $content, ?array $user, ?string $ip = null): array
    {
        $content = trim($content);
        $userId = $user ? (int) $user['id'] : null;

        if ($postId <= 0 || $content === '') {
            return ['ok' => false, 'message' => 'Comment content is required.'];
        }

        if ($error = $this->contentLengthError($content)) {
            return ['ok' => false, 'message' => $error];
        }

        $post = $this->postModel->find((string) $postId);

        if (!$post) {
            return ['ok' => false, 'message' => 'Post not found.'];
        }

        if ($post['status'] !== 'published' || $post['visibility'] !== 'public') {
            return ['ok' => false, 'message' => 'Comments are not available for this post.'];
        }

        $parentId = (int) $parentId;

        if ($parentId > 0) {
            // Replies require an account; guests are captured upstream and
            // resumed here after login, so this only rejects direct misuse
            if ($userId === null) {
                return ['ok' => false, 'message' => 'Please log in to reply to comments.'];
            }

            $parent = $this->commentModel->findApprovedParent($parentId, $postId);

            if (!$parent) {
                return ['ok' => false, 'message' => 'The comment you are replying to is unavailable.'];
            }

            // Keep threads one level deep: replying to a reply attaches to its top-level parent
            if (!empty($parent['parent_comment_id'])) {
                $parentId = (int) $parent['parent_comment_id'];
            }
        }

        $isReply = $parentId > 0;
        $autoPublish = $isReply
            ? $this->blogSettings->repliesAutoPublish((int) $post['blog_id'])
            : $this->blogSettings->commentsAutoPublish((int) $post['blog_id']);

        $comment = [
            'post_id' => $postId,
            'user_id' => $userId,
            'parent_comment_id' => $isReply ? $parentId : null,
            'content' => $content,
            'status' => $autoPublish ? 'approved' : 'pending',
        ];

        if (!$this->commentModel->insert($comment)) {
            return ['ok' => false, 'message' => 'Failed to create comment. Please try again.'];
        }

        $commentId = (int) $this->commentModel->getInsertID();

        audit()->log(
            $userId ?? 0,
            'comment.created',
            'comment',
            $commentId,
            [
                'post_id' => $postId,
                'is_guest' => $userId === null,
                'parent_comment_id' => $isReply ? $parentId : null,
            ],
            $ip
        );

        $this->notifyBlogTeam($post, $content, $user, !$autoPublish, $commentId);

        if ($autoPublish) {
            return ['ok' => true, 'message' => $isReply ? 'Reply posted.' : 'Comment posted.'];
        }

        return ['ok' => true, 'message' => 'Thanks! Your comment was submitted and is awaiting moderation.'];
    }

    /**
     * Length validation shared with the guest capture path.
     *
     * @return string|null Error message, or null when the content is fine
     */
    public function contentLengthError(string $content): ?string
    {
        $length = mb_strlen($content);

        if ($length < self::MIN_LENGTH) {
            return 'Your comment is too short.';
        }

        if ($length > self::MAX_LENGTH) {
            return 'Your comment is too long. Maximum '.self::MAX_LENGTH.' characters.';
        }

        return null;
    }

    /**
     * Stash a guest's reply so it survives the login/registration hop.
     *
     * @param  int  $postId  Target post
     * @param  int  $parentId  Comment being replied to
     * @param  string  $content  The typed reply
     * @param  string  $returnPath  Validated local path of the post page
     */
    public function capturePending(int $postId, int $parentId, string $content, string $returnPath): void
    {
        $this->session->set(self::PENDING_KEY, [
            'post_id' => $postId,
            'parent_id' => $parentId,
            'content' => mb_substr(trim($content), 0, self::MAX_LENGTH),
            'return' => $returnPath,
        ]);
    }

    /**
     * Post the captured reply right after authentication.
     *
     * The capture is single-use: it is cleared before posting so a failed
     * resume never replays on the next login.
     *
     * @param  array<string, mixed>|null  $user  The freshly authenticated user
     * @param  string|null  $ip  Request IP for the audit trail
     * @return array{ok: bool, message: string, path: string}|null Null when nothing was pending
     */
    public function resumePending(?array $user, ?string $ip = null): ?array
    {
        $pending = $this->session->get(self::PENDING_KEY);
        $this->session->remove(self::PENDING_KEY);

        if (!is_array($pending) || $user === null) {
            return null;
        }

        $parentId = (int) ($pending['parent_id'] ?? 0);
        $result = $this->create(
            (int) ($pending['post_id'] ?? 0),
            $parentId > 0 ? $parentId : null,
            (string) ($pending['content'] ?? ''),
            $user,
            $ip
        );

        $path = safe_return_to((string) ($pending['return'] ?? '')) ?? '/';
        if ($result['ok'] && $parentId > 0) {
            $path .= '#comment-'.$parentId;
        }

        return ['ok' => $result['ok'], 'message' => $result['message'], 'path' => $path];
    }

    /**
     * Notify the blog owner, active editors, and the post author about a new
     * comment. The commenter is skipped so people don't get notified about
     * their own comments; recipients can opt out via notify_comments.
     *
     * @param  array<string, mixed>  $post  The commented post row
     * @param  string  $content  Raw comment text
     * @param  array<string, mixed>|null  $commenter  Authenticated commenter, null for guests
     * @param  bool  $awaitingModeration  Whether the comment is held for approval
     * @param  int  $commentId  ID of the new comment, used to build a deep link
     */
    private function notifyBlogTeam(
        array $post,
        string $content,
        ?array $commenter,
        bool $awaitingModeration,
        int $commentId
    ): void {
        $blog = $this->blogModel->getBlog((int) $post['blog_id']);
        if (!$blog) {
            return;
        }

        $recipients = [$blog->ownerId(), (int) ($post['author_id'] ?? 0)];
        foreach ($blog->users() as $member) {
            if ((int) ($member['is_active'] ?? 0) === 1
                && $this->blogModel->baseRoleFor((string) ($member['role'] ?? '')) === 'editor') {
                $recipients[] = (int) $member['user_id'];
            }
        }

        $commenterId = $commenter ? (int) $commenter['id'] : null;
        $recipients = array_unique(array_filter($recipients, static fn (int $id): bool => $id > 0 && $id !== $commenterId));

        $payload = [
            'post_id' => (int) $post['id'],
            'comment_id' => $commentId,
            'post_title' => (string) ($post['title'] ?? ''),
            'post_slug' => (string) ($post['slug'] ?? ''),
            'blog_id' => (int) $post['blog_id'],
            'blog_slug' => $blog->slug(),
            'commenter_name' => $commenter
                ? (string) ($commenter['display_name'] ?? $commenter['username'] ?? 'A reader')
                : 'A guest',
            'comment_excerpt' => truncate($content, 140),
            'awaiting_moderation' => $awaitingModeration,
        ];

        foreach ($recipients as $userId) {
            $this->notifications->dispatch($userId, 'comment.created', $payload);
        }
    }
}
