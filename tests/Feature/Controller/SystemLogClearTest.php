<?php

declare(strict_types=1);

use App\Controllers\Admin\SystemController;
use App\Models\UserModel;
use App\Services\LogFileService;
use Framework\Core\App;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\UserFactory;

/**
 * The clear-log action refuses anyone who is not an administrator before it
 * touches the file.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(\Framework\Database::class);
    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lexicon-clear-'.uniqid();
    mkdir($this->dir);
    file_put_contents($this->dir.'/cron.log', "Started 1 task(s).\n");

    $email = faker()->unique()->safeEmail();
    UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $request = makeRequest('/admin/system/logs/clear', 'POST', [
        '_token' => App::container()->get(Csrf::class)->getToken(),
        'log' => 'cron.log',
    ]);

    $this->controller = new SystemController($this->db, new LogFileService($this->dir));
    setupController($this->controller, $request, Mockery::mock(TemplateViewerInterface::class));
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
    $_SESSION = [];
    auth()->logout();
});

it('refuses a user who is not an administrator and leaves the log intact', function () {
    expect(fn () => $this->controller->clearLog())->toThrow(UnauthorizedException::class);

    clearstatcache();
    expect(filesize($this->dir.'/cron.log'))->toBeGreaterThan(0);
});
