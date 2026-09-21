<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Models\CommentModel;
use App\Models\ContentReportModel;
use App\Models\ModerationCaseModel;
use App\Models\PostModel;
use App\Services\ModerationCaseService;
use App\Services\ReporterStandingService;
use App\Services\ReportIntakeService;
use Framework\Database;
use RuntimeException;

/**
 * Fills the moderation area with cases in every state, for trying it out.
 *
 * Unlike the other seeders it writes nothing directly. Reports go through
 * ReportIntakeService and decisions through the case and reporter services,
 * so rules, safeguards, priorities, audit rows, notifications and queued mail
 * all come out exactly as they would from real use. The only thing it fakes is
 * time: some reports are moved into the past so the burst safeguard sees them
 * spread out.
 */
final class ModerationSeeder
{
    private const COMMENTS = 5;

    private const POSTS = 6;

    // Only accounts on addresses reserved for examples (RFC 2606) take part, so
    // a queued warning can never reach a real inbox once mail is switched on.
    private const SAFE_EMAIL = "u.email REGEXP '@(example\\\\.(com|net|org)|[^@]+\\\\.test)$'";

    /** @var int[] */
    private array $pool = [];

    private int $adminId = 0;

    public function __construct(
        private Database $db,
        private ReportIntakeService $intake,
        private ModerationCaseService $cases,
        private ModerationCaseModel $caseModel,
        private ContentReportModel $reports,
        private ReporterStandingService $standing,
        private CommentModel $comments,
        private PostModel $posts,
    ) {}

    /**
     * @return string[] One line per case, for the command to print
     *
     * @throws RuntimeException When there is not enough content or people to report with
     */
    public function run(): array
    {
        $this->adminId = $this->administratorId();
        $this->pool = $this->reporterPool();
        $comments = $this->reportableComments();
        $posts = $this->reportablePosts();

        $serial = array_pop($this->pool);
        $lines = [];

        $lines[] = 'Case '.$this->spread('comment', $comments[0], 'spam', 3).': spam comment the rule hid on its own';
        $lines[] = 'Case '.$this->burst('comment', $comments[1], 'spam', 3).': spam comment held because the reports came in a burst';
        $lines[] = 'Case '.$this->spread('comment', $comments[2], 'harassment', 3).': harassment, a suspension waiting for a moderator';

        $upheld = $this->spread('comment', $comments[3], 'harassment', 2);
        $this->cases->uphold($this->detail($upheld), $this->adminId, 'Name-calling aimed at another reader.', true, 'Please keep replies about the post, not the person.');
        $lines[] = 'Case '.$upheld.': upheld, comment hidden and the author warned';

        $lines[] = 'Case '.$this->spread('post', $posts[0], 'misinformation', 2).': misinformation, below its threshold';
        $lines[] = 'Case '.$this->file('post', $posts[1], 'illegal', [$this->reporterFor($posts[1])], 'Shares what looks like a leaked private document.').': illegal content, escalated to a person';

        $review = $this->file('post', $posts[2], 'other', [$this->reporterFor($posts[2])], 'The images seem to be copied from another site.');
        $this->cases->startReview($this->detail($review), $this->adminId);
        $lines[] = 'Case '.$review.': in review';

        foreach ([$posts[3], $posts[4], $posts[5], $comments[4]] as $n => $item) {
            $type = $n < 3 ? 'post' : 'comment';
            $caseId = $this->file($type, $item, 'hate', [$serial], 'This is offensive and should be removed.');
            $this->dismissAsUnfounded($caseId, $serial);
            $lines[] = 'Case '.$caseId.': dismissed, the report marked unfounded';
        }

        // The third dismissal already warned them unless automatic warnings are switched off.
        if ($this->standing->history($serial, [ReporterStandingService::WARNED]) === []) {
            $this->standing->warn($serial, $this->adminId, 'Several of your recent reports were about posts you disagreed with, not posts that broke the rules.');
        }
        $lines[] = 'Reporter '.$serial.' has 4 unfounded reports and a warning, so their reporting can now be paused: /admin/reports/reporters/'.$serial;

        return $lines;
    }

    /**
     * Remove every case and report, and undo what they did.
     *
     * @return array{restored_comments: int, restored_posts: int, cases: int, open_suspensions: int}
     */
    public function reset(): array
    {
        $hiddenComments = $this->column("SELECT id FROM comments WHERE hidden_reason = 'moderation'");
        $moderatedPosts = $this->column("SELECT id FROM posts WHERE status = 'moderated'");
        $cases = (int) $this->db->query('SELECT COUNT(*) FROM moderation_cases')->fetchColumn();

        $this->db->transaction(function () use ($hiddenComments, $moderatedPosts): void {
            foreach ($hiddenComments as $id) {
                $this->comments->unhideFromModeration($id);
            }

            foreach ($moderatedPosts as $id) {
                $this->posts->restoreFromModeration($id);
            }

            $this->db->execute('DELETE FROM content_reports');
            $this->db->execute('DELETE FROM moderation_cases');
            $this->db->execute('UPDATE comments SET reports_count = 0 WHERE reports_count > 0');
            $this->db->execute('UPDATE posts SET reports_count = 0 WHERE reports_count > 0');
            $this->db->execute(
                "DELETE FROM activity_log
                  WHERE resource_type = 'moderation_case' OR action IN ('comment.reported', 'post.reported', ?, ?, ?)",
                [ReporterStandingService::WARNED, ReporterStandingService::PAUSED, ReporterStandingService::RESUMED]
            );
            $this->db->execute("DELETE FROM notifications WHERE type LIKE 'moderation.%'");
            $this->db->execute("DELETE FROM mail_queue WHERE related_type IN ('moderation_case', 'moderation_reporter')");
            $this->db->execute('UPDATE users SET reports_paused_until = NULL WHERE reports_paused_until IS NOT NULL');
        });

        return [
            'restored_comments' => count($hiddenComments),
            'restored_posts' => count($moderatedPosts),
            'cases' => $cases,
            // A suspension goes through its own cascade, so it is lifted in the admin, not here
            'open_suspensions' => (int) $this->db->query(
                'SELECT COUNT(*) FROM user_suspensions WHERE case_id IS NOT NULL AND lifted_at IS NULL'
            )->fetchColumn(),
        ];
    }

    /**
     * File reports with all but the last moved hours into the past, so the
     * burst safeguard lets a rule act on the last one.
     *
     * @param  array{id: int, author_id: int}  $item
     */
    private function spread(string $type, array $item, string $category, int $count): int
    {
        $reporters = $this->reportersFor($item, $count);
        $caseId = $this->file($type, $item, $category, array_slice($reporters, 0, -1));

        $this->db->execute('UPDATE content_reports SET created_at = created_at - INTERVAL 3 HOUR WHERE case_id = ?', [$caseId]);
        $this->db->execute(
            'UPDATE moderation_cases SET created_at = created_at - INTERVAL 3 HOUR, first_reported_at = first_reported_at - INTERVAL 3 HOUR WHERE id = ?',
            [$caseId]
        );

        return $this->file($type, $item, $category, array_slice($reporters, -1));
    }

    /**
     * @param  array{id: int, author_id: int}  $item
     */
    private function burst(string $type, array $item, string $category, int $count): int
    {
        return $this->file($type, $item, $category, $this->reportersFor($item, $count));
    }

    /**
     * @param  array{id: int, author_id: int}  $item
     * @param  int[]  $reporters
     */
    private function file(string $type, array $item, string $category, array $reporters, ?string $details = null): int
    {
        $caseId = 0;

        foreach ($reporters as $reporterId) {
            $caseId = (int) $this->intake->file($reporterId, $type, $item['id'], $category, $details)['case_id'];
        }

        return $caseId;
    }

    private function dismissAsUnfounded(int $caseId, int $reporterId): void
    {
        $theirs = array_values(array_filter(
            $this->reports->forCase($caseId),
            static fn (array $report): bool => (int) $report['reporter_id'] === $reporterId
        ));

        $this->cases->dismiss($this->detail($caseId), $this->adminId, 'Nothing here breaks the rules.', array_map(
            static fn (array $report): int => (int) $report['id'],
            $theirs
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(int $caseId): array
    {
        return $this->caseModel->findDetail($caseId)
            ?? throw new RuntimeException("Case {$caseId} disappeared while seeding.");
    }

    /**
     * @param  array{id: int, author_id: int}  $item
     * @return int[]
     */
    private function reportersFor(array $item, int $count): array
    {
        $eligible = array_values(array_filter($this->pool, static fn (int $id): bool => $id !== $item['author_id']));
        shuffle($eligible);

        return array_slice($eligible, 0, $count);
    }

    /**
     * @param  array{id: int, author_id: int}  $item
     */
    private function reporterFor(array $item): int
    {
        return $this->reportersFor($item, 1)[0];
    }

    private function administratorId(): int
    {
        $id = $this->db->query(
            "SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id
              WHERE r.role_slug = 'administrator' ORDER BY ur.user_id LIMIT 1"
        )->fetchColumn();

        return $id === false ? throw new RuntimeException('There is no administrator to make the decisions.') : (int) $id;
    }

    /**
     * Readers old enough for their reports to count, with nothing paused.
     *
     * @return int[]
     */
    private function reporterPool(): array
    {
        $ids = $this->column(
            "SELECT u.id FROM users u
              WHERE u.deleted_at IS NULL AND u.is_active = 1 AND u.suspended_at IS NULL
                AND u.reports_paused_until IS NULL AND u.handle <> 'deleted-user'
                AND u.created_at <= NOW() - INTERVAL 30 DAY AND ".self::SAFE_EMAIL.'
              ORDER BY RAND() LIMIT 12'
        );

        if (count($ids) < 8) {
            throw new RuntimeException('Need at least 8 active accounts older than 30 days to report with. Run php cli db:seed first.');
        }

        return $ids;
    }

    /**
     * Approved, visible comments by ordinary readers that nobody has reported.
     *
     * @return array<int, array{id: int, author_id: int}>
     */
    private function reportableComments(): array
    {
        return $this->items(
            "SELECT c.id, c.user_id AS author_id FROM comments c
               JOIN posts p ON p.id = c.post_id
               JOIN blogs b ON b.id = p.blog_id
               JOIN users u ON u.id = c.user_id
              WHERE c.status = 'approved' AND c.deleted_at IS NULL AND c.hidden_at IS NULL
                AND p.status = 'published' AND b.status = 'published'
                AND {$this->ordinaryAuthor(true)}
                AND NOT EXISTS (SELECT 1 FROM content_reports cr WHERE cr.subject_type = 'comment' AND cr.subject_id = c.id)
              ORDER BY RAND() LIMIT ".self::COMMENTS,
            self::COMMENTS,
            'comments'
        );
    }

    /**
     * @return array<int, array{id: int, author_id: int}>
     */
    private function reportablePosts(): array
    {
        return $this->items(
            "SELECT p.id, p.author_id FROM posts p
               JOIN blogs b ON b.id = p.blog_id
               JOIN users u ON u.id = p.author_id
              WHERE p.status = 'published' AND b.status = 'published'
                AND {$this->ordinaryAuthor(false)}
                AND NOT EXISTS (SELECT 1 FROM content_reports cr WHERE cr.subject_type = 'post' AND cr.subject_id = p.id)
              ORDER BY RAND() LIMIT ".self::POSTS,
            self::POSTS,
            'posts'
        );
    }

    /**
     * An active author on a safe address. Staff are left out only where a rule
     * has to act on its own, since no rule does that against a site role.
     */
    private function ordinaryAuthor(bool $withoutStaff): string
    {
        $sql = "u.is_active = 1 AND u.suspended_at IS NULL AND u.handle <> 'deleted-user' AND ".self::SAFE_EMAIL;

        return $withoutStaff
            ? $sql." AND NOT EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                                      WHERE ur.user_id = u.id AND r.scope = 'system' AND r.role_slug <> 'reader')"
            : $sql;
    }

    /**
     * @return array<int, array{id: int, author_id: int}>
     */
    private function items(string $sql, int $needed, string $kind): array
    {
        $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) < $needed) {
            throw new RuntimeException("Need {$needed} published {$kind} that nobody has reported, by active accounts on example addresses. Run php cli db:seed first.");
        }

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'author_id' => (int) $row['author_id']], $rows);
    }

    /**
     * @return int[]
     */
    private function column(string $sql): array
    {
        return array_map('intval', $this->db->query($sql)->fetchAll(\PDO::FETCH_COLUMN));
    }
}
