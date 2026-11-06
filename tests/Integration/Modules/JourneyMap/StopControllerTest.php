<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\BlogCacheInvalidator;
use Framework\Exceptions\PageNotFoundException;
use Framework\Interfaces\TemplateViewerInterface;
use Modules\JourneyMap\StopController;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * The editor saves and removes a post's place through these endpoints. Who may call
 * them is settled by the manage-blog-extra route guard, which has its own tests.
 */
beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogs = new BlogModel($this->db);
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($ownerId);
    $this->slug = (string) $this->blogs->getBlog($this->blogId)->toArray()['blog_slug'];
    $this->post = fn (int $blogId): int => PostFactory::new(new PostModel($this->db))
        ->published()
        ->withAttributes(['author_id' => $ownerId, 'blog_id' => $blogId])
        ->create();
    $this->postId = ($this->post)($this->blogId);
    $this->stops = new StopModel($this->db);
    (new BlogModuleModel($this->db))->enable($this->blogId, 'journey-map');

    $this->blogCache = Mockery::mock(BlogCacheInvalidator::class)->shouldIgnoreMissing();
    $this->call = function (string $action, array $post = [], ?int $postId = null) {
        $request = makeRequest('/dashboard/blog/'.$this->blogId.'/m/journey-map/stops', 'POST', $post);
        $controller = new StopController(new BlogModuleModel($this->db), $this->stops, $this->blogs, $this->blogCache);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return $controller->$action((string) $this->blogId, (string) ($postId ?? $this->postId));
    };
    $this->place = [
        'place_name' => 'Eiffel Tower',
        'lat' => '48.858370',
        'lng' => '2.294481',
        'precision' => 'area',
        'stop_number' => '2',
    ];
});

afterEach(function () {
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('the owner saves an area stop and it is stored rounded', function () {
    $response = ($this->call)('save', $this->place);
    $stored = $this->stops->find($this->postId);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getBody(), true)['stop']['lat'])->toBe(48.86)
        ->and((float) $stored['lat'])->toBe(48.86)
        ->and((float) $stored['lng'])->toBe(2.29)
        ->and((int) $stored['stop_number'])->toBe(2);
});

test('bad input answers 422 with the field errors and stores nothing', function () {
    $response = ($this->call)('save', ['lat' => '91'] + $this->place);
    $body = json_decode($response->getBody(), true);

    expect($response->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe(['lat' => 'Latitude must be a number from -90 to 90.'])
        ->and($this->stops->find($this->postId))->toBeNull();
});

test('a post from another blog is a 404 and nothing changes', function () {
    $otherBlog = BlogFactory::new($this->blogs)->published()->create(
        UserFactory::new(new UserModel($this->db))->create()
    );
    $foreign = ($this->post)($otherBlog);

    expect(fn () => ($this->call)('save', $this->place, $foreign))->toThrow(PageNotFoundException::class)
        ->and(fn () => ($this->call)('remove', [], $foreign))->toThrow(PageNotFoundException::class)
        ->and($this->stops->find($foreign))->toBeNull();
});

test('remove deletes the stop', function () {
    ($this->call)('save', $this->place);

    $response = ($this->call)('remove');

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getBody(), true)['removed'])->toBeTrue()
        ->and($this->stops->find($this->postId))->toBeNull();
});

test('saving clears the blog page cache', function () {
    $this->blogCache = Mockery::mock(BlogCacheInvalidator::class);
    $this->blogCache->shouldReceive('forgetBlog')->once()->with($this->slug);

    expect(($this->call)('save', $this->place)->getStatusCode())->toBe(200);
});

test('the endpoints are a 404 while the module is off for the blog', function () {
    (new BlogModuleModel($this->db))->disable($this->blogId, 'journey-map');

    expect(fn () => ($this->call)('save', $this->place))->toThrow(PageNotFoundException::class);
});
