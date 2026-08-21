<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\CommentModel;
use App\Models\CommentVoteModel;
use App\Models\PostBookmarkModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Models\PostVoteModel;
use App\Models\TagModel;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Services\CommentRemovalService;
use App\Services\ContentLocaleResolver;
use App\Services\HeadI18nBuilder;
use App\Services\LocaleState;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class BlogController extends AppController
{
    public function __construct(
        private BlogModel $model,
        private UserModel $userModel,
        private PostModel $postModel,
        private CategoryModel $categoryModel,
        private BlogSettingsModel $settings,
        private TagModel $tagModel,
        private PostVoteModel $postVoteModel,
        private PostBookmarkModel $postBookmarkModel,
        private PostTranslationModel $translations,
        private UserProfileModel $profiles,
        private CommentModel $commentModel,
        private CommentVoteModel $commentVotes,
        private CommentRemovalService $commentRemoval,
        private ContentLocaleResolver $localeResolver,
        private HeadI18nBuilder $headI18n
    ) {}

    /**
     * Swap in translated title/content/excerpt for the viewer's locale on
     * blogs with localized posts enabled. Posts without a translation (and
     * the blog's default locale) pass through unchanged.
     *
     * @param  array<int, array<string, mixed>>  $posts
     * @param  array<string, mixed>  $settings  Blog settings row
     * @return array<int, array<string, mixed>>
     */
    private function localizePosts(array $posts, array $settings): array
    {
        if (empty($settings['translations_enabled']) || $posts === []) {
            return $posts;
        }

        $viewerLocale = locale();
        if ($viewerLocale === (string) ($settings['default_locale'] ?? 'en')) {
            return $posts;
        }

        return $this->translations->overlay($posts, $viewerLocale);
    }

    /**
     * Explore page: a blog directory first, latest posts second.
     *
     * Two tabs share one search box. The featured section shows only blogs
     * an administrator flagged, so nobody can spam their way onto it.
     */
    public function index(): Response
    {
        $tab = $this->request->get['tab'] ?? 'blogs';
        if (!in_array($tab, ['blogs', 'posts'], true)) {
            $tab = 'blogs';
        }

        $searchQuery = trim($this->request->get['q'] ?? '');
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        if ($tab === 'posts') {
            $postsData = $searchQuery !== ''
                ? $this->postModel->searchPublishedPosts($searchQuery, $page, 8)
                : $this->postModel->getRecentPublishedWithPagination($page, 8);

            $items = $postsData['data'];
            $pagination = [
                'totalPages' => $postsData['totalPages'],
                'currentPage' => $postsData['currentPage'],
                'total' => $postsData['totalPosts'],
            ];
        } else {
            $blogsData = $this->model->getDirectoryWithPagination($page, 12, $searchQuery);

            $items = $blogsData['data'];
            $pagination = [
                'totalPages' => $blogsData['totalPages'],
                'currentPage' => $blogsData['currentPage'],
                'total' => $blogsData['totalBlogs'],
            ];
        }

        return $this->view([
            'tab' => $tab,
            'items' => $items,
            'pagination' => $pagination,
            'searchQuery' => $searchQuery,
            'featuredCreators' => $this->model->getFeaturedCreators(),
        ]);
    }

    public function showBlog(string $blogSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);
        if ($blogId === 0) {
            throw new PageNotFoundException('Blog not found.', 404);
        }

        if ($redirect = $this->blogLocaleRedirect($ctx)) {
            return $redirect;
        }

        $featured = $this->postModel->findFeaturedByBlogId($blogId);
        $landingData = $this->postModel->findPublishedByBlogIdWithPagination($blogId, 1, 7);
        $posts = $landingData['data'];

        // Put the chosen headline first without duplicating it in the grid below.
        if ($featured !== null) {
            $filtered = array_filter(
                $posts,
                static fn (array $p): bool => (int) $p['id'] !== (int) $featured['id']
            );

            $posts = array_values($filtered);
            array_unshift($posts, $featured);
        }

        // Optional ?category= filter so a filtered grid is bookmarkable and reload-safe.
        // The headline stays put; only the grid below reacts.
        $activeCategory = trim((string) ($this->request->get['category'] ?? ''));
        if ($activeCategory !== '') {
            $category = $this->categoryModel->findBySlugInBlog($blogId, $activeCategory);
            if ($category) {
                $headline = $posts[0] ?? null;
                $headlineId = isset($headline['id']) ? (int) $headline['id'] : null;
                $grid = $this->postModel->findPublishedByBlogAndCategory($blogId, (int) $category['id'], 6, $headlineId);
                $posts = $headline !== null ? array_merge([$headline], $grid) : $grid;
            } else {
                $activeCategory = '';
            }
        }

        return $this->view('Blogs/show.lex.php', $ctx + [
            'posts' => $this->localizePosts($this->enrichCardPosts($posts), $ctx['settings']),
            'categories' => $this->categoryModel->getPublishedByBlogId($blogId),
            'totalPosts' => $landingData['totalPosts'],
            'activeCategory' => $activeCategory,
        ]);
    }

    /**
     * AJAX endpoint that returns the landing page card grid for a category
     * or the most recent posts when the category is “All”.
     *
     * Produces only the card HTML fragment, rendered through the active theme
     * so each theme preserves its own card markup. The headline post is always
     * excluded, following the same rule as showBlog, ensuring it never appears
     * twice in the grid.
     */
    public function indexFeed(string $blogSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);
        if ($blogId === 0) {
            throw new PageNotFoundException('Blog not found.', 404);
        }

        // The headline uses the featured post when available, otherwise it uses the
        // newest post, matching showBlog so the “All” category behaves consistently.
        $featured = $this->postModel->findFeaturedByBlogId($blogId);
        if ($featured !== null) {
            $headlineId = (int) $featured['id'];
        } else {
            $newest = $this->postModel->findPublishedByBlogAndCategory($blogId, null, 1);
            $headlineId = isset($newest[0]['id']) ? (int) $newest[0]['id'] : null;
        }

        $categoryId = null;
        $slug = trim((string) ($this->request->get['category'] ?? ''));
        if ($slug !== '') {
            $category = $this->categoryModel->findBySlugInBlog($blogId, $slug);
            if (!$category) {
                throw new PageNotFoundException('Category not found.', 404);
            }
            $categoryId = (int) $category['id'];
        }

        $cards = $this->postModel->findPublishedByBlogAndCategory($blogId, $categoryId, 6, $headlineId);

        return $this->view('Blogs/_index_cards.lex.php', [
            'cards' => $this->localizePosts($this->enrichCardPosts($cards), $ctx['settings']),
            'blog' => $ctx['blog'],
            'user' => $ctx['user'],
        ]);
    }

    /**
     * Attach display taxonomy (category name + slug, tag name/slug pairs) to a
     * list of post rows, so the card markup can render it. Shared by the landing
     * and the AJAX feed.
     *
     * @param  array<int, array<string, mixed>>  $posts
     * @return array<int, array<string, mixed>>
     */
    private function enrichCardPosts(array $posts): array
    {
        foreach ($posts as &$post) {
            $category = !empty($post['category_id'])
                ? $this->categoryModel->findById((int) $post['category_id'])
                : null;
            $post['category'] = $category['name'] ?? null;
            $post['category_slug'] = $category['slug'] ?? null;

            $post['tags'] = array_map(
                static fn (array $t): array => ['name' => $t['name'], 'slug' => $t['slug']],
                $this->postModel->tags((int) $post['id'])
            );
        }
        unset($post);

        return $posts;
    }

    public function archiveBlog(string $blogSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);

        if ($blogId === 0) {
            throw new PageNotFoundException('Blog not found.', 404);
        }

        if ($redirect = $this->blogLocaleRedirect($ctx)) {
            return $redirect;
        }

        $page = max(1, (int) ($this->request->get['page'] ?? 1));
        $postsData = $this->postModel->findPublishedByBlogIdWithPagination($blogId, $page, 12);

        return $this->view('Blogs/archive.lex.php', $ctx + [
            'posts' => $this->localizePosts($postsData['data'], $ctx['settings']),
            'pagination' => [
                'totalPages' => $postsData['totalPages'],
                'currentPage' => $postsData['currentPage'],
                'perPage' => $postsData['perPage'],
                'totalPosts' => $postsData['totalPosts'],
            ],
        ]);
    }

    /**
     * Published posts in a blog filed under one category.
     */
    public function showCategory(string $blogSlug, string $categorySlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);

        if ($redirect = $this->blogLocaleRedirect($ctx)) {
            return $redirect;
        }

        $category = $this->categoryModel->findBySlugInBlog($blogId, $categorySlug);
        if (!$category) {
            throw new PageNotFoundException('Category not found.', 404);
        }

        $posts = $this->categoryModel->posts((int) $category['id']);
        $name = e($category['name']);

        return $this->view('Blogs/archive.lex.php', $ctx + [
            'posts' => $this->localizePosts($posts, $ctx['settings']),
            'pagination' => $this->taxonomyPagination(count($posts)),
            'archiveKicker' => 'Category &mdash; '.$name,
            'archiveTitle' => $name,
            'archiveDek' => 'Posts filed under <em>'.$name.'</em>.',
        ]);
    }

    /**
     * Published posts in a blog carrying one tag.
     */
    public function showTag(string $blogSlug, string $tagSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);

        if ($redirect = $this->blogLocaleRedirect($ctx)) {
            return $redirect;
        }

        $tag = $this->tagModel->findBySlugInBlog($blogId, $tagSlug);
        if (!$tag) {
            throw new PageNotFoundException('Tag not found.', 404);
        }

        $posts = $this->tagModel->posts((int) $tag['id']);
        $name = e($tag['name']);

        return $this->view('Blogs/archive.lex.php', $ctx + [
            'posts' => $this->localizePosts($posts, $ctx['settings']),
            'pagination' => $this->taxonomyPagination(count($posts)),
            'archiveKicker' => 'Tagged &mdash; '.$name,
            'archiveTitle' => '#'.$name,
            'archiveDek' => 'Posts tagged <em>'.$name.'</em>.',
        ]);
    }

    /**
     * Single-page pagination shape for taxonomy listings (no paging for now).
     *
     * @return array{totalPages:int,currentPage:int,perPage:int,totalPosts:int}
     */
    private function taxonomyPagination(int $count): array
    {
        return [
            'totalPages' => 1,
            'currentPage' => 1,
            'perPage' => max(1, $count),
            'totalPosts' => $count,
        ];
    }

    public function showBlogPost(string $blogSlug, string $postSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);

        // Post. Scoped to the blog because slugs are only unique per blog, and
        // because a blog serves every member's posts, not just the owner's.
        $post = $this->postModel->findBySlugAndBlogId($postSlug, (int) $ctx['blog']['id']);
        if (!$post) {
            throw new PageNotFoundException('Post not be found', 404);
        }

        $canPreview = auth()->check()
            && $this->model->userCanAccessBlog((int) auth()->user()['id'], (int) $ctx['blog']['id']);

        if (!$canPreview && $post['status'] !== 'published') {
            throw new PageNotFoundException('Post not be found', 404);
        }

        // A post's locale set is narrower than its blog's: the blog may carry
        // Greek posts while this particular one has never been translated.
        $postDefault = (string) ($ctx['settings']['default_locale'] ?? 'en');
        $postLocales = empty($ctx['settings']['translations_enabled'])
            ? []
            : $this->translations->localesForPost((int) $post['id']);

        $postLocaleSet = array_values(array_unique(array_merge([$postDefault], $postLocales)));
        LocaleState::setLocaleSet($postLocaleSet);
        $this->refreshHeadI18n();

        if ($redirect = $this->localeRedirect($postLocaleSet, $postDefault)) {
            return $redirect;
        }

        // Swap in the viewer-locale translation before any derived fields (meta, excerpt) are built.
        $post = $this->localizePosts([$post], $ctx['settings'])[0];

        // Normalize timestamps
        $rawTs = $post['published_at'] ?? $post['created_at'] ?? gmdate('Y-m-d H:i:s');
        $post['published_at_raw'] = $rawTs;

        // Pretty date with ordinal suffix like "5th Nov 2025", read on the
        // blog's clock. These pages are full page cached, so the date cannot
        // follow whoever happens to be reading.
        $dt = new \DateTime($post['published_at_raw'], new \DateTimeZone('UTC'));
        $post['published_at'] = $this->formatDateWithOrdinal($dt, blog_timezone((int) ($ctx['blog']['id'] ?? 0)));

        // Enrich display fields. The byline follows the post's own author since a
        // blog serves every member's posts; the owner is only the fallback.
        $author = $ctx['user'];
        if (!empty($post['author_id']) && (int) $post['author_id'] !== (int) $ctx['user']['id']) {
            $author = $this->userModel->findById((int) $post['author_id']) ?: $ctx['user'];
        }

        $post['author_name'] = empty($author['display_name_cached']) ? $author['username'] : $author['display_name_cached'];
        $post['cover_url'] = $post['cover_url'] ?? null; // TODO update the key

        // --- comments enabled logic ---
        $blogCommentsEnabled = array_key_exists('comments_enabled', $ctx['settings'])
            ? (bool) $ctx['settings']['comments_enabled']
            : true; // default blog-level on

        $postCommentsEnabled = array_key_exists('comments_enabled', $post)
            ? (bool) $post['comments_enabled']
            : true; // default post-level on

        $commentsEnabled = $blogCommentsEnabled && $postCommentsEnabled;

        // Reader-chosen ordering, whitelisted by the model. It becomes part of
        // the thread cache key, so both orders can be warm at once.
        $commentSort = (string) ($this->request->get['comments'] ?? CommentModel::SORT_TOP);
        if (!in_array($commentSort, CommentModel::ALLOWED_SORTS, true)) {
            $commentSort = CommentModel::SORT_TOP;
        }

        // Built here rather than in the view: the query string reaches a
        // template only through the superglobals the framework keeps out of them.
        $commentSortUrls = [];
        foreach (CommentModel::ALLOWED_SORTS as $option) {
            $query = array_merge($this->request->get, ['comments' => $option]);
            $commentSortUrls[$option] = '?'.http_build_query($query).'#comments';
        }

        // Load comments only if enabled
        $comments = [];
        if ($commentsEnabled && !empty($post['id'])) {
            $comments = $this->commentModel->forPostThreaded((int) $post['id'], $commentSort);
        }

        // Prev/next/related
        $prev_post = $this->postModel->findPreviousByBlogIdAndDate((int) $ctx['blog']['id'], $post['published_at_raw']) ?: null;
        $next_post = $this->postModel->findNextByBlogIdAndDate((int) $ctx['blog']['id'], $post['published_at_raw']) ?: null;
        $related = $this->localizePosts(
            $this->postModel->findRecentByBlogIdExcludingSlug((int) $ctx['blog']['id'], $postSlug, 4),
            $ctx['settings']
        );

        if ($prev_post !== null) {
            $prev_post = $this->localizePosts([$prev_post], $ctx['settings'])[0];
        }
        if ($next_post !== null) {
            $next_post = $this->localizePosts([$next_post], $ctx['settings'])[0];
        }

        // Taxonomy for display: category name + slug, and tags as name/slug pairs.
        $category = !empty($post['category_id'])
            ? $this->categoryModel->findById((int) $post['category_id'])
            : null;
        $post['category'] = $category['name'] ?? null;
        $post['category_slug'] = $category['slug'] ?? null;

        $post['tags'] = array_map(
            static fn (array $t): array => ['name' => $t['name'], 'slug' => $t['slug']],
            $this->postModel->tags((int) $post['id'])
        );

        // Locale-prefixed, because this feeds og:url, the canonical link and the
        // share buttons. An unprefixed URL redirects to whatever locale the
        // reader's browser negotiates, which is not a stable canonical target.
        $shareUrl = rtrim(base_url(), '/').lurl('/blog/'.rawurlencode($blogSlug).'/'.rawurlencode($postSlug));

        // Merge meta: post-level SEO overrides > post content > blog defaults.
        $robots = [];
        if (!empty($post['meta_noindex']) || $ctx['meta']['robots'] !== null) {
            $robots[] = 'noindex';
        }
        if (!empty($post['meta_nofollow']) || $ctx['meta']['robots'] !== null) {
            $robots[] = 'nofollow';
        }

        $serpTitle = !empty($post['meta_title'])
            ? $post['meta_title']
            : ($post['title'] ?? 'Post').' - '.($ctx['blog']['blog_name'] ?? $ctx['user']['username']."'s Blog");
        $serpDescription = !empty($post['meta_description'])
            ? $post['meta_description']
            : ($post['excerpt'] ?? $ctx['meta']['description'] ?? '');

        $meta = array_merge($ctx['meta'], [
            'title' => $serpTitle,
            'description' => $serpDescription,
            'url' => $shareUrl,
            'og_type' => 'article',
            'og_title' => !empty($post['og_title']) ? $post['og_title'] : ($post['title'] ?? $serpTitle),
            'og_description' => !empty($post['og_description']) ? $post['og_description'] : $serpDescription,
            'og_image' => $this->absoluteAssetUrl(
                !empty($post['og_image']) ? $post['og_image'] : ($post['featured_image'] ?? null)
            ) ?? $ctx['meta']['og_image'],
            'og_image_alt' => $post['og_image_alt'] ?? null,
            'twitter_card' => !empty($post['twitter_card_type']) ? $post['twitter_card_type'] : 'summary_large_image',
            // X is the only platform that reads its own tags, so it is the
            // only one an author can address separately. Empty means inherit.
            'twitter_title' => $post['twitter_title'] ?? null,
            'twitter_description' => $post['twitter_description'] ?? null,
            'twitter_image' => $this->absoluteAssetUrl($post['twitter_image'] ?? null),
            'article_published_time' => gmdate('c', strtotime((string) $post['published_at_raw'])),
            'article_modified_time' => !empty($post['updated_at'])
                ? gmdate('c', strtotime((string) $post['updated_at']))
                : null,
            'article_author' => $post['author_name'] ?? null,
            'canonical' => !empty($post['canonical_url']) ? $post['canonical_url'] : $shareUrl,
            'robots' => $robots !== [] ? implode(', ', $robots) : null,
        ]);

        $viewerId = auth()->check() ? (int) auth()->user()['id'] : null;
        $engagementPostId = (int) $post['id'];

        // Comment votes and removal rights are the two things the thread cannot
        // read from its own cached rows, because both depend on who is looking.
        // The cached thread stays viewer-agnostic and these ride alongside it.
        $viewer = auth()->user();
        $blogResource = $viewerId !== null ? $this->model->getBlog((int) $ctx['blog']['id']) : null;
        $moderatesComments = $blogResource !== null && Gate::allows('update', $blogResource, $viewer);

        $commentVotes = $viewerId !== null && $commentsEnabled
            ? $this->commentVotes->votesForPost($viewerId, $engagementPostId)
            : [];

        // Handed to the view as a callable so the "author or moderator" rule
        // lives in the removal service, next to the removal itself.
        $canRemoveComment = fn (array $comment): bool => $this->commentRemoval->canRemove(
            $comment,
            $viewerId,
            $moderatesComments
        );

        // The composer shows who you are about to comment as.
        $viewerAvatar = $viewerId !== null
            ? ($this->profiles->getProfileAvatar($viewerId)['avatar_url'] ?? null)
            : null;

        // A comment the post's author upvoted wears their face with a heart on
        // it, the way a channel owner hearts a comment under their own video.
        // The author, not the blog owner: those are different people on roughly
        // half the posts here, and it is the author's thread. Their own votes
        // are the signal, so there is no separate gesture to store — one extra
        // indexed read, and it turns on who wrote the post rather than on who
        // is reading it, which is what keeps the thread cacheable.
        $authorId = (int) ($post['author_id'] ?? 0);
        $authorHearts = [];
        $authorIdentity = null;

        if ($commentsEnabled && $authorId > 0) {
            $authorHearts = array_keys(array_filter(
                $this->commentVotes->votesForPost($authorId, $engagementPostId),
                static fn (int $value): bool => $value === 1
            ));

            $author = $this->postModel->author($authorId);

            $authorIdentity = [
                'id' => $authorId,
                'name' => (string) ($author['display_name_cached'] ?? $author['username'] ?? ''),
                'avatar' => $this->profiles->getProfileAvatar($authorId)['avatar_url'] ?? null,
            ];
        }

        // Only the up count is published, matching the comment thread: the
        // down vote registers without handing readers a pile-on to watch.
        $engagement = [
            'like_count' => $this->postVoteModel->countByPost($engagementPostId),
            'bookmark_count' => $this->postBookmarkModel->countByPost($engagementPostId),
            'my_vote' => $viewerId !== null ? $this->postVoteModel->userVote($viewerId, $engagementPostId) : 0,
            'bookmarked' => $viewerId !== null && $this->postBookmarkModel->userBookmarks($viewerId, $engagementPostId),
            'logged_in' => $viewerId !== null,
        ];

        // array_merge, not +: the post-level meta must override the blog-level
        // meta already present in $ctx (the + operator kept the left side).
        return $this->view('Posts/show.lex.php', array_merge($ctx, [
            'engagement' => $engagement,
            'share_url' => $shareUrl,
            'post' => $post,
            'prev_post' => $prev_post,
            'next_post' => $next_post,
            'related' => $related,
            'meta' => $meta,
            'comments' => $comments,
            'comments_enabled' => $commentsEnabled,
            'comment_votes' => $commentVotes,
            'can_remove_comment' => $canRemoveComment,
            'can_moderate_comments' => $moderatesComments,
            'comment_sort' => $commentSort,
            'comment_sort_urls' => $commentSortUrls,
            'viewer_name' => $viewer['display_name_cached'] ?? ($viewer['username'] ?? null),
            'viewer_avatar' => $viewerAvatar,
            'comment_author_hearts' => $authorHearts,
            'comment_author' => $authorIdentity,
        ]));
    }

    /**
     * Turn a stored asset path into an absolute URL for og:image and friends.
     *
     * Social crawlers ignore relative URLs, so local paths are prefixed with
     * the site base URL while full http(s) URLs pass through untouched.
     */
    private function absoluteAssetUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim(base_url(), '/').'/'.ltrim($url, '/');
    }

    /**
     * @return array<string, mixed> Blog, owner, settings, and meta context
     */
    private function loadBlogContext(string $blogSlug): array
    {
        // 1) Blog
        $blog = $this->model->getBlogBySlug($blogSlug);
        if (!$blog) {
            throw new PageNotFoundException('Blog not found', 404);
        }

        if (isset($blog['status']) && $blog['status'] !== 'published') {
            throw new PageNotFoundException('Blog not found', 404);
        }

        // 2) Owner
        $user = $this->userModel->findById($blog['owner_id']);

        if (!$user) {
            throw new PageNotFoundException('Blog not found', 404);
        }

        // 3) Settings + meta defaults
        $settings = $this->settings->findByBlogId((int) $blog['id']) ?? [];

        // Themes iterate this as platform => URL, so hand them the decoded map
        $settings['social_links'] = BlogSettingsModel::decodeSocialLinks($settings['social_links'] ?? null);

        $blogUrl = rtrim(base_url(), '/').lurl('/blog/'.rawurlencode((string) ($blog['blog_slug'] ?? '')));

        $meta = [
            'title' => $settings['meta_title'] ?? ($blog['blog_name'] ?? ($user['display_name_cached']."'s Blog")),
            'description' => $settings['meta_description'] ?? ($blog['description'] ?? ''),
            'url' => $blogUrl,
            'site_name' => (string) ($blog['blog_name'] ?? ''),
            'og_type' => 'website',
            'og_title' => null, // themes fall back to title
            'og_description' => null, // themes fall back to description
            'og_image' => $this->absoluteAssetUrl($settings['banner_path'] ?? null),
            'twitter_card' => 'summary_large_image',
            // The blog home is reachable under any locale prefix that survives the
            // redirect guard, so name the content-locale URL as the one to index.
            'canonical' => $blogUrl,
            // Only an explicit indexable=false opts the blog out; a missing settings row must not.
            'robots' => (array_key_exists('indexable', $settings) && !$settings['indexable']) ? 'noindex, nofollow' : null,
        ];

        // 4) Back-compat field some templates expect
        $user['blog_name'] = $blog['blog_name'] ?? ($user['username']."'s Blog");

        // Every themed surface renders the owner as the author (the post guard
        // enforces it), so one lookup covers bylines, cards, and archives
        $user['public_profile_slug'] = $this->profiles->publicSlugFor((int) $user['id']);

        // 5) Logged-in reader shown in the theme masthead. Full-page cache only
        // serves guests, so viewer-specific markup never leaks between users.
        // Shared with the /auth/nav endpoint, which re-renders the same masthead
        // after a modal login.
        $viewer = app(\App\Services\ViewerContext::class)->current();

        // Every themed page renders these, so the subscribe confirmation (and any
        // other flash) shows up no matter which blog page the visitor lands back on.
        $flashes = $this->getFlashMessages();

        // Which languages this blog's own pages exist in. Recorded on LocaleState
        // so the head globals stop advertising translations that do not exist.
        $localeSet = $this->blogLocaleSet((int) $blog['id'], $settings);
        LocaleState::setLocaleSet($localeSet);
        $this->refreshHeadI18n();

        return compact('user', 'blog', 'settings', 'meta', 'viewer', 'flashes', 'localeSet');
    }

    /**
     * Locales a blog's own pages exist in: its base language, plus any locale a
     * post has been translated into once the blog opts into translations.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @return string[]
     */
    private function blogLocaleSet(int $blogId, array $settings): array
    {
        $default = (string) ($settings['default_locale'] ?? 'en');

        if (empty($settings['translations_enabled'])) {
            return [$default];
        }

        return array_values(array_unique(
            array_merge([$default], $this->translations->localesForBlog($blogId))
        ));
    }

    /**
     * Rebuild the language head globals now that this page's locale set is known.
     *
     * HeadI18nGlobals runs before the controller, so it could only assume every
     * supported locale. Template globals are merged at render time, so this later
     * call is what the page actually renders with.
     */
    private function refreshHeadI18n(): void
    {
        $full = $this->request->uri ?? '/';

        $this->viewer->addGlobals($this->headI18n->build(
            parse_url($full, PHP_URL_PATH) ?: '/',
            $this->request->get !== [] ? http_build_query($this->request->get) : null
        ));
    }

    /**
     * Blog-level redirect guard, for pages whose languages are the blog's.
     *
     * @param  array<string, mixed>  $ctx  Result of loadBlogContext()
     */
    private function blogLocaleRedirect(array $ctx): ?Response
    {
        return $this->localeRedirect(
            $ctx['localeSet'],
            (string) ($ctx['settings']['default_locale'] ?? 'en')
        );
    }

    /**
     * A redirect when the requested locale is not one this page exists in.
     *
     * 302 rather than 301: a locale set changes the moment an author adds a
     * translation, and a permanent redirect would outlive that change in browser
     * caches with no way to recall it.
     *
     * @param  string[]  $localeSet  Locales this page exists in
     * @param  string  $default  The page's own default locale
     */
    private function localeRedirect(array $localeSet, string $default): ?Response
    {
        $target = $this->localeResolver->redirectTarget($localeSet, locale(), $default);

        if ($target === null) {
            return null;
        }

        // LocalePrefixIntake already stripped the prefix, so the request URI is
        // the bare path and prefixing it rebuilds the whole URL. It strips the
        // query string too, so that has to come from the parsed parameters or a
        // reader on page 4 of an archive gets sent back to page 1.
        $path = parse_url($this->request->uri ?? '/', PHP_URL_PATH) ?: '/';
        $query = $this->request->get !== [] ? '?'.http_build_query($this->request->get) : '';

        return $this->response->redirect(
            '/'.$target.($path === '/' ? '' : $path).$query,
            302
        );
    }

    private function formatDateWithOrdinal(\DateTimeInterface $dt, string $tz = 'UTC'): string
    {
        $clone = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone(new \DateTimeZone($tz));

        return $clone->format('jS M Y'); // e.g. 5th Nov 2025
    }
}
