<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Each email's words per language, where written or changed in the control
 * panel. English rows override the shipped English; other languages exist
 * only here.
 */
class EmailContentModel extends AppModel
{
    protected ?string $table = 'email_contents';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRows(): array
    {
        return $this->database->query("SELECT * FROM {$this->getTable()} ORDER BY mailable_class, locale")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param  array{subject: string, preheader: string, body: string, footer_note: string, repeat: ?string}  $content
     */
    public function upsert(string $mailable, string $locale, array $content, ?int $userId): void
    {
        $this->database->execute(
            "INSERT INTO {$this->getTable()}
                (mailable_class, locale, subject, preheader, body, footer_note, repeat_html, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                subject = VALUES(subject), preheader = VALUES(preheader), body = VALUES(body),
                footer_note = VALUES(footer_note), repeat_html = VALUES(repeat_html), updated_by = VALUES(updated_by)",
            [$mailable, $locale, $content['subject'], $content['preheader'], $content['body'], $content['footer_note'], $content['repeat'], $userId]
        );
    }

    public function deleteOne(string $mailable, string $locale): bool
    {
        return $this->database->execute("DELETE FROM {$this->getTable()} WHERE mailable_class = ? AND locale = ?", [$mailable, $locale]) > 0;
    }
}
