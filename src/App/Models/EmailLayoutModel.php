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
}
