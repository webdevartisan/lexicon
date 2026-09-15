<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Words that cannot be claimed as a user handle, each with how strictly it is matched.
 */
class ReservedHandleModel extends AppModel
{
    protected ?string $table = 'reserved_handles';

    /**
     * Every reserved word with its match type.
     *
     * @return array<string, string> Reserved word => 'exact' or 'contains'
     */
    public function matchTypesByHandle(): array
    {
        $sql = "SELECT handle, match_type FROM {$this->getTable()}";

        return $this->database->query($sql)->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
