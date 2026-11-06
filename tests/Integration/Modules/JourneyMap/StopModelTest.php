<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Modules\JourneyMap\Stop;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * Stops are stored one per post and listed in journey order, public posts only.
 */
beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogs = new BlogModel($this->db);
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $this->stops = new StopModel($this->db);

    $this->post = fn (array $attributes = [], ?int $blogId = null): int => PostFactory::new(new PostModel($this->db))
        ->published()
        ->withAttributes($attributes + ['author_id' => $this->ownerId, 'blog_id' => $blogId ?? $this->blogId])
        ->create();
    $this->place = fn (string $name, ?int $number = null): Stop => Stop::fromInput([
        'place_name' => $name,
        'lat' => '40.4',
        'lng' => '-3.7',
        'precision' => 'exact',
        'stop_number' => $number === null ? '' : (string) $number,
    ]);
    $this->publishedIds = fn (): array => array_map(
        'intval',
        array_column($this->stops->published($this->blogId), 'post_id')
    );
});

afterEach(function () {
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('published stops come in stop number order then by publish date', function () {
    $second = ($this->post)(['published_at' => '2026-01-03 10:00:00']);
    $third = ($this->post)(['published_at' => '2026-01-01 10:00:00']);
    $first = ($this->post)(['published_at' => '2026-01-02 10:00:00']);
    $fourth = ($this->post)(['published_at' => '2026-01-04 10:00:00']);

    $this->stops->save($this->blogId, $second, ($this->place)('Madrid', 2));
    $this->stops->save($this->blogId, $third, ($this->place)('Toledo'));
    $this->stops->save($this->blogId, $first, ($this->place)('Lisbon', 1));
    $this->stops->save($this->blogId, $fourth, ($this->place)('Porto'));

    expect(($this->publishedIds)())->toBe([$first, $second, $third, $fourth])
        ->and($this->stops->published($this->blogId)[0])->toHaveKeys([
            'title', 'slug', 'excerpt', 'featured_image', 'place_name', 'lat', 'lng', 'place_precision', 'stop_number',
        ]);
});

test('stops on posts that are not public are left out', function () {
    $public = ($this->post)();
    $hidden = [
        ($this->post)(['status' => 'draft']),
        ($this->post)(['status' => 'scheduled']),
        ($this->post)(['status' => 'archived']),
        ($this->post)(['status' => 'moderated']),
        ($this->post)(['visibility' => 'private']),
        ($this->post)(['visibility' => 'unlisted']),
    ];

    foreach ([$public, ...$hidden] as $postId) {
        $this->stops->save($this->blogId, $postId, ($this->place)('Somewhere'));
    }

    expect(($this->publishedIds)())->toBe([$public]);
});

test('the owner list has every stop with its post status, unpublished posts last', function () {
    $draft = ($this->post)(['status' => 'draft', 'published_at' => null, 'title' => 'Still writing']);
    $public = ($this->post)(['published_at' => '2026-01-02 10:00:00', 'title' => 'Out now']);
    $this->stops->save($this->blogId, $draft, ($this->place)('Faro'));
    $this->stops->save($this->blogId, $public, ($this->place)('Lisbon'));

    $list = $this->stops->forBlog($this->blogId);

    expect(array_column($list, 'title'))->toBe(['Out now', 'Still writing'])
        ->and(array_column($list, 'status'))->toBe(['published', 'draft'])
        ->and($list[0])->toHaveKeys(['post_id', 'place_name', 'place_precision', 'stop_number', 'visibility']);
});

test('next after a stop is the following public stop, and after the last there is none', function () {
    $first = ($this->post)();
    $second = ($this->post)();
    $this->stops->save($this->blogId, $first, ($this->place)('Lisbon', 1));
    $this->stops->save($this->blogId, $second, ($this->place)('Madrid', 2));

    expect((int) $this->stops->nextAfter($this->blogId, $first)['post_id'])->toBe($second)
        ->and($this->stops->nextAfter($this->blogId, $second))->toBeNull();
});

test('saving twice updates the one row', function () {
    $postId = ($this->post)();
    $this->stops->save($this->blogId, $postId, ($this->place)('Lisbon'));
    $this->stops->save($this->blogId, $postId, ($this->place)('Porto', 4));

    $count = (int) $this->db->query('SELECT COUNT(*) FROM journey_stops WHERE post_id = ?', [$postId])->fetchColumn();

    expect($count)->toBe(1)
        ->and($this->stops->find($postId)['place_name'])->toBe('Porto')
        ->and((int) $this->stops->find($postId)['stop_number'])->toBe(4);
});

test('post in blog is false for a post of another blog', function () {
    $otherBlog = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $foreign = ($this->post)([], $otherBlog);

    expect($this->stops->postInBlog($foreign, $this->blogId))->toBeFalse()
        ->and($this->stops->postInBlog(($this->post)(), $this->blogId))->toBeTrue();
});

test('removing a stop and deleting its post both clear the row', function () {
    $kept = ($this->post)();
    $deleted = ($this->post)();
    $this->stops->save($this->blogId, $kept, ($this->place)('Lisbon'));
    $this->stops->save($this->blogId, $deleted, ($this->place)('Porto'));

    $this->stops->remove($kept);
    $this->db->execute('DELETE FROM posts WHERE id = ?', [$deleted]);

    expect($this->stops->find($kept))->toBeNull()
        ->and($this->stops->find($deleted))->toBeNull();
});

test('usage counts stops and public stops per blog with the module on', function () {
    $blogModules = new BlogModuleModel($this->db);
    $blogModules->enable($this->blogId, 'journey-map');
    $quietBlog = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $blogModules->enable($quietBlog, 'journey-map');
    $offBlog = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $this->stops->save($offBlog, ($this->post)([], $offBlog), ($this->place)('Seville'));

    $this->stops->save($this->blogId, ($this->post)(), ($this->place)('Lisbon'));
    $this->stops->save($this->blogId, ($this->post)(['status' => 'draft']), ($this->place)('Porto'));

    $usage = array_column($this->stops->usage(), null, 'blog_id');

    expect(array_keys($usage))->toEqualCanonicalizing([$this->blogId, $quietBlog])
        ->and($usage[$this->blogId])->toMatchArray(['stops' => 2, 'public_stops' => 1])
        ->and($usage[$this->blogId]['last_added'])->not->toBeNull()
        ->and($usage[$quietBlog])->toMatchArray(['stops' => 0, 'public_stops' => 0, 'last_added' => null]);
});

test('outside lists stops beyond the box', function () {
    (new BlogModuleModel($this->db))->enable($this->blogId, 'journey-map');
    $at = fn (string $name, string $lat, string $lng): Stop => Stop::fromInput([
        'place_name' => $name, 'lat' => $lat, 'lng' => $lng, 'precision' => 'exact', 'stop_number' => '',
    ]);
    $this->stops->save($this->blogId, ($this->post)(), $at('Lisbon', '38.72', '-9.14'));
    $tokyo = ($this->post)(['title' => 'Tokyo days']);
    $this->stops->save($this->blogId, $tokyo, $at('Tokyo', '35.68', '139.69'));

    $outside = $this->stops->outside([-32.0, 34.0, 45.0, 72.0]);

    expect($outside)->toHaveCount(1)
        ->and($outside[0])->toMatchArray(['post_id' => $tokyo, 'title' => 'Tokyo days', 'place_name' => 'Tokyo'])
        ->and($outside[0]['blog_name'])->toBe($this->blogs->getBlog($this->blogId)->toArray()['blog_name']);
});
