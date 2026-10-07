<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The layout an email uses, for emails whose layout was changed from the one
 * their shipped file names.
 */
class EmailSettingModel extends AppModel
{
    protected ?string $table = 'email_settings';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRows(): array
    {
        return $this->database->query("SELECT * FROM {$this->getTable()} ORDER BY mailable_class")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function setLayout(string $mailable, string $layoutSlug, ?int $userId): void
    {
        $this->database->execute(
            "INSERT INTO {$this->getTable()} (mailable_class, layout_slug, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE layout_slug = VALUES(layout_slug), updated_by = VALUES(updated_by)",
            [$mailable, $layoutSlug, $userId]
        );
    }

    public function deleteByClass(string $mailable): void
    {
        $this->database->execute("DELETE FROM {$this->getTable()} WHERE mailable_class = ?", [$mailable]);
    }
}
