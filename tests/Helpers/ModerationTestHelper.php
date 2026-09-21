<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\ContentReportModel;
use App\Models\MailQueueModel;
use App\Models\ModerationCaseModel;
use App\Models\ModerationCategoryModel;
use App\Models\NotificationModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\AuditService;
use App\Services\ImpersonationService;
use App\Services\MailQueueService;
use App\Services\MailService;
use App\Services\ModerationActionService;
use App\Services\ModerationCaseService;
use App\Services\ModerationRuleEngine;
use App\Services\ModerationSettings;
use App\Services\PublicCacheInvalidator;
use App\Services\ReporterStandingService;
use App\Services\ReportIntakeService;
use App\Services\UserSuspensionService;
use Framework\Database;
use Mockery;

/**
 * Wires the moderation services against the test database.
 *
 * Integration tests cannot resolve them from the container, which holds the
 * development connection, so this builds the same graph by hand.
 */
final class ModerationTestHelper
{
    public const DELETED_USER_HANDLE = 'deleted-user';

    /**
     * @param  array<string, string>  $settings  Moderation settings to save first, e.g. a zero burst window
     * @param  ModerationActionService|null  $actions  A stand-in, for tests that make an action fail
     * @return array{intake: ReportIntakeService, engine: ModerationRuleEngine, actions: ModerationActionService, moderation: ModerationCaseService, cases: ModerationCaseModel, reports: ContentReportModel, suspensions: UserSuspensionService}
     */
    public static function services(Database $db, array $settings = [], ?ModerationActionService $actions = null): array
    {
        $settingModel = new SettingModel($db);

        foreach ($settings as $name => $value) {
            $settingModel->set($name, $value);
        }

        $cases = new ModerationCaseModel($db);
        $reports = new ContentReportModel($db);
        $categories = new ModerationCategoryModel($db);
        $users = new UserModel($db);
        $moderationSettings = new ModerationSettings($settingModel);
        $suspensions = self::suspensions($db);
        $actions ??= self::actions($db, $cases, $suspensions);

        $engine = new ModerationRuleEngine($db, $cases, $categories, $reports, $moderationSettings, $actions, $suspensions, $users);
        $intake = new ReportIntakeService($db, $categories, $cases, $reports, $moderationSettings, $engine, self::DELETED_USER_HANDLE);

        return [
            'intake' => $intake,
            'engine' => $engine,
            'actions' => $actions,
            'moderation' => new ModerationCaseService($db, $cases, $reports, $categories, $actions, self::standing($db)),
            'cases' => $cases,
            'reports' => $reports,
            'suspensions' => $suspensions,
        ];
    }

    public static function suspensions(Database $db): UserSuspensionService
    {
        return new UserSuspensionService(
            $db,
            Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing(),
            new CommentModel($db),
            new BlogModel($db),
        );
    }

    /**
     * The real action service, or a partial mock of it when $partial is set,
     * so a test can make one action throw while record() still writes.
     */
    public static function actions(
        Database $db,
        ?ModerationCaseModel $cases = null,
        ?UserSuspensionService $suspensions = null,
        bool $partial = false,
    ): ModerationActionService {
        $args = [
            $db,
            $cases ?? new ModerationCaseModel($db),
            new PostModel($db),
            new CommentModel($db),
            $suspensions ?? self::suspensions($db),
            self::audit($db),
            new UserModel($db),
            new NotificationModel($db),
            // The real queue model so tests can see the row; the mailer is never reached by enqueue().
            new MailQueueService(new MailQueueModel($db), Mockery::mock(MailService::class)),
        ];

        return $partial
            ? Mockery::mock(ModerationActionService::class, $args)->makePartial()
            : new ModerationActionService(...$args);
    }

    public static function standing(Database $db): ReporterStandingService
    {
        return new ReporterStandingService(
            $db,
            new UserModel($db),
            new ContentReportModel($db),
            new NotificationModel($db),
            new MailQueueService(new MailQueueModel($db), Mockery::mock(MailService::class)),
            self::audit($db),
            new ModerationSettings(new SettingModel($db)),
        );
    }

    public static function audit(Database $db): AuditService
    {
        $impersonation = Mockery::mock(ImpersonationService::class);
        $impersonation->shouldReceive('impersonatorId')->andReturn(null);

        return new AuditService($db, $impersonation);
    }

    /**
     * Age an account so its reports count toward a rule.
     */
    public static function season(Database $db, int $userId, int $days = 30): void
    {
        $db->execute('UPDATE users SET created_at = NOW() - INTERVAL ? DAY WHERE id = ?', [$days, $userId]);
    }

    /**
     * Snapshot the seeded categories so a test can change them. The table is
     * preserved between tests, so every change has to be put back.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function snapshotCategories(Database $db): array
    {
        return $db->query('SELECT * FROM moderation_categories')->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function restoreCategories(Database $db, array $rows): void
    {
        $db->execute('DELETE FROM moderation_categories');

        foreach ($rows as $row) {
            $columns = implode(', ', array_keys($row));
            $marks = implode(', ', array_fill(0, count($row), '?'));
            $db->execute("INSERT INTO moderation_categories ({$columns}) VALUES ({$marks})", array_values($row));
        }
    }

    /**
     * Audit rows written about a case, oldest first, with details decoded.
     *
     * @return array<int, array{action: string, user_id: int|null, details: array<string, mixed>}>
     */
    public static function caseAudit(Database $db, int $caseId): array
    {
        $rows = $db->query(
            "SELECT action, user_id, details FROM activity_log
              WHERE resource_type = 'moderation_case' AND resource_id = ?
              ORDER BY id",
            [$caseId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'action' => (string) $row['action'],
            'user_id' => $row['user_id'] === null ? null : (int) $row['user_id'],
            'details' => json_decode((string) $row['details'], true) ?? [],
        ], $rows);
    }
}
