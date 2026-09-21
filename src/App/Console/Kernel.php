<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CacheClearCommand;
use App\Console\Commands\CachePruneCommand;
use App\Console\Commands\CacheWarmCommand;
use App\Console\Commands\KeyGenerateCommand;
use App\Console\Commands\LiftDueSuspensionsCommand;
use App\Console\Commands\MailQueueWorkCommand;
use App\Console\Commands\NotificationPruneCommand;
use App\Console\Commands\PrivacyPruneCommand;
use App\Console\Commands\ProcessDueErasuresCommand;
use App\Console\Commands\PublishDuePostsCommand;
use App\Console\Commands\SanitizePostContentCommand;
use App\Console\Commands\SchedulePruneRunsCommand;
use App\Console\Commands\ScheduleRunCommand;
use App\Console\Commands\ScheduleRunTaskCommand;
use App\Console\Commands\SeedCommand;
use App\Console\Commands\SeedModerationCommand;
use Framework\Console\Kernel as ConsoleKernel;

/**
 * Application Console Kernel
 *
 * We extend the framework's base console kernel and register
 * application-specific commands. This keeps command registration
 * in the application layer where it belongs.
 *
 * Add new commands here as your application grows.
 */
class Kernel extends ConsoleKernel
{
    /**
     * Register application commands.
     *
     * We map command names to their handler classes.
     * The framework kernel will automatically load and route these.
     *
     * @return array<string, class-string>
     */
    protected function commands(): array
    {
        return [
            // Cache management commands
            'cache:clear' => CacheClearCommand::class,
            'cache:prune' => CachePruneCommand::class,
            'cache:warm' => CacheWarmCommand::class,
            'key:generate' => KeyGenerateCommand::class,

            // Notification management
            'notifications:prune' => NotificationPruneCommand::class,

            // Deletes personal data past the retention periods in config/privacy.php
            'privacy:prune' => PrivacyPruneCommand::class,

            // Runs self-requested erasures whose grace period has passed
            'privacy:process-due-erasures' => ProcessDueErasuresCommand::class,

            // Ends temporary suspensions once their time is up, restoring the cascade
            'users:lift-due-suspensions' => LiftDueSuspensionsCommand::class,

            // Promotes scheduled posts once published_at arrives; run every minute
            'posts:publish-due' => PublishDuePostsCommand::class,

            // Runs stored post bodies through the content sanitizer; reports unless --apply
            'posts:sanitize-content' => SanitizePostContentCommand::class,

            // Delivers queued email at a paced rate, scheduled from the panel
            'mail:queue-work' => MailQueueWorkCommand::class,

            // The only entry cron needs. Everything else is configured under
            // System, Scheduled Tasks.
            // * * * * * cd /var/www/html && php cli schedule:run >> storage/logs/cron.log 2>&1
            'schedule:run' => ScheduleRunCommand::class,

            // Started by schedule:run for one task, not meant to be run by hand
            'schedule:run-task' => ScheduleRunTaskCommand::class,

            'schedule:prune-runs' => SchedulePruneRunsCommand::class,

            // Populates the database with realistic fake content for local dev
            'db:seed' => SeedCommand::class,

            // Moderation cases in every state, through the real services (--reset clears them)
            'moderation:seed' => SeedModerationCommand::class,

            // 'db:migrate'    => MigrateCommand::class,
            // 'make:controller' => MakeControllerCommand::class,
        ];
    }
}
