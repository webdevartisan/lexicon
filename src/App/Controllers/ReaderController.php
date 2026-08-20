<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\BlogSubscriberModel;
use App\Models\PostBookmarkModel;
use App\Models\PostVoteModel;
use App\Services\ReaderService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * The reader's own things, on the front rather than in the back office.
 *
 * Five pages under three menu entries: Saved (with Liked), Replies (with Your
 * comments), and Subscriptions. Every list is scoped to the signed-in user in
 * the WHERE clause, so there is no id in any of these URLs to authorize and
 * nothing to leak between accounts.
 *
 * The removals are their own actions rather than the post page's toggles. A
 * toggle asked twice puts the row back, which on a page whose whole purpose is
 * "take this off my list" means a double submit silently re-saves what the
 * reader just removed. The model methods are shared; only the intent differs.
 */
final class ReaderController extends AppController
{
    /**
     * Session key carrying the blog an unsubscribe just removed.
     *
     * Read and cleared by the next render of the subscriptions page, so the
     * undo strip survives the redirect and nothing longer.
     */
    private const UNDO_KEY = 'reader_unsubscribe_undo';

    public function __construct(
        private ReaderService $reader,
        private PostBookmarkModel $bookmarks,
        private PostVoteModel $votes,
        private BlogSubscriberModel $subscribers,
        private BlogModel $blogs
    ) {}

    // ============== The five pages ==============

    /**
     * Posts the reader put aside to come back to.
     */
    public function saved(): Response
    {
        return $this->renderList('public.Reader.saved', 'saved', '/saved', fn (int $u, int $p) => $this->reader->saved($u, $p));
    }

    /**
     * Posts the reader voted up. A tab, not a menu entry: a reaction log is
     * worth keeping but not worth a place in the menu.
     */
    public function liked(): Response
    {
        return $this->renderList('public.Reader.liked', 'liked', '/saved/liked', fn (int $u, int $p) => $this->reader->liked($u, $p));
    }

    /**
     * Replies to things the reader wrote. The inbox, and the reason to return.
     *
     * Reads nothing but the list: marking read is POST /replies/mark-read, so a
     * prefetcher, a crawler, or a browser warming a link can never clear
     * somebody's badge on their behalf.
     */
    public function replies(): Response
    {
        return $this->renderList('public.Reader.replies', 'replies', '/replies', fn (int $u, int $p) => $this->reader->replies($u, $p));
    }

    /**
     * What the reader has written, wherever they wrote it.
     */
    public function myComments(): Response
    {
        return $this->renderList('public.Reader.my-comments', 'my-comments', '/replies/mine', fn (int $u, int $p) => $this->reader->myComments($u, $p));
    }

    /**
     * Blogs the reader follows.
     */
    public function subscriptions(): Response
    {
        $email = $this->viewerEmail();

        $undo = $this->session->get(self::UNDO_KEY);
        $this->session->remove(self::UNDO_KEY);

        return $this->renderList(
            'public.Reader.subscriptions',
            'subscriptions',
            '/subscriptions',
            fn (int $u, int $p) => $this->reader->subscriptions($u, $email, $p),
            ['undo' => $undo]
        );
    }

    // ============== The actions ==============

    /**
     * Take a post off the Saved list.
     */
    public function removeBookmark(string $postId): Response
    {
        $removed = $this->bookmarks->remove($this->viewerId(), (int) $postId);

        return $this->afterAction($removed, '/saved', 'reader.removedFromSaved');
    }

    /**
     * Take a post off the Liked list.
     */
    public function removeVote(string $postId): Response
    {
        $removed = $this->votes->remove($this->viewerId(), (int) $postId);

        return $this->afterAction($removed, '/saved/liked', 'reader.removedFromLiked');
    }

    /**
     * Stop following a blog.
     *
     * Scoped by the same either-identity rule the list uses, so a subscription
     * made before the account existed is just as cancellable as one made after.
     */
    public function unsubscribe(string $blogId): Response
    {
        $blog = $this->blogs->find((int) $blogId);
        $removed = $this->subscribers->deleteForUserAndBlog((int) $blogId, $this->viewerId(), $this->viewerEmail());

        if ($removed && $blog !== null) {
            $this->session->set(self::UNDO_KEY, [
                'blog_id' => (int) $blogId,
                'blog_name' => (string) ($blog['blog_name'] ?? ''),
            ]);
        }

        return $this->afterAction($removed, '/subscriptions', null);
    }

    /**
     * Undo an unsubscribe.
     *
     * Its own endpoint rather than the public subscribe action, which is guest
     * shaped, IP throttled, and flashes wording that belongs on a blog page.
     * The insert is idempotent, so a double-submitted undo cannot collide with
     * uq_subscriber_blog_email.
     */
    public function resubscribe(string $blogId): Response
    {
        $blog = $this->blogs->find((int) $blogId);
        $email = $this->viewerEmail();

        if ($blog === null || $email === '') {
            return $this->afterAction(false, '/subscriptions', null);
        }

        $this->subscribers->subscribe((int) $blogId, $email, $this->viewerId());
        $this->flash('success', chrome_translate('reader.resubscribed', ['blog' => (string) ($blog['blog_name'] ?? '')]));

        return $this->redirect($this->backTo('/subscriptions'));
    }

    /**
     * Mark the replies a page rendered as read.
     *
     * The only writer of read_at on this surface. Ids the viewer does not own
     * are dropped by the query rather than rejected, so a stale tab is a no-op
     * and not an error somebody has to read.
     */
    public function markRepliesRead(): Response
    {
        $ids = $this->request->post['ids'] ?? [];
        $this->reader->markRepliesRead($this->viewerId(), is_array($ids) ? $ids : []);

        if ($this->request->isAjax()) {
            return $this->jsonSuccess(['unread' => $this->reader->unreadReplyCount($this->viewerId())]);
        }

        return $this->redirect($this->backTo('/replies'));
    }

    // ============== Plumbing ==============

    /**
     * Render one reader list.
     *
     * @param  string  $view  Dotted view path
     * @param  string  $surface  Which list this is, for the tabs and the menu
     * @param  string  $basePath  Unlocalised path, for building page links
     * @param  callable(int, int): array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}  $fetch
     * @param  array<string, mixed>  $extra  Additional view data
     */
    private function renderList(string $view, string $surface, string $basePath, callable $fetch, array $extra = []): Response
    {
        $page = $this->currentPage();
        $result = $fetch($this->viewerId(), $page);

        $totalPages = (int) max(1, (int) ceil($result['total'] / max(1, $result['perPage'])));

        // These are private pages behind a login. no-store is the directive
        // doing the work; private is kept as a second line in case something
        // in the path reads only one of them.
        $this->response->addHeader('Cache-Control', 'private, no-store');

        return $this->view($view, $extra + [
            'items' => $result['items'],
            'surface' => $surface,
            'pagination' => [
                'page' => $page,
                'perPage' => $result['perPage'],
                'total' => $result['total'],
                'totalPages' => $totalPages,
                'basePath' => $basePath,
                // A bookmarked ?page=3 that empties out after a few removals is
                // ordinary, so it gets its own message rather than a 404 or the
                // "nothing here yet" copy, which would be a lie.
                'outOfRange' => $result['items'] === [] && $page > 1,
            ],
        ]);
    }

    /**
     * Finish a removal: flash the outcome and go back where it was made.
     *
     * A POST that matched nothing is not an error. A double submit, or a row
     * another tab already removed, is ordinary; and answering with a 404 would
     * confirm that somebody else's row exists. Both outcomes redirect to the
     * same place with the same status, so nothing about the response depends on
     * whether the row belonged to another account or never existed at all.
     *
     * @param  string|null  $successKey  Translation key to flash, or null to stay quiet
     */
    private function afterAction(bool $changed, string $basePath, ?string $successKey): Response
    {
        if (!$changed) {
            $this->flash('info', chrome_translate('reader.nothingRemoved'));
        } elseif ($successKey !== null) {
            $this->flash('success', chrome_translate($successKey));
        }

        return $this->redirect($this->backTo($basePath));
    }

    /**
     * Where an action returns to: the list it was made on, same page.
     *
     * The page comes from a hidden field rather than the Referer, which is
     * routinely stripped, and is re-validated here because it arrives from the
     * form like anything else.
     */
    private function backTo(string $basePath): string
    {
        $page = (string) ($this->request->post['page'] ?? '');
        $suffix = preg_match('/^[1-9]\d*$/', $page) === 1 ? '?page='.$page : '';

        return lurl($basePath).$suffix;
    }

    /**
     * The requested page number.
     *
     * Anything that is not a whole number of at least 1 is a 404: those URLs
     * were never handed out, so they are a typo or a probe, not a stale
     * bookmark that deserves a friendly page.
     *
     * @throws PageNotFoundException
     */
    private function currentPage(): int
    {
        $raw = $this->request->get['page'] ?? null;

        if ($raw === null || $raw === '') {
            return 1;
        }

        if (!is_string($raw) || preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1) {
            throw new PageNotFoundException('Invalid page number.');
        }

        return (int) $raw;
    }

    private function viewerId(): int
    {
        return (int) (auth()->user()['id'] ?? 0);
    }

    private function viewerEmail(): string
    {
        return (string) (auth()->user()['email'] ?? '');
    }
}
