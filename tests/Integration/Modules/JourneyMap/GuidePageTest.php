<?php

declare(strict_types=1);

use App\Auth;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Modules\JourneyMap\GuideController;
use Modules\JourneyMap\Stop;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * The blog's Journey map page in the dashboard: how to use it, and the blog's stops.
 */
beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $users = new UserModel($this->db);
    $this->ownerId = UserFactory::new($users)->create();
    $this->strangerId = UserFactory::new($users)->create();
    $this->blogs = new BlogModel($this->db);
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $this->blogModules = new BlogModuleModel($this->db);
    $this->blogModules->enable($this->blogId, 'journey-map');
    $this->stops = new StopModel($this->db);

    $this->postId = PostFactory::new(new PostModel($this->db))
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $this->blogId, 'status' => 'draft'])
        ->create();
    $this->stops->save($this->blogId, $this->postId, Stop::fromInput([
        'place_name' => 'Faro',
        'lat' => '37.0',
        'lng' => '-7.9',
        'precision' => 'area',
        'stop_number' => '',
    ]));

    $this->open = function (int $userId): array {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('user')->andReturn(['id' => $userId, 'roles' => [], 'permissions' => []]);
        $seen = [];
        $viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
        $viewer->shouldReceive('render')->andReturnUsing(function ($template, $data) use (&$seen) {
            $seen = ['template' => $template, 'data' => $data];

            return '';
        });
        $controller = new GuideController($this->blogModules, $this->stops, $this->blogs, $auth);
        setupController($controller, makeRequest('/dashboard/blog/'.$this->blogId.'/m/journey-map'), $viewer);
        $controller->show((string) $this->blogId);

        return $seen;
    };
});

afterEach(function () {
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('the owner sees every stop with a link to edit its post', function () {
    $page = ($this->open)($this->ownerId);
    $slug = (string) $this->blogs->getBlog($this->blogId)->toArray()['blog_slug'];

    expect($page['template'])->toBe('journey-map::guide')
        ->and($page['data']['stops'])->toHaveCount(1)
        ->and($page['data']['stops'][0])->toMatchArray([
            'place' => 'Faro',
            'precision' => 'area',
            'status' => 'draft',
            'edit' => '/dashboard/post/'.$this->postId.'/edit',
        ])
        ->and($page['data']['journeyUrl'])->toBe('/blog/'.$slug.'/m/journey-map');
});

test('the page is a 404 while the module is off for the blog', function () {
    $this->blogModules->disable($this->blogId, 'journey-map');

    expect(fn () => ($this->open)($this->ownerId))->toThrow(PageNotFoundException::class);
});

test('someone who cannot change the blog is refused', function () {
    expect(fn () => ($this->open)($this->strangerId))->toThrow(UnauthorizedException::class);
});
