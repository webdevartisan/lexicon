<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\UserPreferencesModel;
use App\ValueObjects\AnalyticsRange;

/**
 * Where each user left Insights: the last page and the last preset range. The
 * Insights link resumes on that page, and a page opened without a range shows
 * the one picked last. Custom dates are not kept, since they go stale.
 */
final class InsightsSavedView
{
    /** @var array<int, array{page: ?string, range: ?string}> */
    private array $saved = [];

    public function __construct(private UserPreferencesModel $preferences) {}

    public function lastPage(int $userId): ?string
    {
        return $this->saved($userId)['page'];
    }

    /**
     * The range the address asks for, or the one picked last when it names none.
     * Either is kept for next time, along with the page.
     *
     * @param  string|null  $page  Null for a post's page, which leaves the last page as it was
     * @param  array<string, mixed>  $query  The request's query parameters
     */
    public function range(int $userId, ?string $page, array $query, string $timezone): AnalyticsRange
    {
        $last = $this->saved($userId)['range'];

        if (!isset($query['range']) && $last !== null) {
            $query = ['range' => $last] + $query;
        }

        $range = AnalyticsRange::fromQuery($query, $timezone);
        $this->remember($userId, $page, $range);

        return $range;
    }

    private function remember(int $userId, ?string $page, AnalyticsRange $range): void
    {
        $saved = $this->saved($userId);
        $changes = [];

        if ($page !== null && $page !== $saved['page']) {
            $changes['insights_page'] = $page;
        }

        if ($range->preset !== 'custom' && !$range->rejected && $range->preset !== $saved['range']) {
            $changes['insights_range'] = $range->preset;
        }

        if ($changes === []) {
            return;
        }

        $this->preferences->upsert($userId, $changes);
        $this->saved[$userId] = [
            'page' => $changes['insights_page'] ?? $saved['page'],
            'range' => $changes['insights_range'] ?? $saved['range'],
        ];
    }

    /**
     * @return array{page: ?string, range: ?string}
     */
    private function saved(int $userId): array
    {
        if (!isset($this->saved[$userId])) {
            $row = $this->preferences->findOrCreate($userId);
            $this->saved[$userId] = [
                'page' => isset($row['insights_page']) ? (string) $row['insights_page'] : null,
                'range' => isset($row['insights_range']) ? (string) $row['insights_range'] : null,
            ];
        }

        return $this->saved[$userId];
    }
}
