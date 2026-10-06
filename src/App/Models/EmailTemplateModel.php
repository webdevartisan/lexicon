<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Email templates saved in the control panel, with their blocks in order.
 *
 * Only customized or added templates have a row; the built-in ones live in
 * resources/mail/catalog.php. EmailTemplateRepository lays these over them.
 */
class EmailTemplateModel extends AppModel
{
    protected ?string $table = 'email_templates';

    /**
     * Every stored template with its layout as a list of block slugs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithLayout(): array
    {
        $rows = $this->database->query("SELECT * FROM {$this->getTable()} ORDER BY slug")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $slots = $this->database->query(
            'SELECT template_id, component_slug FROM email_template_components ORDER BY template_id, position'
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $layouts = [];
        foreach ($slots as $slot) {
            $layouts[(int) $slot['template_id']][] = (string) $slot['component_slug'];
        }

        foreach ($rows as &$row) {
            $row['layout'] = $layouts[(int) $row['id']] ?? [];
        }

        return $rows;
    }

    /**
     * Insert or replace a template and its layout in one transaction, so a
     * template is never read back with half of its blocks.
     *
     * @param  array{slug: string, label: string, category: string, description: string, layout: list<string>}  $template
     */
    public function upsert(array $template, ?int $userId): void
    {
        $write = function () use ($template, $userId): void {
            $this->database->execute(
                "INSERT INTO {$this->getTable()} (slug, label, category, description, updated_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    label = VALUES(label), category = VALUES(category), description = VALUES(description),
                    updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP",
                [$template['slug'], $template['label'], $template['category'], $template['description'], $userId]
            );

            $id = (int) $this->database->query("SELECT id FROM {$this->getTable()} WHERE slug = ?", [$template['slug']])->fetchColumn();

            $this->database->execute('DELETE FROM email_template_components WHERE template_id = ?', [$id]);

            foreach ($template['layout'] as $position => $slug) {
                $this->database->execute(
                    'INSERT INTO email_template_components (template_id, position, component_slug) VALUES (?, ?, ?)',
                    [$id, $position, $slug]
                );
            }
        };

        // Joins a transaction the caller already opened rather than nesting one.
        $this->inTransaction() ? $write() : $this->transaction($write);
    }

    /**
     * Remove a template; its layout rows go with it through the foreign key.
     */
    public function deleteBySlug(string $slug): bool
    {
        return $this->database->execute("DELETE FROM {$this->getTable()} WHERE slug = ?", [$slug]) > 0;
    }
}
