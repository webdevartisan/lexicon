<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * A module that stores anything about a person adds it to that person's data export.
 */
interface PersonalDataExporter
{
    /**
     * Everything the module holds about this account, as JSON-ready data.
     *
     * @return array<int|string, mixed>
     */
    public function exportUser(int $userId): array;
}
