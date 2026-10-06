<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsEventModel;
use Framework\Core\Request;
use Framework\Interfaces\ServerErrorReporterInterface;

/**
 * Counts each 5xx answer as a server_error event for the control panel's
 * Technical page: the path and the status, nothing about who asked, no query
 * string and no body. The rows go with the other raw events.
 */
final class ServerErrorRecorder implements ServerErrorReporterInterface
{
    public function __construct(
        private AnalyticsSettings $settings,
        private AnalyticsEventModel $events,
    ) {}

    public function report(Request $request, int $status): void
    {
        if ($status < 500 || $status > 599) {
            return;
        }

        try {
            if (!$this->settings->enabled()) {
                return;
            }

            $path = mb_substr($request->path(), 0, 255);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            $this->events->record([
                'event_key' => random_bytes(16),
                'name' => 'server_error',
                'path' => $path,
                'path_hash' => substr(hash('sha256', $path, true), 0, 8),
                'props' => ['status' => $status],
                'local_date' => $now->format('Y-m-d'),
                'local_hour' => (int) $now->format('G'),
            ]);
        } catch (\Throwable $e) {
            // The database may be the reason for the error, and the error page must still go out.
            error_log('Server error not counted for Insights: '.$e->getMessage());
        }
    }
}
