<?php

declare(strict_types=1);

use App\Controllers\Admin\SettingController;
use App\Models\SettingModel;
use Framework\Core\App;
use Framework\Database;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Sign-ups always become readers and blog roles come from invitations, so the
 * settings form has no default role, and a post can't sneak one back in.
 *
 * The store is a mock because setMany() opens its own transaction, which can't
 * nest inside the test's.
 */
beforeEach(function () {
    $_SESSION = [];
    $this->settings = Mockery::mock(SettingModel::class);
    $this->settings->shouldReceive('get')->with('registration_enabled', '1')->andReturn('1');
    App::container()->set(SettingModel::class, fn () => $this->settings);

    $this->controller = App::container()->get(SettingController::class);
});

afterEach(function () {
    App::container()->set(SettingModel::class, fn ($c) => new SettingModel($c->get(Database::class)));
    $_SESSION = [];
    Mockery::close();
});

it('ignores a default role posted with the registration settings', function () {
    $this->settings->shouldReceive('setMany')->once()->with(['registration_enabled' => '1'])->andReturn(true);

    $request = makeRequest('/admin/settings', 'POST', [
        '_token' => csrf()->getToken(),
        'registration_enabled' => '1',
        'default_user_role' => '2',
    ]);
    setupController($this->controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

    $response = callController($this->controller, 'update', $request);

    expect($response->getHeader('Location'))->toContain('/admin/settings');
});
