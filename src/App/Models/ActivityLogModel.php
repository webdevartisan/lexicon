<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Read model for the activity_log audit trail.
 *
 * AuditService writes the rows; this model exists so the admin control
 * panel can browse and filter them.
 */
class ActivityLogModel extends AppModel
{
    protected ?string $table = 'activity_log';

    /**
     * Paginated audit trail with optional action and resource-type filters.
     *
     * @param  string  $action  Exact action filter (e.g. 'comment.deleted')
     * @param  string  $resourceType  Resource type filter (e.g. 'user', 'post')
     * @param  int  $page  Current page (1-based)
     * @param  int  $perPage  Rows per page (capped at 100)
     * @return array{data: array, pagination: array}
     */
    public function findWithFilters(
        string $action = '',
        string $resourceType = '',
        int $page = 1,
        int $perPage = 25
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = [];
        $params = [];

        if ($action !== '') {
            $where[] = 'a.action = :action';
            $params[':action'] = $action;
        }

        if ($resourceType !== '') {
            $where[] = 'a.resource_type = :resource_type';
            $params[':resource_type'] = $resourceType;
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} a {$whereSql}",
            $params
        )->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT a.*, u.username
                FROM {$this->getTable()} a
                LEFT JOIN users u ON u.id = a.user_id
                {$whereSql}
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

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
     * Distinct filter options currently present in the trail.
     *
     * @return array{actions: string[], resource_types: string[]}
     */
    public function filterOptions(): array
    {
        $actions = $this->database
            ->query("SELECT DISTINCT action FROM {$this->getTable()} ORDER BY action")
            ->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        $types = $this->database
            ->query("SELECT DISTINCT resource_type FROM {$this->getTable()} ORDER BY resource_type")
            ->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        return ['actions' => $actions, 'resource_types' => $types];
    }

    /**
     * Most recent entries for the control panel overview.
     */
    public function latestEntries(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));

        $sql = "SELECT a.*, u.username
                FROM {$this->getTable()} a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$limit}";

        return $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
