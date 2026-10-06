<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\AnalyticsDailyEventModel;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\Analytics\InsightsSavedView;
use App\Services\Analytics\SignupReportService;
use App\ValueObjects\AnalyticsRange;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Insights > Sign-ups in the control panel: how many visitors sign up, where
 * those visits came from, and how many new accounts go on to read or write.
 *
 * Days are UTC.
 */
class SignupController extends AppController
{
    protected ?string $areaAbility = 'viewPlatformAnalytics';

    public function __construct(
        private SignupReportService $reports,
        private AnalyticsSettings $settings,
        private InsightsSavedView $savedView,
    ) {}

    public function index(): Response
    {
        $range = $this->savedView->range((int) auth()->user()['id'], 'signups', $this->request->get, 'UTC');

        return $this->view('signup.index', [
            'range' => $range,
            'report' => $this->reports->report($range),
            'windowDays' => SignupReportService::WINDOW_DAYS,
            'today' => (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d'),
            'trackingEnabled' => $this->settings->enabled(),
            'basePath' => '/admin/insights/signups',
            'scope' => 'site',
            'post' => null,
        ]);
    }

    /**
     * One sign-up list for the chosen range as CSV.
     */
    public function export(): Response
    {
        $dimension = (string) ($this->request->get['dimension'] ?? '');

        if (!in_array($dimension, AnalyticsDailyEventModel::breakdowns(), true)) {
            throw new PageNotFoundException('Unknown sign-up breakdown.');
        }

        $range = AnalyticsRange::fromQuery($this->request->get, 'UTC');
        $rows = array_map(
            static fn (array $row): array => [$row['name'] ?? $row['value'], $row['signups']],
            $this->reports->fullBreakdown($dimension, $range)
        );

        return $this->csv("signups-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv", [$dimension, 'signups'], $rows);
    }
}
