<?php

declare(strict_types=1);

use App\Console\Commands\SessionPruneCommand;
use App\Models\UserModel;
use Tests\Factories\UserFactory;
use Tests\Helpers\SessionTestHelper;

test('expired sessions are deleted and live ones stay', function () {
    $userId = UserFactory::new(new UserModel($this->db))->create();
    $live = SessionTestHelper::signedInRow($this->db, $userId);
    $expired = SessionTestHelper::signedInRow($this->db, $userId);
    $this->db->execute('UPDATE user_sessions SET expires_at = ? WHERE id = ?', [time() - 60, $expired]);

    ob_start();
    $code = (new SessionPruneCommand(SessionTestHelper::registry($this->db)))->handle();
    $output = ob_get_clean();

    $registry = SessionTestHelper::registry($this->db);
    expect($code)->toBe(0)
        ->and($output)->toContain('Expired sessions deleted: 1')
        ->and($registry->find($expired))->toBeNull()
        ->and($registry->find($live))->not->toBeNull();
});
