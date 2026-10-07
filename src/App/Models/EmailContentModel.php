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
}
