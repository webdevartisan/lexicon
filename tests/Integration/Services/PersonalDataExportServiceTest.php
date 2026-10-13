<?php

declare(strict_types=1);

use App\Models\AnalyticsSaltModel;
use App\Models\UserModel;
use App\Services\Analytics\VisitorIdentity;
use App\Services\PersonalDataExportService;
use Tests\Factories\UserFactory;
use Tests\Helpers\SessionTestHelper;

test('the export holds the account data and no secrets', function () {
    $users = new UserModel($this->db);
    $userId = UserFactory::new($users)->withAttributes(['email' => 'me@example.test'])->create();
    $this->db->execute('INSERT INTO user_social_links (user_id, network, url) VALUES (?, ?, ?)', [$userId, 'github', 'https://github.com/me']);

    $export = (new PersonalDataExportService($this->db, new VisitorIdentity(new AnalyticsSaltModel($this->db), 'test-key')))->export($userId);
    $json = json_encode($export, JSON_THROW_ON_ERROR);

    expect($export['account']['email'])->toBe('me@example.test')
        ->and($export['social_links'][0]['url'])->toBe('https://github.com/me')
        ->and($json)->not->toContain('$2y$')
        ->and($json)->not->toContain('"token"');
});

test('the export lists signed-in devices without session keys or support sessions', function () {
    $users = new UserModel($this->db);
    $userId = UserFactory::new($users)->create();
    $adminId = UserFactory::new($users)->admin()->create();
    $hash = SessionTestHelper::signedInRow($this->db, $userId, ['city' => 'Patras']);
    SessionTestHelper::signedInRow($this->db, $userId, ['impersonated_by' => $adminId]);

    $export = (new PersonalDataExportService($this->db, new VisitorIdentity(new AnalyticsSaltModel($this->db), 'test-key')))->export($userId);

    expect($export['signed_in_sessions'])->toHaveCount(1)
        ->and($export['signed_in_sessions'][0]['city'])->toBe('Patras')
        ->and($export['signed_in_sessions'][0]['signed_in_at'])->toEndWith('Z')
        ->and(json_encode($export, JSON_THROW_ON_ERROR))->not->toContain($hash);
});
