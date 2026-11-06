<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * A module that leaves things outside its tables and its public folder, such as a folder under storage/ or a
 * Scheduler row, deletes them here. Core deletes the tables and the public folder itself.
 *
 * Runs only on uninstall, after the data checks pass. Switching a module off keeps everything.
 */
interface ModuleUninstaller
{
    /**
     * Delete what the module made outside its tables and public folder.
     */
    public function uninstall(): void;
}
