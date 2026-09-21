<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ModerationPriority;
use InvalidArgumentException;

/**
 * One moderation case per reported item: the reports gathered against it and
 * the decision taken about it.
 *
 * Two independent states live here. `status` is the moderator's work (open,
 * in review, escalated, resolved); `content_status` is what readers see
 * (visible, hidden, removed). A dismissed case leaves content visible and an
 * upheld one may not, which is why neither is derived from the other.
 */
class ModerationCaseModel extends AppModel
{
    protected ?string $table = 'moderation_cases';

    public const STATUSES = ['open', 'in_review', 'escalated', 'resolved'];

    public const CONTENT_STATUSES = ['visible', 'hidden', 'removed'];

    /**
     * Where the reported item lives now, joined so the queue can link to it and
     * tell a deleted item from a hidden one. Every column is NULL once it is gone.
     */
    private const SUBJECT_JOINS = "
        LEFT JOIN posts p ON mc.subject_type = 'post' AND p.id = mc.subject_id
        LEFT JOIN comments c ON mc.subject_type = 'comment' AND c.id = mc.subject_id
        LEFT JOIN posts cp ON cp.id = c.post_id
        LEFT JOIN blogs b ON b.id = COALESCE(p.blog_id, cp.blog_id)";

    private const SUBJECT_COLUMNS = '
        COALESCE(p.id, c.id) AS live_id,
        COALESCE(p.slug, cp.slug) AS post_slug,
        COALESCE(p.title, cp.title) AS post_title,
        b.blog_slug, b.blog_name,
        p.status AS post_status,
        c.deleted_at AS comment_deleted_at';

    /**
     * The live case for an item, opened if there is none.
     *
     * open_key is unique while a case is unresolved, so two reports arriving
     * together land on the same row instead of opening two cases.
     *
     * @return int The case id
     */
    public function openFor(
        string $subjectType,
        int $subjectId,
        ?int $authorId,
        ?string $authorHandle,
        ?int $blogId,
        ?string $snapshot,
    ): int {
        $this->database->execute(
            "INSERT INTO {$this->getTable()}
                (subject_type, subject_id, open_key, subject_author_id, subject_author_handle, blog_id,
                 subject_snapshot, first_reported_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
            [
                $subjectType,
                $subjectId,
                "{$subjectType}:{$subjectId}",
                $authorId,
                $authorHandle,
                $blogId,
                $snapshot,
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->database
            ->query("SELECT * FROM {$this->getTable()} WHERE id = ?", [$id])
            ->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * A case with where its item lives now and, when it still exists, its
     * current text, for the case page.
     *
     * @return array<string, mixed>|null
     */
    public function findDetail(int $id): ?array
    {
        $columns = self::SUBJECT_COLUMNS;
        $joins = self::SUBJECT_JOINS;

        $row = $this->database->query(
            "SELECT mc.*, {$columns},
                    p.content AS post_content, p.excerpt AS post_excerpt,
                    c.content AS comment_content, c.hidden_reason AS comment_hidden_reason,
                    c.post_id AS comment_post_id
               FROM {$this->getTable()} mc {$joins}
              WHERE mc.id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * One page of the reports queue.
     *
     * Filters are expected to be validated by the caller; unknown values fall
     * through as "no filter". $orderBy must come from a TableSort whitelist.
     *
     * @param  array{type?: string, category?: string, status?: string, from?: string, to?: string, author_id?: int|null, reporter_id?: int|null}  $filters
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function findForQueue(array $filters, int $page = 1, int $perPage = 20, string $orderBy = 'mc.priority DESC, mc.id DESC'): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        [$whereSql, $params] = $this->queueWhere($filters);

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} mc {$whereSql}",
            $params
        )->fetchColumn();

        $columns = self::SUBJECT_COLUMNS;
        $joins = self::SUBJECT_JOINS;

        // Reporter names and categories are folded in here so a page of cases
        // costs one query, not one per row.
        $sql = "SELECT mc.*, {$columns},
                       (SELECT SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(r.reporter_handle, '') ORDER BY r.created_at SEPARATOR ','), ',', 3)
                          FROM content_reports r WHERE r.case_id = mc.id) AS first_reporters,
                       (SELECT GROUP_CONCAT(DISTINCT r.category ORDER BY r.category SEPARATOR ',')
                          FROM content_reports r WHERE r.case_id = mc.id) AS categories
                  FROM {$this->getTable()} mc {$joins}
                  {$whereSql}
                 ORDER BY {$orderBy}
                 LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = ($page - 1) * $perPage;

        $rows = $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $totalPages = (int) ceil($total / $perPage);

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    /**
     * How many cases sit in each work state, for the queue's status tabs.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        $rows = $this->database
            ->query("SELECT status, COUNT(*) AS total FROM {$this->getTable()} GROUP BY status")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param  array{type?: string, category?: string, status?: string, from?: string, to?: string, author_id?: int|null, reporter_id?: int|null}  $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function queueWhere(array $filters): array
    {
        $where = [];
        $params = [];
        $status = $filters['status'] ?? 'active';

        if ($status === 'active') {
            $where[] = "mc.status <> 'resolved'";
        } elseif (in_array($status, self::STATUSES, true)) {
            $where[] = 'mc.status = :status';
            $params[':status'] = $status;
        }

        if (in_array($filters['type'] ?? '', ['post', 'comment'], true)) {
            $where[] = 'mc.subject_type = :type';
            $params[':type'] = $filters['type'];
        }

        if (($filters['category'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM content_reports fr WHERE fr.case_id = mc.id AND fr.category = :category)';
            $params[':category'] = $filters['category'];
        }

        if (($filters['from'] ?? '') !== '') {
            $where[] = 'mc.last_reported_at >= :from';
            $params[':from'] = $filters['from'].' 00:00:00';
        }

        if (($filters['to'] ?? '') !== '') {
            $where[] = 'mc.last_reported_at <= :to';
            $params[':to'] = $filters['to'].' 23:59:59';
        }

        if (isset($filters['author_id'])) {
            $where[] = 'mc.subject_author_id = :author';
            $params[':author'] = $filters['author_id'];
        }

        if (isset($filters['reporter_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM content_reports rr WHERE rr.case_id = mc.id AND rr.reporter_id = :reporter)';
            $params[':reporter'] = $filters['reporter_id'];
        }

        return [$where === [] ? '' : 'WHERE '.implode(' AND ', $where), $params];
    }

    /**
     * Drop a case that was opened for a report which then turned out to be a
     * repeat. Only a case with no reports at all can go.
     */
    public function discardIfEmpty(int $id): void
    {
        $this->database->execute(
            "DELETE FROM {$this->getTable()}
              WHERE id = ? AND NOT EXISTS (SELECT 1 FROM content_reports WHERE case_id = ?)",
            [$id, $id]
        );
    }

    /**
     * Recompute the counts and the queue priority from the reports themselves.
     *
     * Called after every report and every decision, so the queue never shows a
     * number that was true an hour ago.
     */
    public function recount(int $id): void
    {
        $totals = $this->database->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(counts_toward_threshold), 0) AS counted,
                    MAX(created_at) AS last_at
               FROM content_reports
              WHERE case_id = ?',
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        $total = (int) ($totals['total'] ?? 0);
        $counted = (int) ($totals['counted'] ?? 0);
        $lastAt = $totals['last_at'] ?? null;

        $lastDay = $lastAt === null ? 0 : (int) $this->database->query(
            'SELECT COUNT(*) FROM content_reports WHERE case_id = ? AND created_at >= ? - INTERVAL 1 DAY',
            [$id, $lastAt]
        )->fetchColumn();

        // Unknown categories sort last: FIELD() gives them 0.
        $top = $this->database->query(
            "SELECT cr.category, mc.severity
               FROM content_reports cr
               LEFT JOIN moderation_categories mc ON mc.slug = cr.category
              WHERE cr.case_id = ?
              GROUP BY cr.category, mc.severity
              ORDER BY FIELD(mc.severity, 'low', 'medium', 'high', 'critical') DESC, COUNT(*) DESC
              LIMIT 1",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        $case = $this->findById($id);

        if ($case === null) {
            return;
        }

        $priorUpheld = $case['subject_author_id'] === null ? 0 : (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()}
              WHERE subject_author_id = ? AND resolution = 'upheld' AND id <> ?",
            [$case['subject_author_id'], $id]
        )->fetchColumn();

        $priority = ModerationPriority::score(
            $top === false ? null : $top['severity'],
            $counted,
            $total - $counted,
            $lastDay,
            $priorUpheld,
            $case['pending_action'] !== null || $case['last_error'] !== null,
        );

        $this->database->execute(
            "UPDATE {$this->getTable()}
                SET report_count = ?, counted_report_count = ?, top_category = ?,
                    last_reported_at = ?, priority = ?
              WHERE id = ?",
            [$total, $counted, $top === false ? null : $top['category'], $lastAt, $priority, $id]
        );
    }

    /**
     * Categories whose rule already fired on this case.
     *
     * @param  array<string, mixed>  $case
     * @return string[]
     */
    public function firedRules(array $case): array
    {
        if (empty($case['fired_rules'])) {
            return [];
        }

        $rules = json_decode((string) $case['fired_rules'], true, 4, JSON_THROW_ON_ERROR);

        return is_array($rules) ? array_values(array_filter($rules, 'is_string')) : [];
    }

    /**
     * Remember that a category's rule fired, so it cannot fire twice on one case.
     */
    public function markFired(int $id, string $rule): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()}
                SET fired_rules = JSON_ARRAY_APPEND(COALESCE(fired_rules, JSON_ARRAY()), '$', ?)
              WHERE id = ?",
            [$rule, $id]
        );
    }

    /**
     * Park an action a rule wants taken until a person confirms it.
     */
    public function propose(int $id, string $action, string $rule, string $note): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()}
                SET pending_action = ?, pending_rule = ?, pending_note = ?, status = 'escalated'
              WHERE id = ? AND status <> 'resolved'",
            [$action, $rule, $note, $id]
        );
    }

    /**
     * Mark that a moderator has picked the case up.
     *
     * @return bool False when it was already in review or is resolved
     */
    public function startReview(int $id): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()} SET status = 'in_review' WHERE id = ? AND status IN ('open', 'escalated')",
            [$id]
        ) > 0;
    }

    /**
     * Close the case with a decision. open_key is released, so the next report
     * on the same item starts a fresh case rather than reopening this one.
     *
     * @param  string  $resolution  'dismissed' or 'upheld'
     * @return bool False when the case was already resolved
     */
    public function resolve(int $id, string $resolution, int $actorId, string $note): bool
    {
        if (!in_array($resolution, ['dismissed', 'upheld'], true)) {
            throw new InvalidArgumentException("Unknown resolution '{$resolution}'.");
        }

        return $this->database->execute(
            "UPDATE {$this->getTable()}
                SET status = 'resolved', open_key = NULL, resolution = ?, resolution_note = ?,
                    resolved_by = ?, resolved_at = NOW(),
                    pending_action = NULL, pending_rule = NULL, pending_note = NULL, last_error = NULL
              WHERE id = ? AND status <> 'resolved'",
            [$resolution, $note, $actorId, $id]
        ) > 0;
    }

    /**
     * What this author's earlier cases came to, for the case page.
     *
     * @return array{total: int, upheld: int, dismissed: int, open: int}
     */
    public function authorRecord(int $authorId, int $exceptCaseId): array
    {
        $row = $this->database->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(resolution = 'upheld'), 0) AS upheld,
                    COALESCE(SUM(resolution = 'dismissed'), 0) AS dismissed,
                    COALESCE(SUM(status <> 'resolved'), 0) AS open
               FROM {$this->getTable()}
              WHERE subject_author_id = ? AND id <> ?",
            [$authorId, $exceptCaseId]
        )->fetch(\PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'upheld' => (int) ($row['upheld'] ?? 0),
            'dismissed' => (int) ($row['dismissed'] ?? 0),
            'open' => (int) ($row['open'] ?? 0),
        ];
    }

    /**
     * Everything the audit log holds about a case, oldest first, with who did
     * it. details.actor_type says whether a rule or a person acted.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(int $caseId): array
    {
        $rows = $this->database->query(
            "SELECT a.action, a.user_id, a.details, a.created_at, u.handle AS actor_handle
               FROM activity_log a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE a.resource_type = 'moderation_case' AND a.resource_id = ?
              ORDER BY a.id",
            [$caseId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['details'] = json_decode((string) ($row['details'] ?? ''), true) ?: [];
        }

        return $rows;
    }

    /**
     * How many times moderators have warned this person.
     */
    public function warningsFor(int $authorId): int
    {
        return (int) $this->database->query(
            "SELECT COUNT(*) FROM activity_log
              WHERE action = 'moderation.author_warned'
                AND JSON_EXTRACT(details, '$.target_user_id') = ?",
            [$authorId]
        )->fetchColumn();
    }

    /**
     * Hand the case to a person without proposing a specific action.
     */
    public function escalate(int $id): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()} SET status = 'escalated' WHERE id = ? AND status <> 'resolved'",
            [$id]
        );
    }

    /**
     * Record what readers now see of the reported item.
     *
     * @throws InvalidArgumentException For a status outside CONTENT_STATUSES
     */
    public function setContentStatus(int $id, string $status): void
    {
        if (!in_array($status, self::CONTENT_STATUSES, true)) {
            throw new InvalidArgumentException("Unknown content status '{$status}'.");
        }

        $this->database->execute(
            "UPDATE {$this->getTable()} SET content_status = ? WHERE id = ?",
            [$status, $id]
        );
    }

    /**
     * An automatic action failed. Leave it as a proposal with the error beside
     * it, so the queue shows it and a person can apply it by hand.
     */
    public function recordFailure(int $id, string $action, string $rule, string $error): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()}
                SET last_error = ?, pending_action = ?, pending_rule = ?, status = 'escalated'
              WHERE id = ?",
            [mb_substr($error, 0, 500), $action, $rule, $id]
        );
    }

    /**
     * Whether anything this person wrote is under an unresolved case.
     */
    public function hasOpenAgainst(int $authorId): bool
    {
        return (bool) $this->database->query(
            "SELECT EXISTS(SELECT 1 FROM {$this->getTable()} WHERE subject_author_id = ? AND status <> 'resolved')",
            [$authorId]
        )->fetchColumn();
    }
}
