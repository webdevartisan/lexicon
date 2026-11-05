<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Which optional modules the platform has installed, and whether each is on, off or paused.
 */
class ModuleModel extends AppModel
{
    protected ?string $table = 'modules';

    /**
     * Every installed module's state, keyed by module key.
     *
     * @return array<string, string>
     */
    public function states(): array
    {
        $rows = $this->database->query("SELECT module_key, state FROM {$this->getTable()} ORDER BY module_key")
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $rows ?: [];
    }

    /**
     * Modules stopped after repeated errors, with the reason, for the control panel notice.
     *
     * @return list<array{module_key: string, paused_reason: ?string}>
     */
    public function paused(): array
    {
        return $this->database->query(
            "SELECT module_key, paused_reason FROM {$this->getTable()} WHERE state = 'paused' ORDER BY module_key"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Mark a module installed at a version. A new install starts on and an upgrade resumes a
     * paused module, but one an administrator switched off stays off.
     */
    public function record(string $key, int $version): void
    {
        $this->database->execute(
            "INSERT INTO {$this->getTable()} (module_key, version, state) VALUES (?, ?, 'on')
             ON DUPLICATE KEY UPDATE version = VALUES(version), state = IF(state = 'off', 'off', 'on'),
                 paused_reason = NULL",
            [$key, $version]
        );
    }

    /**
     * Stop a module from booting, keeping its data, with the reason shown to administrators.
     */
    public function pause(string $key, string $reason): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()} SET state = 'paused', paused_reason = ? WHERE module_key = ?",
            [$reason, $key]
        );
    }

    /**
     * Switch a module on for the whole site. A paused module resumes, losing its pause reason.
     */
    public function switchOn(string $key): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()} SET state = 'on', paused_reason = NULL WHERE module_key = ?",
            [$key]
        );
    }

    /**
     * Switch a module off for the whole site. Its data and every blog's own choice stay.
     */
    public function switchOff(string $key): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()} SET state = 'off', paused_reason = NULL WHERE module_key = ?",
            [$key]
        );
    }

    /**
     * Remove a module's row; its blog_modules rows go with it through the foreign key.
     */
    public function forget(string $key): void
    {
        $this->database->execute("DELETE FROM {$this->getTable()} WHERE module_key = ?", [$key]);
    }
}
