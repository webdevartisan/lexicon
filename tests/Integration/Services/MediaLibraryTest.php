<?php

declare(strict_types=1);

use App\Interfaces\ImageProcessorInterface;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\MediaModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Resources\BlogResource;
use App\Services\BlogCacheInvalidator;
use App\Services\MediaReferenceCleaner;
use App\Services\MediaService;
use App\Services\MediaUsageResolver;
use App\Services\UploadService;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * A blog's media library only holds files from the blog's own folder, and deleting
 * one must not leave the blog pointing at a missing file.
 *
 * Most media rows here point at files that do not exist: a missing file is exactly
 * the case the delete has to handle.
 */
beforeEach(function () {
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->settings = new BlogSettingsModel($this->db);
    $this->mediaRows = new MediaModel($this->db);

    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blog = $this->blogs->getBlog(BlogFactory::new($this->blogs)->published()->create($ownerId));
    $this->settings->createDefaultForBlog($this->blog->id(), []);

    $this->url = '/uploads/blogs/'.$this->blog->mediaKey().'/2026/10/banner-aaaaaaaaaaaa.jpg';

    $this->postId = PostFactory::new($this->posts)->withAttributes([
        'blog_id' => $this->blog->id(),
        'author_id' => $ownerId,
        'featured_image' => $this->url,
        'og_image' => $this->url,
        'content' => '<p>Text</p><img src="'.$this->url.'">',
    ])->create();
    $this->settings->updateForBlog($this->blog->id(), ['banner_path' => $this->url]);

    $usage = new MediaUsageResolver($this->db);
    $this->cleaner = new MediaReferenceCleaner($usage, $this->posts, $this->settings, new BlogCacheInvalidator());

    $images = new class() implements ImageProcessorInterface
    {
        public function process(string $sourcePath, string $destPath, array $opts = []): array
        {
            return ['width' => 1, 'height' => 1];
        }

        public function crop(string $sourcePath, string $destPath, array $rect, array $opts = []): array
        {
            return ['width' => 1, 'height' => 1];
        }
    };
    $this->media = new MediaService(new UploadService($images), $this->mediaRows, $images, $usage, $this->cleaner);

    // storage/uploads is shared with the dev site, so real files only ever go in a test blog's own random folder.
    $this->testFolders = [];
    $this->putFile = function (BlogResource $blog, string $name): string {
        $url = '/uploads/blogs/'.$blog->mediaKey().'/2026/10/'.$name;
        $path = ROOT_PATH.'/storage'.$url;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
        $this->testFolders[] = ROOT_PATH.'/storage/uploads/blogs/'.$blog->mediaKey();

        return $url;
    };
});

afterEach(function () {
    foreach (array_unique($this->testFolders) as $folder) {
        if (!is_dir($folder)) {
            continue;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($folder);
    }
});

it('gives every new blog its own random folder key', function () {
    $other = $this->blogs->getBlog(BlogFactory::new($this->blogs)->create($this->blog->ownerId()));

    expect($this->blog->mediaKey())->toMatch('/^[0-9a-f]{16}$/')
        ->and($other->mediaKey())->toMatch('/^[0-9a-f]{16}$/')
        ->and($other->mediaKey())->not->toBe($this->blog->mediaKey());
});

it('empties the banner, featured and social slots that hold the image', function () {
    $this->cleaner->clear($this->blog, $this->url);

    $post = $this->posts->find($this->postId);
    $settings = $this->settings->findByBlogId($this->blog->id());

    expect($post['featured_image'])->toBeNull()
        ->and($post['og_image'])->toBeNull()
        ->and($settings['banner_path'])->toBeNull();
});

it('leaves an image inside the post text alone', function () {
    $this->cleaner->clear($this->blog, $this->url);

    expect($this->posts->find($this->postId)['content'])->toContain('src="'.$this->url.'"');
});

it('does not touch slots holding a different image', function () {
    $this->cleaner->clear($this->blog, '/uploads/blogs/'.$this->blog->mediaKey().'/2026/10/logo-bbbbbbbbbbbb.jpg');

    expect($this->posts->find($this->postId)['featured_image'])->toBe($this->url)
        ->and($this->settings->findByBlogId($this->blog->id())['banner_path'])->toBe($this->url);
});

it('deletes the library row and clears the slots in one go, even when the file is already gone', function () {
    $this->db->execute(
        'INSERT INTO media (blog_id, disk_path, url, filename, size_bytes, source) VALUES (?, ?, ?, ?, 0, ?)',
        [$this->blog->id(), ltrim($this->url, '/'), $this->url, basename($this->url), 'branding']
    );
    $mediaId = (int) $this->db->lastInsertId();

    expect($this->media->delete($mediaId, $this->blog))->toBeTrue()
        ->and($this->mediaRows->findForBlog($mediaId, $this->blog->id()))->toBeNull()
        ->and($this->posts->find($this->postId)['featured_image'])->toBeNull()
        ->and($this->settings->findByBlogId($this->blog->id())['banner_path'])->toBeNull();
});

it('refuses to delete a library row from another blog', function () {
    $other = $this->blogs->getBlog(BlogFactory::new($this->blogs)->create($this->blog->ownerId()));
    $this->db->execute(
        'INSERT INTO media (blog_id, disk_path, url, filename, size_bytes, source) VALUES (?, ?, ?, ?, 0, ?)',
        [$this->blog->id(), ltrim($this->url, '/'), $this->url, basename($this->url), 'branding']
    );
    $mediaId = (int) $this->db->lastInsertId();

    expect($this->media->delete($mediaId, $other))->toBeFalse()
        ->and($this->posts->find($this->postId)['featured_image'])->toBe($this->url);
});

it('never takes a file from another blog into the library, however it is offered', function () {
    $other = $this->blogs->getBlog(BlogFactory::new($this->blogs)->create($this->blog->ownerId()));
    $foreign = ($this->putFile)($other, 'banner-cccccccccccc.png');

    expect($this->media->register($this->blog, $this->blog->ownerId(), $foreign, 'upload'))->toBeNull()
        ->and($this->media->ensureIndexed($this->blog, $foreign))->toBeNull()
        ->and(fn () => $this->media->usePicked($this->blog, $foreign, 'branding'))->toThrow(InvalidArgumentException::class)
        ->and($this->mediaRows->findByPathForBlog($this->blog->id(), ltrim($foreign, '/')))->toBeNull();
});

it('leaves files it finds on disk without an owner, so erasing whoever looked cannot delete them', function () {
    $url = ($this->putFile)($this->blog, 'image-dddddddddddd.png');

    expect($this->media->backfill($this->blog))->toBe(1)
        ->and($this->mediaRows->findByPathForBlog($this->blog->id(), ltrim($url, '/'))['user_id'])->toBeNull();
});
