<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Email blocks saved in the control panel.
 *
 * Only customized or added blocks have a row; the built-in ones live in
 * resources/mail/catalog.php. EmailTemplateRepository lays these over them.
 */
class EmailComponentModel extends AppModel
{
    protected ?string $table = 'email_components';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRows(): array
    {
        return $this->database->query("SELECT * FROM {$this->getTable()} ORDER BY slug")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Insert or replace the row for a slug.
     *
     * @param  array{slug: string, label: string, category: string, description: string, html: string, text: ?string, css: string, preview_data: array<string, string>}  $component
     */
    public function upsert(array $component, ?int $userId): void
    {
        $this->database->execute(
            "INSERT INTO {$this->getTable()}
                (slug, label, category, description, html_template, text_template, css, preview_data, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                label = VALUES(label), category = VALUES(category), description = VALUES(description),
                html_template = VALUES(html_template), text_template = VALUES(text_template), css = VALUES(css),
                preview_data = VALUES(preview_data), updated_by = VALUES(updated_by)",
            [
                $component['slug'],
                $component['label'],
                $component['category'],
                $component['description'],
                $component['html'],
                $component['text'],
                $component['css'] === '' ? null : $component['css'],
                json_encode((object) $component['preview_data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $userId,
            ]
        );
    }

    public function deleteBySlug(string $slug): bool
    {
        return $this->database->execute("DELETE FROM {$this->getTable()} WHERE slug = ?", [$slug]) > 0;
    }
}
