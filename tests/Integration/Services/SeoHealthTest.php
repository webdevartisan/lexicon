<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\SeoAuditModel;
use App\Models\UserModel;
use App\Services\Analytics\SeoHealth;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * One blog with a post for each check, three posts kept from search engines on
 * purpose, and no search description of its own.
 */
beforeEach(function () {
    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, ['indexable' => 1, 'meta_description' => '']);
    $slug = (string) $this->db->query('SELECT blog_slug FROM blogs WHERE id = ?', [$this->blogId])->fetchColumn();

    $posts = new PostModel($this->db);
    $post = fn (string $name, array $fields = []): int => PostFactory::new($posts)->withAttributes($fields + [
        'blog_id' => $this->blogId,
        'author_id' => $this->ownerId,
        'slug' => $name,
        'meta_title' => 'Notes on '.$name,
        'meta_description' => 'A short description.',
        'content' => '<p>Plain text.</p>',
    ])->published()->create();

    $this->ids = [
        'long' => $post('long', ['meta_title' => str_repeat('a', 70)]),
        'twin1' => $post('twin1', ['meta_title' => 'Same title']),
        'twin2' => $post('twin2', ['meta_title' => 'Same Title']),
        'bare' => $post('bare', ['meta_description' => null, 'excerpt' => null]),
        'keyword' => $post('river-birds-guide', ['focus_keyword' => 'river birds']),
        'share' => $post('share', ['og_image' => '/uploads/cover.jpg', 'og_image_alt' => '']),
        'images' => $post('images', ['content' => '<img src="a.jpg"><img src="b.jpg" alt=""><img src="c.jpg" alt="A heron">']),
        'canonicalHere' => $post('here', ['canonical_url' => "https://lexicon.test/en/blog/{$slug}/here"]),
        'noindex' => $post('hidden', ['meta_noindex' => 1]),
        'unlisted' => $post('unlisted', ['visibility' => 'unlisted']),
        'elsewhere' => $post('elsewhere', ['canonical_url' => 'https://elsewhere.example/post']),
    ];

    $this->health = new SeoHealth(new SeoAuditModel($this->db));
});

test('each check names the posts it found, with what to show beside them', function () {
    $report = $this->health->forBlog($this->blogId);
    $found = static fn (string $check): array => array_column($report['checks'][$check], 'detail', 'id');

    expect([$report['published'], $report['indexable']])->toBe([11, 8])
        ->and($found('title_long'))->toBe([$this->ids['long'] => 70])
        ->and($found('title_duplicate'))->toEqualCanonicalizing([$this->ids['twin1'] => 2, $this->ids['twin2'] => 2])
        ->and(array_keys($found('no_description')))->toBe([$this->ids['bare']])
        ->and($found('keyword_missing'))->toBe([$this->ids['keyword'] => 'title,description'])
        ->and(array_keys($found('og_alt_missing')))->toBe([$this->ids['share']])
        ->and($found('image_alt_missing'))->toBe([$this->ids['images'] => 2])
        ->and($report['checks']['not_in_sitemap'])->toBe([])
        ->and($report['blog'])->toBe(['no_blog_description']);
});

test('posts kept from search engines are listed with the reason, not checked', function () {
    $report = $this->health->forBlog($this->blogId);

    expect(array_column($report['notOffered'], 'detail', 'id'))->toEqualCanonicalizing([
        $this->ids['noindex'] => 'noindex',
        $this->ids['unlisted'] => 'unlisted',
        $this->ids['elsewhere'] => 'canonical',
    ]);
});

test('a writer sees only their own posts, and nothing about the blog settings', function () {
    $report = $this->health->forBlog($this->blogId, [$this->ids['long'], $this->ids['noindex']]);

    expect([$report['published'], $report['indexable']])->toBe([2, 1])
        ->and(array_column($report['checks']['title_long'], 'id'))->toBe([$this->ids['long']])
        ->and($report['checks']['title_duplicate'])->toBe([])
        ->and(array_column($report['notOffered'], 'id'))->toBe([$this->ids['noindex']])
        ->and($report['blog'])->toBe([]);
});

test('a blog hidden from search engines says so, and none of its posts are offered', function () {
    (new BlogSettingsModel($this->db))->updateForBlog($this->blogId, ['indexable' => 0]);

    $report = $this->health->forBlog($this->blogId);

    expect($report['indexable'])->toBe(0)
        ->and($report['blog'])->toContain('indexing_off')
        ->and(array_unique(array_column($report['notOffered'], 'detail')))->toBe(['blog']);
});

test('the site counts posts offered against the sitemap limit', function () {
    expect($this->health->forSite())->toBe(['published' => 11, 'indexable' => 8, 'beyondSitemap' => 0, 'sitemapLimit' => PostModel::SITEMAP_LIMIT]);
});

test('the sitemap lists only the posts offered to search engines, and no blog hidden from them', function () {
    $slugs = array_column((new PostModel($this->db))->findPublicForSitemap(), 'slug');

    expect($slugs)->toContain('here')
        ->and($slugs)->toContain('long')
        ->and($slugs)->not->toContain('hidden')
        ->and($slugs)->not->toContain('unlisted')
        ->and($slugs)->not->toContain('elsewhere');

    (new BlogSettingsModel($this->db))->updateForBlog($this->blogId, ['indexable' => 0]);

    expect((new PostModel($this->db))->findPublicForSitemap())->toBe([])
        ->and((new BlogModel($this->db))->findPublicForSitemap())->toBe([]);
});
