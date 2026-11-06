<?php

declare(strict_types=1);

use App\Auth;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use Framework\Modules\SlotContext;
use Modules\JourneyMap\EditorPanelSlot;
use Modules\JourneyMap\MapData;
use Modules\JourneyMap\SearchSettings;
use Modules\JourneyMap\Stop;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * The Place panel shows in the post editor for people who may change the blog.
 */
beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $users = new UserModel($this->db);
    $this->ownerId = UserFactory::new($users)->create();
    $this->strangerId = UserFactory::new($users)->create();
    $this->blogs = new BlogModel($this->db);
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $this->postId = PostFactory::new(new PostModel($this->db))
        ->published()
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $this->blogId])
        ->create();
    $this->stops = new StopModel($this->db);
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    $this->settings = new SearchSettings(new SettingModel($this->db));

    $this->slot = function (int $userId): EditorPanelSlot {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('user')->andReturn(['id' => $userId, 'roles' => [], 'permissions' => []]);

        $map = new MapData(sys_get_temp_dir().'/no-map-here');

        return new EditorPanelSlot($auth, $this->blogs, $this->stops, $map, $this->settings);
    };
    $this->context = fn (?int $postId): SlotContext => new SlotContext(
        'editor.sidebar',
        $this->blogId,
        $this->blogs->getBlog($this->blogId)->toArray(),
        $postId === null ? [] : ['id' => $postId],
        'edit'
    );
});

afterEach(function () {
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('the owner sees the panel', function () {
    $slot = ($this->slot)($this->ownerId);
    $context = ($this->context)($this->postId);

    expect($slot->shouldRender($context))->toBeTrue()
        ->and($slot->render($context))->toContain('data-journey-map-editor')
        ->and($slot->render($context))->toContain('/m/journey-map/stops/'.$this->postId);
});

test('someone who cannot change the blog does not see it', function () {
    expect(($this->slot)($this->strangerId)->shouldRender(($this->context)($this->postId)))->toBeFalse();
});

test('a new post shows the save first message', function () {
    expect(($this->slot)($this->ownerId)->render(($this->context)(null)))
        ->toContain('Save the post first to give it a place.')
        ->not->toContain('data-journey-map-editor');
});

test('the panel links the blog guide, also before the first save', function () {
    $guide = 'href="'.lurl('/dashboard/blog/'.$this->blogId.'/m/journey-map').'"';

    expect(($this->slot)($this->ownerId)->render(($this->context)($this->postId)))->toContain($guide)
        ->and(($this->slot)($this->ownerId)->render(($this->context)(null)))->toContain($guide);
});

test('place names are escaped', function () {
    $this->stops->save($this->blogId, $this->postId, Stop::fromInput([
        'place_name' => "Tom & Jerry's",
        'lat' => '1',
        'lng' => '2',
        'precision' => 'exact',
        'stop_number' => '',
    ]));

    expect(($this->slot)($this->ownerId)->render(($this->context)($this->postId)))
        ->toContain('Tom &amp; Jerry&#039;s')
        ->not->toContain("Tom & Jerry's");
});

test('without the map file the panel loads no map scripts', function () {
    expect(($this->slot)($this->ownerId)->assets())->toBe(['css' => ['editor.css'], 'js' => ['editor.js']]);
});

test('the panel has no search box while search is off, coordinates stay', function () {
    $this->settings->save(false, '', '');

    $html = ($this->slot)($this->ownerId)->render(($this->context)($this->postId));

    expect($html)->not->toContain('data-field="query"')
        ->and($html)->toContain('data-field="lat"')
        ->and($html)->toContain('data-field="lng"');
});
