<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\TaskRunnerInterface;
use App\Models\SettingModel;
use Framework\Core\Request;
use Throwable;

/**
 * Keeps the scheduler moving on installs with no crontab, using page visits.
 *
 * Off by default and meant as a fallback, not a replacement. Real cron fires
 * whether or not anybody is looking, and this cannot, which is the whole of
 * the difference. WordPress made this the default and spent years dealing with
 * the consequences, so here it is something an operator turns on knowingly.
 *
 * Worth being plain about what it cannot do. A cached page never reaches PHP,
 * so on a quiet, heavily cached site this may almost never fire. A site with
 * no visitors overnight does no overnight work. Anything that has to happen at
 * a particular time needs real cron.
 *
 * What it does do is start the same detached tick that cron would, after the
 * response has already gone out, at most once a minute, and only when one
 * visitor out of however many arrive at that moment wins the claim.
 */
class PseudoCron
{
    /** Where the attempt lock lives. Deliberately not the heartbeat. */
    private const ATTEMPT_KEY = 'scheduler_pseudo_attempt';

    /** Matches the one minute resolution real cron would give. */
    private const MIN_INTERVAL = 60;

    /**
     * @param  array<string, mixed>  $config  config/schedule.php
     */
    public function __construct(
        private ScheduleService $schedule,
        private TaskRunnerInterface $runner,
        private SettingModel $settings,
        private array $config = [],
    ) {}

    /**
     * Start a tick if one looks overdue and this request is a good moment.
     *
     * Called after the response has been sent, and swallows everything. A
     * visitor is not the right person to hear about a scheduling problem, and
     * the panel heartbeat reports it properly.
     */
    public function maybeTick(Request $request): void
    {
        try {
            if (!$this->shouldConsider($request)) {
                return;
            }

            // The lock is its own setting rather than the heartbeat, because
            // the heartbeat has to keep telling the truth. Bumping it here
            // would leave a broken fallback looking healthy for ever, when the
            // whole point of the panel warning is to notice exactly that.
            if (!$this->settings->claimStale(self::ATTEMPT_KEY, self::MIN_INTERVAL)) {
                return;
            }

            $this->runner->dispatchTick();
        } catch (Throwable) {
            // Never let the fallback affect a page that has already rendered.
        }
    }

    /**
     * Whether the fallback is on, able to run, and wanted on this request.
     */
    private function shouldConsider(Request $request): bool
    {
        if (empty($this->config['pseudo_cron'])) {
            return false;
        }

        // Running a whole tick inline would hold this worker for as long as the
        // due tasks take, so without a detached runner the fallback stands down.
        if (!$this->runner->isDetached()) {
            return false;
        }

        // Ordinary page views only. Background calls are frequent, often fire
        // several at once, and are the last thing that should be starting work.
        if (!$request->isMethod('GET') || $request->isAjax()) {
            return false;
        }

        $age = $this->schedule->heartbeatAge();

        return $age === null || $age >= self::MIN_INTERVAL;
    }

    /**
     * Whether the fallback would actually do anything on this install.
     *
     * Used by the panel, so an operator who switched it on can see whether it
     * is genuinely running or quietly standing down.
     */
    public function isOperational(): bool
    {
        return !empty($this->config['pseudo_cron']) && $this->runner->isDetached();
    }

    /**
     * Whether the setting is on at all, regardless of whether it can act.
     */
    public function isEnabled(): bool
    {
        return !empty($this->config['pseudo_cron']);
    }
}
