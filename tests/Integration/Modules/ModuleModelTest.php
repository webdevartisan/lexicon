<?php

declare(strict_types=1);

use App\Models\ModuleModel;

test('record switches a module on', function () {
    $modules = new ModuleModel($this->db);
    $modules->record('sample-extra', 1);

    expect($modules->states())->toBe(['sample-extra' => 'on']);
});

test('switchOff and switchOn change the site wide state, and switching on clears a pause reason', function () {
    $modules = new ModuleModel($this->db);
    $modules->record('sample-extra', 1);
    $modules->switchOff('sample-extra');
    expect($modules->states())->toBe(['sample-extra' => 'off']);

    $modules->pause('sample-extra', 'Too many errors');
    $modules->switchOn('sample-extra');

    expect($modules->states())->toBe(['sample-extra' => 'on'])
        ->and($modules->paused())->toBe([]);
});

test('recording a new version keeps a module that was switched off, off', function () {
    $modules = new ModuleModel($this->db);
    $modules->record('sample-extra', 1);
    $modules->switchOff('sample-extra');

    $modules->record('sample-extra', 2);

    expect($modules->states())->toBe(['sample-extra' => 'off']);
});

test('recording a new version resumes a paused module', function () {
    $modules = new ModuleModel($this->db);
    $modules->record('sample-extra', 1);
    $modules->pause('sample-extra', 'Too many errors');

    $modules->record('sample-extra', 2);

    expect($modules->states())->toBe(['sample-extra' => 'on'])
        ->and($modules->paused())->toBe([]);
});
