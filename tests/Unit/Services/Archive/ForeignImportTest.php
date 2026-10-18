<?php

declare(strict_types=1);

use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveMediaUrls;
use App\Services\Archive\ArchivePostValidator;
use App\Services\Archive\ArchiveZip;
use App\Services\Archive\Foreign\ForeignArchiveBuilder;

/**
 * Another platform's export becomes an archive the normal importer accepts, and every
 * post in it passes the same checks as one from a Lexicon archive.
 */
beforeEach(function () {
    $this->builder = new ForeignArchiveBuilder();
    $this->validator = new ArchivePostValidator(new ArchiveMediaUrls('https://lexicon.test'));
    $this->files = [];

    $this->write = function (string $contents, string $extension): string {
        $path = sys_get_temp_dir().'/foreign-'.bin2hex(random_bytes(4)).'.'.$extension;
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    };

    $this->convert = function (string $key, string $contents, string $siteUrl = ''): array {
        $source = $this->builder->source($key);
        $dest = ($this->write)('', 'zip');
        $this->builder->build($source, ($this->write)($contents, $source->extension()), $siteUrl, $dest);

        $zip = ArchiveZip::open($dest);
        $posts = $zip->json('posts.json');
        $manifest = $zip->json('manifest.json');
        $zip->close();

        return [$posts, $manifest];
    };

    $this->wordpress = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>Old Blog</title>
  <item>
    <title>First &amp; best</title>
    <dc:creator><![CDATA[maria]]></dc:creator>
    <content:encoded><![CDATA[<!-- wp:paragraph --><p>Hello there.</p><!-- /wp:paragraph -->]]></content:encoded>
    <excerpt:encoded><![CDATA[A short note]]></excerpt:encoded>
    <wp:post_id>10</wp:post_id>
    <wp:post_date_gmt>2024-03-05 14:30:00</wp:post_date_gmt>
    <wp:comment_status>closed</wp:comment_status>
    <wp:post_name>first-and-best</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_type>post</wp:post_type>
    <category domain="category" nicename="travel"><![CDATA[Travel]]></category>
    <category domain="post_tag" nicename="road"><![CDATA[Road]]></category>
    <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>55</wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_yoast_wpseo_title</wp:meta_key><wp:meta_value>%%title%% %%sep%%</wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_yoast_wpseo_metadesc</wp:meta_key><wp:meta_value>A search description</wp:meta_value></wp:postmeta>
  </item>
  <item>
    <title>Plain text post</title>
    <content:encoded><![CDATA[First paragraph.

Second paragraph.]]></content:encoded>
    <wp:post_id>11</wp:post_id>
    <wp:post_date_gmt>0000-00-00 00:00:00</wp:post_date_gmt>
    <wp:comment_status>open</wp:comment_status>
    <wp:post_name></wp:post_name>
    <wp:status>draft</wp:status>
    <wp:post_type>post</wp:post_type>
    <category domain="category" nicename="uncategorized"><![CDATA[Uncategorized]]></category>
  </item>
  <item>
    <title>An about page</title>
    <wp:post_id>12</wp:post_id>
    <wp:status>publish</wp:status>
    <wp:post_type>page</wp:post_type>
  </item>
  <item>
    <title>Gone</title>
    <wp:post_id>13</wp:post_id>
    <wp:status>trash</wp:status>
    <wp:post_type>post</wp:post_type>
  </item>
  <item>
    <title>photo</title>
    <wp:post_id>55</wp:post_id>
    <wp:post_type>attachment</wp:post_type>
    <wp:attachment_url>https://old.example/wp-content/uploads/photo.jpg</wp:attachment_url>
  </item>
</channel>
</rss>
XML;

    $this->ghost = json_encode(['db' => [['data' => [
        'posts' => [
            ['id' => 'p1', 'title' => 'Ghost post', 'slug' => 'ghost-post', 'html' => '<p>See <img src="__GHOST_URL__/content/images/a.jpg"></p>',
                'feature_image' => '__GHOST_URL__/content/images/cover.jpg', 'status' => 'published', 'visibility' => 'public',
                'published_at' => '2024-05-01T12:00:00.000Z', 'type' => 'post', 'custom_excerpt' => 'Short'],
            ['id' => 'p2', 'title' => 'Members only', 'slug' => 'members-only', 'html' => '<p>Secret</p>', 'status' => 'published',
                'visibility' => 'paid', 'published_at' => '2024-05-02T12:00:00.000Z', 'type' => 'post'],
            ['id' => 'p3', 'title' => 'A page', 'slug' => 'a-page', 'html' => '<p>Page</p>', 'status' => 'published', 'type' => 'page'],
        ],
        'tags' => [['id' => 't1', 'name' => 'News', 'slug' => 'news'], ['id' => 't2', 'name' => '#internal', 'slug' => 'hash-internal']],
        'posts_tags' => [['post_id' => 'p1', 'tag_id' => 't1', 'sort_order' => 0], ['post_id' => 'p1', 'tag_id' => 't2', 'sort_order' => 1]],
        'users' => [['id' => 'u1', 'name' => 'Sam Writer']],
        'posts_authors' => [['post_id' => 'p1', 'author_id' => 'u1', 'sort_order' => 0]],
        'posts_meta' => [['post_id' => 'p1', 'meta_title' => 'Ghost SEO title']],
    ]]]]);
});

afterEach(function () {
    array_map('unlink', array_filter($this->files, 'is_file'));
});

it('turns a WordPress export into valid archive posts', function () {
    [$posts, $manifest] = ($this->convert)('wordpress', $this->wordpress);

    expect($manifest['source']['name'])->toBe('WordPress')
        ->and($posts)->toHaveCount(2);

    $first = $posts[0];
    expect($first['title'])->toBe('First & best')
        ->and($first['slug'])->toBe('first-and-best')
        ->and($first['status'])->toBe('published')
        ->and($first['published_at'])->toBe('2024-03-05T14:30:00Z')
        ->and($first['comments_enabled'])->toBeFalse()
        ->and($first['content'])->toBe('<p>Hello there.</p>')
        ->and($first['category'])->toBe(['name' => 'Travel', 'slug' => 'travel'])
        ->and($first['tags'])->toBe([['name' => 'Road', 'slug' => 'road']])
        ->and($first['featured_image'])->toBe('https://old.example/wp-content/uploads/photo.jpg')
        ->and($first['meta_title'])->toBeNull()
        ->and($first['meta_description'])->toBe('A search description')
        ->and($first['author_name'])->toBe('maria');

    foreach ($posts as $post) {
        expect($this->validator->validate($post)['errors'])->toBe([]);
    }
});

it('wraps WordPress plain text in paragraphs and leaves drafts without a date', function () {
    [$posts] = ($this->convert)('wordpress', $this->wordpress);

    expect($posts[1]['status'])->toBe('draft')
        ->and($posts[1]['published_at'])->toBeNull()
        ->and($posts[1]['slug'])->toBe('plain-text-post')
        ->and($posts[1]['content'])->toBe("<p>First paragraph.</p>\n<p>Second paragraph.</p>")
        ->and($posts[1]['category'])->toBeNull();
});

it('turns a Ghost export into valid archive posts', function () {
    [$posts, $manifest] = ($this->convert)('ghost', $this->ghost, 'https://old.example/');

    expect($manifest['source']['name'])->toBe('Ghost')
        ->and($posts)->toHaveCount(2);

    $first = $posts[0];
    expect($first['content'])->toContain('src="https://old.example/content/images/a.jpg"')
        ->and($first['featured_image'])->toBe('https://old.example/content/images/cover.jpg')
        ->and($first['tags'])->toBe([['name' => 'News', 'slug' => 'news']])
        ->and($first['author_name'])->toBe('Sam Writer')
        ->and($first['meta_title'])->toBe('Ghost SEO title')
        ->and($first['published_at'])->toBe('2024-05-01T12:00:00Z');

    foreach ($posts as $post) {
        expect($this->validator->validate($post)['errors'])->toBe([]);
    }
});

it('brings Ghost members-only posts in as drafts', function () {
    [$posts] = ($this->convert)('ghost', $this->ghost, 'https://old.example');

    expect($posts[1]['title'])->toBe('Members only')
        ->and($posts[1]['status'])->toBe('draft');
});

it('asks for the old address when a Ghost export leaves it out of links', function () {
    expect(fn () => ($this->convert)('ghost', $this->ghost))->toThrow(ArchiveException::class);
});

it('refuses files that are not the platform they were sent as', function (string $key, string $contents) {
    expect(fn () => ($this->convert)($key, $contents))->toThrow(ArchiveException::class);
})->with([
    'ghost given html' => ['ghost', '<html></html>'],
    'ghost given other json' => ['ghost', '{"hello": "world"}'],
    'wordpress given html' => ['wordpress', '<html><body>hi</body></html>'],
    'wordpress given nonsense' => ['wordpress', 'not xml at all'],
]);

it('refuses an export with no posts', function () {
    $empty = '<?xml version="1.0"?><rss version="2.0"><channel><title>x</title></channel></rss>';

    expect(fn () => ($this->convert)('wordpress', $empty))->toThrow(ArchiveException::class);
});

it('knows only the platforms it can convert', function () {
    expect($this->builder->source('medium'))->toBeNull();
});
