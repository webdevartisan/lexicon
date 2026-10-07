<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Email layouts made or changed in the control panel. A row with the slug of
 * a shipped layout file overrides that file.
 */
class EmailLayoutModel extends AppModel
{
    protected ?string $table = 'email_layouts';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRows(): array
    {
        return $this->database->query("SELECT * FROM {$this->getTable()} ORDER BY slug")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param  array{slug: string, name: string, html: string, primary_color: string, background_color: string, support_email: string, company_address: string}  $layout
     */
    public function upsert(array $layout, ?int $userId): void
    {
        $this->database->execute(
            "INSERT INTO {$this->getTable()}
                (slug, name, html, primary_color, background_color, support_email, company_address, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), html = VALUES(html), primary_color = VALUES(primary_color),
                background_color = VALUES(background_color), support_email = VALUES(support_email),
                company_address = VALUES(company_address), updated_by = VALUES(updated_by)",
            [$layout['slug'], $layout['name'], $layout['html'], $layout['primary_color'], $layout['background_color'], $layout['support_email'], $layout['company_address'], $userId]
        );
    }

    public function deleteBySlug(string $slug): bool
    {
        return $this->database->execute("DELETE FROM {$this->getTable()} WHERE slug = ?", [$slug]) > 0;
    }
}
