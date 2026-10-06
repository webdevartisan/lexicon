<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per-email template choice and wording saved in the control panel.
 *
 * Only emails whose binding was changed have a row; the rest use the
 * built-in binding in resources/mail/catalog.php.
 */
class MailableTemplateBindingModel extends AppModel
{
    protected ?string $table = 'mailable_template_bindings';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRows(): array
    {
        return $this->database->query("SELECT * FROM {$this->getTable()} ORDER BY mailable_class")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param  array{mailable: string, template: string, subject: ?string, mapping: array<string, string>, is_active: bool}  $binding
     */
    public function upsert(array $binding, ?int $userId): void
    {
        $this->database->execute(
            "INSERT INTO {$this->getTable()}
                (mailable_class, template_slug, subject_template, placeholder_mapping, is_active, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                template_slug = VALUES(template_slug), subject_template = VALUES(subject_template),
                placeholder_mapping = VALUES(placeholder_mapping), is_active = VALUES(is_active),
                updated_by = VALUES(updated_by)",
            [
                $binding['mailable'],
                $binding['template'],
                $binding['subject'],
                json_encode((object) $binding['mapping'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $binding['is_active'] ? 1 : 0,
                $userId,
            ]
        );
    }

    public function deleteByClass(string $mailable): bool
    {
        return $this->database->execute("DELETE FROM {$this->getTable()} WHERE mailable_class = ?", [$mailable]) > 0;
    }
}
