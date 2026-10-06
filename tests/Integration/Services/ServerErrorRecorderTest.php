<?php

declare(strict_types=1);

use App\Models\AnalyticsEventModel;
use App\Models\SettingModel;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\Analytics\ServerErrorRecorder;
use Framework\Core\Request;

beforeEach(function () {
    $this->recorder = new ServerErrorRecorder(new AnalyticsSettings(new SettingModel($this->db)), new AnalyticsEventModel($this->db));
    $this->request = static fn (string $uri): Request => new Request(
        $uri, 'POST', [], ['password' => 'secret'], [], ['session' => 'abc'], ['REMOTE_ADDR' => '203.0.113.9'], ['user-agent' => 'Mozilla/5.0']
    );
});

test('a server error keeps the path and the status, and nothing about the request', function () {
    $this->recorder->report(($this->request)('/en/blog/demo/a-post?token=abc'), 503);

    $row = $this->db->query("SELECT * FROM analytics_events WHERE name = 'server_error'")->fetch(PDO::FETCH_ASSOC);
    $everything = implode('|', array_map('strval', $row));

    expect($row['path'])->toBe('/en/blog/demo/a-post')
        ->and(json_decode((string) $row['props'], true))->toBe(['status' => 503])
        ->and($row['visitor_hash'])->toBeNull()
        ->and($row['visit_id'])->toBeNull()
        ->and($row['blog_id'])->toBeNull()
        ->and($everything)->not->toContain('token')
        ->and($everything)->not->toContain('203.0.113.9')
        ->and($everything)->not->toContain('secret');
});

test('anything but a 5xx answer, or counting switched off, leaves nothing', function () {
    $this->recorder->report(($this->request)('/en/missing'), 404);
    (new SettingModel($this->db))->set('analytics.enabled', '0');
    $this->recorder->report(($this->request)('/en/blog/demo'), 500);

    expect((int) $this->db->query('SELECT COUNT(*) FROM analytics_events')->fetchColumn())->toBe(0);
});
