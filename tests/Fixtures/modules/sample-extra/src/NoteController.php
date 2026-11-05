<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\SampleExtra;

use App\Controllers\ModuleController;

/**
 * Exposes the active check so tests can call it directly.
 */
final class NoteController extends ModuleController
{
    public function checkBlog(int $blogId): void
    {
        $this->requireActive($blogId);
    }

    protected function moduleKey(): string
    {
        return 'sample-extra';
    }
}
