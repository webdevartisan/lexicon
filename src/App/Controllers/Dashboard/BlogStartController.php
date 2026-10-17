<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\GuidedSetupMarkModel;
use App\Resources\BlogResource;
use App\Services\BlogThemeService;
use App\Services\ThemeService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Steps 2 and 3 of Start your blog: what it's about, and a look.
 *
 * Step 1 is the create form (BlogController::new/create). The blog exists as a
 * draft from then on, so leaving at any step still leaves a usable blog.
 */
final class BlogStartController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private BlogSettingsModel $settings,
        private ThemeService $themes,
        private BlogThemeService $blogThemes,
        private GuidedSetupMarkModel $setupMarks,
    ) {}

    /**
     * @param  string  $blogId  Blog ID
     */
    public function about(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);

        return $this->view('areas/dashboard/BlogStart/about.lex.php', [
            'blog' => $blog->toArray(),
            'step' => 2,
        ]);
    }

    /**
     * Save the one-line description, or move on without one.
     *
     * @param  string  $blogId  Blog ID
     */
    public function saveAbout(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);

        if ($this->request->postParam('intent') !== 'skip') {
            $validated = $this->validateOrFail(['description' => 'max:160'])->validated();
            $description = trim((string) ($validated['description'] ?? ''));

            if ($description !== $blog->description()) {
                $this->blogModel->update($blog->id(), ['description' => $description]);
            }
        }

        return $this->redirect('/dashboard/blog/'.$blog->id().'/start/look');
    }

    /**
     * @param  string  $blogId  Blog ID
     */
    public function look(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);

        return $this->view('areas/dashboard/BlogStart/look.lex.php', [
            'blog' => $blog->toArray(),
            'step' => 3,
            'themes' => array_values($this->themes->available()),
            'currentTheme' => (string) ($this->settings->findByBlogId($blog->id())['theme'] ?? 'folio'),
        ]);
    }

    /**
     * Activate the picked theme, then open the editor for the first post.
     *
     * @param  string  $blogId  Blog ID
     */
    public function saveLook(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $editor = '/dashboard/post/new?blog_id='.$blog->id();

        if ($this->request->postParam('intent') === 'skip') {
            return $this->redirect($editor);
        }

        $userId = (int) auth()->user()['id'];
        $key = (string) $this->request->postParam('theme');

        if (!$this->blogThemes->activate($blog->id(), $key, $userId, $this->request->ip())) {
            $this->flash('error', $this->t('setup.start.look.unknown'));

            return $this->redirect('/dashboard/blog/'.$blog->id().'/start/look');
        }

        // Picking the default look still counts as choosing one
        $this->setupMarks->mark($userId, $blog->id(), 'look_chosen');

        return $this->redirect($editor);
    }

    private function authorizedBlog(string $blogId): BlogResource
    {
        $blog = $this->blogModel->getBlog((int) $blogId);
        if (!$blog) {
            throw new PageNotFoundException('Blog not found');
        }

        Gate::authorize('update', $blog, auth()->user());

        return $blog;
    }
}
