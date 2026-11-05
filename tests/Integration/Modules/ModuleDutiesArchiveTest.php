<?php

declare(strict_types=1);

use App\Interfaces\ArchiveMediaStoreInterface;
use App\Models\AnalyticsSaltModel;
use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\MediaModel;
use App\Models\PostArchiveModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Models\UserModel;
use App\Services\Analytics\VisitorIdentity;
use App\Services\Archive\ArchiveMediaUrls;
use App\Services\Archive\ArchivePostValidator;
use App\Services\Archive\ArchivePostWriter;
use App\Services\Archive\BlogArchiveExporter;
use App\Services\Archive\BlogArchiveImporter;
use App\Services\Archive\BlogArchiveReader;
use App\Services\PersonalDataExportService;
use App\Services\PostContentSanitizer;
use App\Services\PublicCacheInvalidator;
use Framework\Modules\ModuleDuties;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * Module rows leave with a blog backup and come back attached to the new copies of their posts.
 */
function archiveImporter(Framework\Database $db, ModuleDuties $duties, ArchiveMediaUrls $urls): BlogArchiveImporter
{
    $archivePosts = new PostArchiveModel($db);
    $writer = new ArchivePostWriter(
        $archivePosts,
        new TagModel($db),
        new CategoryModel($db),
        new PostContentSanitizer(),
        $urls
    );

    return new BlogArchiveImporter(
        $db,
        new PostModel($db),
        Mockery::mock(ArchiveMediaStoreInterface::class),
        new PublicCacheInvalidator(),
        $writer,
        $duties
    );
}

beforeEach(function () {
    ModuleFixtures::dropSampleExtra($this->db);
    $this->duties = ModuleFixtures::installSampleExtra($this->db);
    $this->root = sys_get_temp_dir().'/lexicon-module-archive-'.bin2hex(random_bytes(4));
    mkdir($this->root.'/work', 0775, true);

    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();
    $blogs = new BlogModel($this->db);
    $this->source = $blogs->getBlog(BlogFactory::new($blogs)->published()->create($this->ownerId));
    $this->target = $blogs->getBlog(BlogFactory::new($blogs)->published()->create($this->ownerId));
    $this->postId = PostFactory::new(new PostModel($this->db))
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => (int) $this->source->id(), 'slug' => 'day-one'])
        ->published()
        ->create();

    $this->urls = new ArchiveMediaUrls('https://lexicon.test');
    $archivePosts = new PostArchiveModel($this->db);
    $media = new MediaModel($this->db);
    $this->exporter = new BlogArchiveExporter($archivePosts, $media, $this->urls, $this->duties, $this->root);
    $this->reader = new BlogArchiveReader($archivePosts, new ArchivePostValidator($this->urls), $this->urls);

    $this->restore = function (ModuleDuties $duties, ?Closure $tamper = null): array {
        $zip = $this->root.'/export.zip';
        $this->exporter->export($this->source, $zip);
        if ($tamper !== null) {
            $tamper($zip);
        }

        return archiveImporter($this->db, $duties, $this->urls)->import(
            $this->reader->read($zip, $this->target),
            $zip,
            $this->target,
            $this->ownerId,
            ['on_clash' => 'skip', 'keep_status' => false, 'work_dir' => $this->root.'/work']
        );
    };
});

afterEach(function () {
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::cleanUp();
    foreach (glob($this->root.'/{,work/}*', GLOB_BRACE) ?: [] as $file) {
        is_file($file) && unlink($file);
    }
    @rmdir($this->root.'/work');
    @rmdir($this->root);
});

test('the personal data export includes module data under its key', function () {
    $this->db->execute(
        'INSERT INTO sample_extra_notes (blog_id, user_id, note) VALUES (?, ?, ?)',
        [(int) $this->source->id(), $this->ownerId, 'mine']
    );
    $service = new PersonalDataExportService(
        $this->db,
        new VisitorIdentity(new AnalyticsSaltModel($this->db), 'test-key'),
        $this->duties
    );

    expect($service->export($this->ownerId)['modules']['sample-extra'])->toBe([['note' => 'mine']]);
});

test('a blog backup round trips module rows with remapped post ids', function () {
    $this->db->execute(
        'INSERT INTO sample_extra_notes (blog_id, post_id, note) VALUES (?, ?, ?)',
        [(int) $this->source->id(), $this->postId, 'on day one']
    );

    $result = ($this->restore)($this->duties);

    $newPostId = (int) $this->db->query(
        "SELECT id FROM posts WHERE blog_id = ? AND slug = 'day-one'",
        [(int) $this->target->id()]
    )->fetchColumn();
    $copied = $this->db->query(
        'SELECT post_id, note FROM sample_extra_notes WHERE blog_id = ?',
        [(int) $this->target->id()]
    )->fetchAll(PDO::FETCH_ASSOC);

    expect($result['modules']['imported'])->toBe(['sample-extra' => 1])
        ->and($newPostId)->not->toBe($this->postId)
        ->and($copied)->toBe([['post_id' => $newPostId, 'note' => 'on day one']]);
});

test('restoring module data for a module that is not installed skips it with a notice', function () {
    $this->db->execute(
        'INSERT INTO sample_extra_notes (blog_id, post_id, note) VALUES (?, ?, ?)',
        [(int) $this->source->id(), $this->postId, 'left behind']
    );

    $result = ($this->restore)(ModuleFixtures::noDuties($this->db));

    expect($result['modules']['skipped'])->toBe(['sample-extra'])
        ->and($result['modules']['imported'])->toBe([])
        ->and($result['created'])->not->toBeEmpty();
});

test('a damaged module file in a backup is reported and the posts still restore', function () {
    $this->db->execute(
        'INSERT INTO sample_extra_notes (blog_id, post_id, note) VALUES (?, ?, ?)',
        [(int) $this->source->id(), $this->postId, 'damaged']
    );

    $result = ($this->restore)($this->duties, function (string $zipPath): void {
        $zip = new ZipArchive();
        $zip->open($zipPath);
        $zip->addFromString('modules/sample-extra.json', '{ not json');
        $zip->close();
    });

    expect($result['modules']['failed'])->toBe(['sample-extra'])
        ->and($result['created'])->not->toBeEmpty();
});

test('a backup of a blog without module rows names no modules', function () {
    $result = ($this->restore)($this->duties);

    expect($result['modules'])->toBe(['imported' => [], 'skipped' => [], 'failed' => []]);
});
