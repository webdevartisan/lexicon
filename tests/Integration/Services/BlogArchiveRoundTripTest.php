<?php

declare(strict_types=1);

use App\Interfaces\ArchiveMediaStoreInterface;
use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\MediaModel;
use App\Models\PostArchiveModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Models\UserModel;
use App\Resources\BlogResource;
use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveFormat;
use App\Services\Archive\ArchiveMediaUrls;
use App\Services\Archive\ArchivePostValidator;
use App\Services\Archive\ArchivePostWriter;
use App\Services\Archive\BlogArchiveExporter;
use App\Services\Archive\BlogArchiveImporter;
use App\Services\Archive\BlogArchiveReader;
use App\Services\PostContentSanitizer;
use App\Services\PublicCacheInvalidator;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * A blog archive must survive the trip out of one blog and into another, and every
 * clash policy must do what the preview says it will.
 *
 * Everything on disk lives in a temp folder: storage/uploads is shared with the dev
 * site, so the exporter reads from a temp storage root and the importer stores media
 * through a stand-in that writes there too.
 */
beforeEach(function () {
    $this->root = sys_get_temp_dir().'/lexicon-archive-'.bin2hex(random_bytes(4));
    mkdir($this->root.'/work', 0775, true);

    $users = new UserModel($this->db);
    $this->ownerId = UserFactory::new($users)->create();
    $this->formerOwnerId = UserFactory::new($users)->create();
    $this->otherAuthorId = UserFactory::new($users)->create();

    $blogs = new BlogModel($this->db);
    $this->sourceId = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $this->targetId = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $this->source = $blogs->getBlog($this->sourceId);
    $this->target = $blogs->getBlog($this->targetId);

    $urls = new ArchiveMediaUrls('https://lexicon.test');
    $archivePosts = new PostArchiveModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->tags = new TagModel($this->db);
    $this->categories = new CategoryModel($this->db);

    $this->exporter = new BlogArchiveExporter($archivePosts, new MediaModel($this->db), $urls, $this->root);
    $this->reader = new BlogArchiveReader($archivePosts, new ArchivePostValidator($urls), $urls);
    $this->store = new class($this->root) implements ArchiveMediaStoreInterface
    {
        public function __construct(private string $root) {}

        public function store(string $path, string $sourceRoot, string $name, ?string $alt, BlogResource $blog, int $userId): string
        {
            $url = '/uploads/users/'.$blog->ownerId().'/blogs/'.$blog->id().'/media/'.$name;
            is_dir(dirname($this->root.$url)) || mkdir(dirname($this->root.$url), 0775, true);
            copy($path, $this->root.$url);

            return $url;
        }
    };
    $writer = new ArchivePostWriter($archivePosts, $this->tags, $this->categories, new PostContentSanitizer(), $urls);
    $this->importer = new BlogArchiveImporter(
        $this->db, $this->posts, $this->store, new PublicCacheInvalidator(), $writer
    );

    $this->image = function (string $url, array $rgb): void {
        $file = $this->root.$url;
        is_dir(dirname($file)) || mkdir(dirname($file), 0775, true);
        $im = imagecreatetruecolor(40, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
        imagejpeg($im, $file);
    };

    $this->export = function (): string {
        $zip = $this->root.'/export.zip';
        $this->exporter->export($this->source, $zip);

        return $zip;
    };

    $this->import = fn (string $zip, string $onClash = 'skip', bool $keep = false): array => $this->importer->import(
        $this->reader->read($zip, $this->target),
        $zip,
        $this->target,
        $this->ownerId,
        ['on_clash' => $onClash, 'keep_status' => $keep, 'work_dir' => $this->root.'/work']
    );

    // A hand-built archive, for content the exporter would never produce.
    $this->craft = function (array $posts, array $media = [], array $manifest = []): string {
        $path = $this->root.'/craft-'.bin2hex(random_bytes(3)).'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('manifest.json', json_encode($manifest + ['format' => ArchiveFormat::NAME, 'format_version' => 1]));
        $zip->addFromString('posts.json', json_encode($posts));
        $zip->addFromString('media.json', json_encode(array_map(fn ($p) => ['path' => $p, 'original_name' => basename($p)], array_keys($media))));
        foreach ($media as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        $zip->close();

        return $path;
    };

    $this->seedSource = function (): void {
        $mine = '/uploads/users/'.$this->ownerId.'/blogs/'.$this->sourceId;
        $theirs = '/uploads/users/'.$this->formerOwnerId.'/blogs/'.$this->sourceId;
        ($this->image)($mine.'/postImages/feat-aaaaaaaaaaaa.jpg', [10, 20, 30]);
        ($this->image)($mine.'/postImages/inline-bbbbbbbbbbbb.jpg', [40, 50, 60]);
        ($this->image)($theirs.'/media/old-cccccccccccc.jpg', [70, 80, 90]);

        $categoryId = $this->categories->findOrCreateForBlog($this->sourceId, 'Field Notes');
        $postId = PostFactory::new($this->posts)->withAttributes([
            'blog_id' => $this->sourceId,
            'author_id' => $this->ownerId,
            'category_id' => $categoryId,
            'title' => 'Harbour morning',
            'slug' => 'harbour-morning',
            'status' => 'published',
            'published_at' => '2026-01-01 10:00:00',
            'featured_image' => $mine.'/postImages/feat-aaaaaaaaaaaa.jpg',
            'content' => '<p><img src="../../../../uploads/users/'.$this->ownerId.'/blogs/'.$this->sourceId.'/postImages/inline-bbbbbbbbbbbb.jpg"></p>'
                .'<p><img src="'.$theirs.'/media/old-cccccccccccc.jpg"></p>',
        ])->create();
        $this->tags->syncForPost($postId, $this->sourceId, ['Travel', 'Photo']);

        PostFactory::new($this->posts)->withAttributes([
            'blog_id' => $this->sourceId, 'author_id' => $this->ownerId, 'title' => 'A draft', 'slug' => 'a-draft',
            'content' => '<p>Unfinished</p>',
        ])->create();
    };
});

afterEach(function () {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($this->root);
});

function archivePostBySlug(\Framework\Database $db, int $blogId, string $slug): ?array
{
    return $db->query('SELECT * FROM posts WHERE blog_id = ? AND slug = ?', [$blogId, $slug])->fetch(PDO::FETCH_ASSOC) ?: null;
}

it('carries posts, taxonomy and media into another blog', function () {
    ($this->seedSource)();

    $result = ($this->import)(($this->export)());

    $post = archivePostBySlug($this->db, $this->targetId, 'harbour-morning');
    $tagSlugs = array_column($this->db->query(
        'SELECT t.slug FROM post_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.post_id = ? ORDER BY t.slug',
        [$post['id']]
    )->fetchAll(PDO::FETCH_ASSOC), 'slug');
    $targetMedia = '/uploads/users/'.$this->ownerId.'/blogs/'.$this->targetId.'/media/';

    expect(count($result['created']))->toBe(2)
        ->and($result['failed'])->toBe([])
        ->and($result['media']['stored'])->toBe(3)
        ->and($post['status'])->toBe('draft')
        ->and((int) $post['author_id'])->toBe($this->ownerId)
        ->and($this->categories->findForBlog((int) $post['category_id'], $this->targetId)['slug'])->toBe('field-notes')
        ->and($tagSlugs)->toBe(['photo', 'travel'])
        ->and($post['featured_image'])->toStartWith($targetMedia)
        ->and(substr_count($post['content'], 'src="'.$targetMedia))->toBe(2)
        ->and($post['content'])->not->toContain('/blogs/'.$this->sourceId.'/');
});

it('leaves existing posts alone when told to skip clashes', function () {
    ($this->seedSource)();
    $existing = PostFactory::new($this->posts)->withAttributes([
        'blog_id' => $this->targetId, 'author_id' => $this->otherAuthorId, 'title' => 'Mine', 'slug' => 'harbour-morning',
        'content' => '<p>Keep me</p>',
    ])->create();

    $result = ($this->import)(($this->export)(), BlogArchiveImporter::CLASH_SKIP);

    expect(array_column($result['skipped'], 'slug'))->toBe(['harbour-morning'])
        ->and($this->posts->find($existing)['content'])->toBe('<p>Keep me</p>');
});

it('imports only the posts picked and reports the rest as left out', function () {
    $post = fn (string $slug) => ['title' => ucfirst($slug), 'slug' => $slug, 'content' => '<p>Body</p>', 'status' => 'draft', 'visibility' => 'public', 'tags' => []];
    $zip = ($this->craft)([$post('first-pick'), $post('second-pick'), $post('third-pick')]);

    $result = $this->importer->import(
        $this->reader->read($zip, $this->target),
        $zip,
        $this->target,
        $this->ownerId,
        ['on_clash' => 'skip', 'keep_status' => false, 'work_dir' => $this->root.'/work', 'only' => [1]]
    );

    expect(array_column($result['created'], 'slug'))->toBe(['second-pick'])
        ->and(array_column($result['skipped'], 'slug'))->toBe(['first-pick', 'third-pick'])
        ->and(archivePostBySlug($this->db, $this->targetId, 'first-pick'))->toBeNull();
});

it('imports clashing posts under a numbered slug when told to copy', function () {
    ($this->seedSource)();
    PostFactory::new($this->posts)->withAttributes([
        'blog_id' => $this->targetId, 'author_id' => $this->otherAuthorId, 'title' => 'Mine', 'slug' => 'harbour-morning',
    ])->create();

    ($this->import)(($this->export)(), BlogArchiveImporter::CLASH_COPY);

    expect(archivePostBySlug($this->db, $this->targetId, 'harbour-morning-2'))->not->toBeNull()
        ->and(archivePostBySlug($this->db, $this->targetId, 'harbour-morning')['title'])->toBe('Mine');
});

it('overwrites in place, keeping the id, the author and the tags of the archive', function () {
    ($this->seedSource)();
    $existing = PostFactory::new($this->posts)->withAttributes([
        'blog_id' => $this->targetId, 'author_id' => $this->otherAuthorId, 'title' => 'Mine', 'slug' => 'harbour-morning',
    ])->create();
    $this->tags->syncForPost($existing, $this->targetId, ['Old tag']);

    $result = ($this->import)(($this->export)(), BlogArchiveImporter::CLASH_OVERWRITE);
    $post = $this->posts->find($existing);
    $tagNames = array_column($this->db->query(
        'SELECT t.name FROM post_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.post_id = ? ORDER BY t.name',
        [$existing]
    )->fetchAll(PDO::FETCH_ASSOC), 'name');

    expect(array_column($result['overwritten'], 'id'))->toBe([$existing])
        ->and($post['title'])->toBe('Harbour morning')
        ->and((int) $post['author_id'])->toBe($this->otherAuthorId)
        ->and($tagNames)->toBe(['Photo', 'Travel']);
});

it('runs every body through the sanitiser', function () {
    $zip = ($this->craft)([[
        'title' => 'Hostile', 'slug' => 'hostile', 'status' => 'draft', 'visibility' => 'public',
        'content' => '<p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
    ]]);

    ($this->import)($zip);
    $content = archivePostBySlug($this->db, $this->targetId, 'hostile')['content'];

    expect($content)->not->toContain('<script')
        ->and($content)->not->toContain('onclick')
        ->and($content)->not->toContain('javascript:');
});

it('reports missing and unusable media without aborting the other posts', function () {
    $zip = ($this->craft)([
        ['title' => 'Missing image', 'slug' => 'missing-image', 'content' => '<p>Before<img src="media/gone123.jpg"></p>'],
        ['title' => 'Gif image', 'slug' => 'gif-image', 'content' => '<p><img src="media/anim123.gif"></p>'],
        ['title' => 'Plain', 'slug' => 'plain', 'content' => '<p>Fine</p>'],
    ], ['media/anim123.gif' => 'GIF89a']);

    $plan = $this->reader->read($zip, $this->target);
    $result = ($this->import)($zip);

    expect(array_column($plan->problems, 'type'))->toContain('media_missing', 'media_skipped', 'media_left_out')
        ->and(count($result['created']))->toBe(3)
        ->and(archivePostBySlug($this->db, $this->targetId, 'missing-image')['content'])->not->toContain('gone123')
        ->and(archivePostBySlug($this->db, $this->targetId, 'gif-image')['content'])->not->toContain('<img');
});

it('stamps live imports as already announced and never queues mail', function () {
    $zip = ($this->craft)([
        ['title' => 'Live one', 'slug' => 'live-one', 'status' => 'published', 'published_at' => '2026-01-01T00:00:00Z', 'content' => '<p>x</p>'],
        ['title' => 'Overdue', 'slug' => 'overdue', 'status' => 'scheduled', 'published_at' => '2025-01-01T00:00:00Z', 'content' => '<p>x</p>'],
        ['title' => 'Still a draft', 'slug' => 'still-draft', 'status' => 'draft', 'content' => '<p>x</p>'],
    ]);

    ($this->import)($zip, BlogArchiveImporter::CLASH_SKIP, true);

    expect(archivePostBySlug($this->db, $this->targetId, 'live-one')['subscribers_notified_at'])->not->toBeNull()
        ->and(archivePostBySlug($this->db, $this->targetId, 'overdue')['status'])->toBe('published')
        ->and(archivePostBySlug($this->db, $this->targetId, 'overdue')['subscribers_notified_at'])->not->toBeNull()
        ->and(archivePostBySlug($this->db, $this->targetId, 'still-draft')['subscribers_notified_at'])->toBeNull()
        ->and((int) $this->db->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn())->toBe(0);
});

it('refuses an archive written in an unknown format version', function () {
    $zip = ($this->craft)([], [], ['format_version' => 2]);

    expect(fn () => $this->reader->read($zip, $this->target))->toThrow(ArchiveException::class, 'format version 2');
});

it('refuses a zip-slip archive before reading any post', function () {
    $path = $this->root.'/slip.zip';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('manifest.json', json_encode(['format' => ArchiveFormat::NAME, 'format_version' => 1]));
    $zip->addFromString('../../evil.php', '<?php echo 1;');
    $zip->close();

    expect(fn () => $this->reader->read($path, $this->target))->toThrow(ArchiveException::class, 'unsafe file path')
        ->and(file_exists(dirname($this->root).'/evil.php'))->toBeFalse();
});
