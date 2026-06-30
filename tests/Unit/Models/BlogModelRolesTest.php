<?php

declare(strict_types=1);

use App\Models\BlogModel;

test('ROLES does not contain viewer', function () {
    expect(BlogModel::ROLES)->not->toContain('viewer');
});

test('ROLES contains the four active collaborative roles', function () {
    expect(BlogModel::ROLES)->toBe(['editor', 'author', 'contributor', 'reviewer']);
});
