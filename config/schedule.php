<?php

declare(strict_types=1);

/**
 * Task scheduler configuration.
 *
 * Cron only ever calls one command:
 *
 *   * * * * * cd /path/to/app && php cli schedule:run >> /dev/null 2>&1
 *
 * Everything else is set up in the control panel under System. These values
 * are the bounds the dispatcher works inside, not the schedules themselves.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Run budget
    |--------------------------------------------------------------------------
    |
    | The dispatcher stops handing out work once a tick has been going this
    | long, so a busy minute cannot bleed into the next one. Anything it did
    | not reach keeps its place and comes up again immediately. Ten seconds of
    | headroom inside a sixty second cron interval is about right.
    |
    */
    'run_budget_seconds' => (int) ($_ENV['SCHEDULE_RUN_BUDGET'] ?? 50),

    /*
    |--------------------------------------------------------------------------
    | Tasks per tick
    |--------------------------------------------------------------------------
    |
    | Ceiling on how many tasks one tick will claim. Guards against a long
    | outage coming back and trying to start everything at once.
    |
    */
    'max_tasks_per_tick' => (int) ($_ENV['SCHEDULE_MAX_TASKS'] ?? 20),

    /*
    |--------------------------------------------------------------------------
    | Reap grace
    |--------------------------------------------------------------------------
    |
    | Extra seconds allowed on top of a task own timeout before its claim is
    | treated as abandoned. Covers the gap between claiming a task and the
    | child process actually starting.
    |
    */
    'reap_grace_seconds' => (int) ($_ENV['SCHEDULE_REAP_GRACE'] ?? 30),

    /*
    |--------------------------------------------------------------------------
    | History retention
    |--------------------------------------------------------------------------
    |
    | Days of run history kept by schedule:prune-runs. Rows still marked
    | running are never dropped, however old, because one of those means a
    | process disappeared without reporting and deleting it would hide that.
    |
    */
    'history_days' => (int) ($_ENV['SCHEDULE_HISTORY_DAYS'] ?? 30),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat warning
    |--------------------------------------------------------------------------
    |
    | How long the panel waits before reporting the scheduler as not running.
    | The dispatcher writes its heartbeat on every tick including empty ones,
    | so a quiet scheduler and a dead one never look the same.
    |
    */
    'heartbeat_warn_after' => (int) ($_ENV['SCHEDULE_HEARTBEAT_WARN'] ?? 300),

    /*
    |--------------------------------------------------------------------------
    | PHP binary
    |--------------------------------------------------------------------------
    |
    | Which executable starts a detached task. Worth being explicit about,
    | because PHP_BINARY under a web server points at Apache or php-fpm rather
    | than the command line build, so the Run Now button would otherwise try to
    | start the wrong program. Left empty, the runner uses PHP_BINARY on the
    | command line and falls back to the php in PHP_BINDIR on the web.
    |
    */
    'php_binary' => $_ENV['SCHEDULE_PHP_BINARY'] ?? '',

    /*
    |--------------------------------------------------------------------------
    | Pseudo cron
    |--------------------------------------------------------------------------
    |
    | Off by default. When on, a visitor arriving more than a minute after the
    | last tick quietly kicks off a dispatch in the background. It is a
    | fallback for hosts with no crontab and it is worth knowing what it cannot
    | do: cached pages never reach PHP, so a quiet site may not tick at all.
    | Real cron stays the supported path.
    |
    */
    'pseudo_cron' => filter_var($_ENV['SCHEDULE_PSEUDO_CRON'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
