<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Resources\BlogResource;
use App\Services\BlogCacheInvalidator;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use Framework\Modules\BlogDataEraser;
use Framework\Modules\ModuleInstances;
use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRegistry;

/**
 * The Extras page: a blog switches optional modules on and off for itself.
 *
 * Switching off keeps the extra's data, so turning it back on restores everything.
 * Deleting that data is a separate step, offered only while the extra is off.
 * Anyone who can change the blog's appearance can manage its extras.
 */
final class ExtrasController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private ModuleRegistry $registry,
        private BlogModuleModel $blogModules,
        private ModuleInstances $instances,
        private BlogCacheInvalidator $blogCache,
    ) {}

    /**
     * List the extras this site offers, with whether each is on for the blog.
     */
    public function index(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $active = $this->blogModules->activeKeys((int) $blog->id());

        $extras = [];
        foreach ($this->registry->blogExtras() as $key => $manifest) {
            $on = in_array($key, $active, true);
            $extras[] = [
                'key' => $key,
                'name' => $manifest->name,
                'description' => $manifest->description,
                'on' => $on,
                'hasData' => !$on && $this->hasData($manifest, (int) $blog->id()),
                'page' => $on && $manifest->hasPage ? '/dashboard/blog/'.(int) $blog->id().'/m/'.$key : null,
            ];
        }

        return $this->view('areas/dashboard/Extras/index.lex.php', [
            'blog' => $blog->toArray(),
            'extras' => $extras,
        ]);
    }

    /**
     * Switch one extra on or off for the blog.
     */
    public function toggle(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));
        $blog = $this->authorizedBlog($blogId);

        $input = $this->validateOrFail(['module' => 'required', 'enabled' => 'required|in:0,1'])->validated();
        $manifest = $this->extra((string) $input['module']);

        if ($input['enabled'] === '1') {
            $this->blogModules->enable((int) $blog->id(), $manifest->key);
            $this->flash('success', 'Extra switched on.');
        } else {
            $this->blogModules->disable((int) $blog->id(), $manifest->key);
            $this->flash('success', 'Extra switched off.');
        }

        $this->blogCache->forgetBlog((string) $blog->toArray()['blog_slug']);

        return $this->redirect($this->pagePath($blog));
    }

    /**
     * Delete everything an extra stored for the blog. Only allowed while the extra is off.
     */
    public function purge(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));
        $blog = $this->authorizedBlog($blogId);

        $input = $this->validateOrFail(['module' => 'required'])->validated();
        $manifest = $this->extra((string) $input['module']);

        if (in_array($manifest->key, $this->blogModules->activeKeys((int) $blog->id()), true)) {
            $this->flash('error', 'Switch '.$manifest->name.' off before deleting its data.');

            return $this->redirect($this->pagePath($blog));
        }

        $module = $this->instances->for($manifest);
        if ($module instanceof BlogDataEraser) {
            $module->eraseBlog((int) $blog->id());
        }

        $this->blogCache->forgetBlog((string) $blog->toArray()['blog_slug']);
        $this->flash('success', 'Data deleted.');

        return $this->redirect($this->pagePath($blog));
    }

    /**
     * @throws PageNotFoundException When the blog does not exist
     */
    private function authorizedBlog(string $blogId): BlogResource
    {
        $blog = $this->blogModel->getBlog($blogId);
        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$blogId}' not found.");
        }

        Gate::authorize('update', $blog, auth()->user() ?? []);

        return $blog;
    }

    /**
     * The installed blog extra with this key; anything else is not found, never a write.
     *
     * @throws PageNotFoundException
     */
    private function extra(string $key): ModuleManifest
    {
        return $this->registry->blogExtras()[$key] ?? throw new PageNotFoundException('That extra is not available.');
    }

    private function hasData(ModuleManifest $manifest, int $blogId): bool
    {
        $module = $this->instances->for($manifest);

        return $module instanceof BlogDataEraser && $module->hasBlogData($blogId);
    }

    private function pagePath(BlogResource $blog): string
    {
        return '/dashboard/blog/'.$blog->id().'/extras';
    }
}
