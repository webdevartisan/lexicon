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
}
