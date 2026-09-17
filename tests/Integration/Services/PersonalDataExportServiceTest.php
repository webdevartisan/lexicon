<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Services\PersonalDataExportService;
use Tests\Factories\UserFactory;

test('the export holds the account data and no secrets', function () {
    $users = new UserModel($this->db);
    $userId = UserFactory::new($users)->withAttributes(['email' => 'me@example.test'])->create();
    $this->db->execute('INSERT INTO user_social_links (user_id, network, url) VALUES (?, ?, ?)', [$userId, 'github', 'https://github.com/me']);

    $export = (new PersonalDataExportService($this->db))->export($userId);
    $json = json_encode($export, JSON_THROW_ON_ERROR);

    expect($export['account']['email'])->toBe('me@example.test')
        ->and($export['social_links'][0]['url'])->toBe('https://github.com/me')
        ->and($json)->not->toContain('$2y$')
        ->and($json)->not->toContain('"token"');
});
