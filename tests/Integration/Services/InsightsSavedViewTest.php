<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\Analytics\InsightsSavedView;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->userId = UserFactory::new(new UserModel($this->db))->create();
    $this->preferences = new UserPreferencesModel($this->db);
    $this->preferences->upsert($this->userId, ['timezone' => 'Europe/Athens', 'notify_insights_digest' => 0]);
    $this->savedView = static fn (UserPreferencesModel $preferences): InsightsSavedView => new InsightsSavedView($preferences);
});

test('the page and the preset range are kept for next time, and nothing else changes', function () {
    ($this->savedView)($this->preferences)->range($this->userId, 'audience', ['range' => '7d'], 'UTC');

    $row = $this->preferences->findOrCreate($this->userId);

    expect([$row['insights_page'], $row['insights_range']])->toBe(['audience', '7d'])
        ->and([$row['timezone'], (int) $row['notify_insights_digest']])->toBe(['Europe/Athens', 0])
        ->and(($this->savedView)($this->preferences)->lastPage($this->userId))->toBe('audience');
});

test('a page opened without a range shows the one picked last', function () {
    ($this->savedView)($this->preferences)->range($this->userId, 'audience', ['range' => '90d'], 'UTC');

    $range = ($this->savedView)($this->preferences)->range($this->userId, 'content', [], 'UTC');

    expect($range->preset)->toBe('90d')
        ->and($this->preferences->findOrCreate($this->userId)['insights_page'])->toBe('content');
});

test('custom dates and a range that was refused are not kept', function () {
    ($this->savedView)($this->preferences)->range($this->userId, 'audience', ['range' => '30d'], 'UTC');
    ($this->savedView)($this->preferences)->range($this->userId, 'audience', ['range' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-07'], 'UTC');
    ($this->savedView)($this->preferences)->range($this->userId, 'audience', ['range' => 'forever'], 'UTC');

    expect($this->preferences->findOrCreate($this->userId)['insights_range'])->toBe('30d');
});

test('a post page keeps the range but leaves the last page alone', function () {
    ($this->savedView)($this->preferences)->range($this->userId, 'goals', ['range' => '30d'], 'UTC');
    ($this->savedView)($this->preferences)->range($this->userId, null, ['range' => '12m'], 'UTC');

    $row = $this->preferences->findOrCreate($this->userId);

    expect([$row['insights_page'], $row['insights_range']])->toBe(['goals', '12m']);
});
