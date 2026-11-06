<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\ModuleModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Modules\ModuleDuties;
use Framework\Modules\ModuleRegistry;
use Modules\JourneyMap\Stop;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * Stops follow the blog: they go when it is deleted and travel with its backup.
 */
beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();
    $blogs = new BlogModel($this->db);
    $this->blogA = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $this->blogB = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $this->stops = new StopModel($this->db);

    $modules = new ModuleModel($this->db);
    $this->duties = new ModuleDuties(
        new ModuleRegistry(ModuleFixtures::REAL, ModuleFixtures::cacheFile(), fn () => $modules->states()),
        ModuleFixtures::instances($this->db)
    );
    $this->post = fn (int $blogId): int => PostFactory::new(new PostModel($this->db))
        ->published()
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $blogId])
        ->create();
    $this->stop = fn (string $name): Stop => Stop::fromInput([
        'place_name' => $name,
        'lat' => '48.858370',
        'lng' => '2.294481',
        'precision' => 'exact',
        'stop_number' => '',
    ]);
});

afterEach(function () {
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('deleting a blog through the module duties leaves no stops', function () {
    $this->stops->save($this->blogA, ($this->post)($this->blogA), ($this->stop)('Paris'));
    $this->stops->save($this->blogB, ($this->post)($this->blogB), ($this->stop)('Lyon'));

    $this->duties->eraseBlog($this->blogA);

    expect($this->stops->hasBlogData($this->blogA))->toBeFalse()
        ->and($this->stops->hasBlogData($this->blogB))->toBeTrue();
});

test('a backup round trip maps post ids', function () {
    $from = [($this->post)($this->blogA), ($this->post)($this->blogA)];
    $to = [($this->post)($this->blogB), ($this->post)($this->blogB)];
    $this->stops->save($this->blogA, $from[0], ($this->stop)('Paris'));
    $this->stops->save($this->blogA, $from[1], ($this->stop)('Lyon'));

    $result = $this->duties->importBlog(
        $this->blogB,
        $this->duties->exportBlog($this->blogA),
        [$from[0] => $to[0], $from[1] => $to[1]]
    );

    expect($result['imported'])->toBe(['journey-map' => 2])
        ->and($this->stops->find($to[0])['place_name'])->toBe('Paris')
        ->and((int) $this->stops->find($to[1])['blog_id'])->toBe($this->blogB);
});

test('restored area stops are rounded and junk rows skipped', function () {
    $from = ($this->post)($this->blogA);
    $to = ($this->post)($this->blogB);
    $row = [
        'post_id' => $from,
        'place_name' => 'Paris',
        'lat' => 48.858370,
        'lng' => 2.294481,
        'place_precision' => 'area',
        'stop_number' => null,
    ];

    $result = $this->duties->importBlog($this->blogB, ['journey-map' => ['version' => 1, 'rows' => [
        $row,
        'not a row',
        ['lat' => 500] + $row,
        ['post_id' => 999999] + $row,
    ]]], [$from => $to]);

    expect($result['imported'])->toBe(['journey-map' => 1])
        ->and((float) $this->stops->find($to)['lat'])->toBe(48.86)
        ->and((float) $this->stops->find($to)['lng'])->toBe(2.29);
});
