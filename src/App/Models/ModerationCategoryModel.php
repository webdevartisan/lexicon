<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The reasons a reader can give when reporting something, each carrying the
 * rule the system applies once enough people agree.
 *
 * Slugs are stored on every report, so a category is deactivated rather than
 * deleted once it has been used.
 */
class ModerationCategoryModel extends AppModel
{
    protected ?string $table = 'moderation_categories';

    /** Least to most severe; the index is the rank. */
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const ACTIONS = ['none', 'hide_content', 'suspend', 'escalate'];

    public const EXECUTIONS = ['automatic', 'confirm'];

    /**
     * Every category in display order, inactive ones included.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ordered(): array
    {
        return $this->database
            ->query("SELECT * FROM {$this->getTable()} ORDER BY sort_order, label")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * The categories a reader may choose from right now.
     *
     * @return array<int, array<string, mixed>>
     */
    public function active(): array
    {
        return $this->database
            ->query("SELECT * FROM {$this->getTable()} WHERE is_active = 1 ORDER BY sort_order, label")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->database
            ->query("SELECT * FROM {$this->getTable()} WHERE slug = ?", [$slug])
            ->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Write a category's rule and wording. The slug never changes, since
     * every report already filed under it stores it.
     *
     * @param  array<string, mixed>  $fields  Column => value, from ModerationConfigService
     */
    public function updateSettings(string $slug, array $fields): void
    {
        $allowed = [
            'label', 'description', 'severity', 'auto_action', 'threshold', 'suspension_hours',
            'execution', 'automation_acknowledged_by', 'automation_acknowledged_at', 'is_active',
        ];
        $fields = array_intersect_key($fields, array_flip($allowed));
        $set = implode(', ', array_map(static fn (string $column): string => "{$column} = ?", array_keys($fields)));

        $this->database->execute(
            "UPDATE {$this->getTable()} SET {$set} WHERE slug = ?",
            [...array_values($fields), $slug]
        );
    }

    /**
     * How many categories readers can currently choose from.
     */
    public function activeCount(): int
    {
        return (int) $this->database
            ->query("SELECT COUNT(*) FROM {$this->getTable()} WHERE is_active = 1")
            ->fetchColumn();
    }

    /**
     * A category a reader may file under, or null when it is unknown or retired.
     *
     * @return array<string, mixed>|null
     */
    public function findActive(string $slug): ?array
    {
        $row = $this->findBySlug($slug);

        return $row !== null && (int) $row['is_active'] === 1 ? $row : null;
    }

    /**
     * Position of a severity on the scale, so two can be compared.
     */
    public static function severityRank(string $severity): int
    {
        $rank = array_search($severity, self::SEVERITIES, true);

        return $rank === false ? -1 : $rank;
    }
}
