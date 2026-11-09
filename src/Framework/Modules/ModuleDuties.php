<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * Carries the platform's data duties (deletion, erasure, export, backup) to every module whose data exists.
 *
 * Paused modules are included and the blog's own on or off switch is ignored:
 * switching a module off keeps its rows, so they still have to go.
 * Errors are not caught, so the caller's transaction rolls back.
 */
final class ModuleDuties
{
    public function __construct(private ModuleRegistry $registry, private ModuleInstances $instances) {}

    /**
     * Remove every module's rows for a blog that is being deleted.
     */
    public function eraseBlog(int $blogId): void
    {
        foreach ($this->modulesImplementing(BlogDataEraser::class) as $module) {
            $module->eraseBlog($blogId);
        }
    }

    /**
     * Hand every module a post that was just copied, so the copy keeps the module's rows.
     */
    public function copyPost(int $fromPostId, int $toPostId): void
    {
        foreach ($this->modulesImplementing(PostCopyParticipant::class) as $module) {
            $module->copyPost($fromPostId, $toPostId);
        }
    }

    /**
     * Remove every module's rows about an account that is being erased.
     */
    public function eraseUser(int $userId): void
    {
        foreach ($this->modulesImplementing(PersonalDataEraser::class) as $module) {
            $module->eraseUser($userId);
        }
    }

    /**
     * Every module's data about an account, keyed by module, for the personal data export.
     *
     * @return array<string, array<int|string, mixed>>
     */
    public function exportUser(int $userId): array
    {
        $export = [];
        foreach ($this->modulesImplementing(PersonalDataExporter::class) as $key => $module) {
            $data = $module->exportUser($userId);
            if ($data !== []) {
                $export[$key] = $data;
            }
        }

        return $export;
    }

    /**
     * Every module's rows for a blog backup, keyed by module. Modules with no rows are left out.
     *
     * @return array<string, array{version: int, rows: list<array<string, mixed>>}>
     */
    public function exportBlog(int $blogId): array
    {
        $export = [];
        foreach ($this->modulesImplementing(BlogArchiveParticipant::class) as $key => $module) {
            $rows = $module->exportBlog($blogId);
            if ($rows !== []) {
                $export[$key] = ['version' => $this->registry->present()[$key]->version, 'rows' => $rows];
            }
        }

        return $export;
    }

    /**
     * Hand each module its rows from a backup. Never throws: a module that is missing
     * here is skipped, and one that fails is logged and reported.
     *
     * @param  array<string, array{version: int, rows: list<mixed>}>  $data  Module key => rows from the backup
     * @param  array<int, int>  $postIdMap  Post id in the backup => id of the restored post
     * @return array{imported: array<string, int>, skipped: list<string>, failed: list<string>}
     */
    public function importBlog(int $blogId, array $data, array $postIdMap): array
    {
        $participants = $this->modulesImplementing(BlogArchiveParticipant::class);
        $result = ['imported' => [], 'skipped' => [], 'failed' => []];

        foreach ($data as $key => $backup) {
            if (!isset($participants[$key])) {
                $result['skipped'][] = $key;
                continue;
            }

            try {
                $result['imported'][$key] = $participants[$key]->importBlog(
                    $blogId,
                    $backup['version'],
                    $backup['rows'],
                    $postIdMap
                );
            } catch (\Throwable $e) {
                error_log("Module {$key} could not restore its backup rows: ".$e->getMessage());
                $result['failed'][] = $key;
            }
        }

        return $result;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $interface
     * @return array<string, T> Keyed by module key
     */
    private function modulesImplementing(string $interface): array
    {
        $modules = [];
        foreach ($this->registry->present() as $key => $manifest) {
            $module = $this->instances->for($manifest);
            if ($module instanceof $interface) {
                $modules[$key] = $module;
            }
        }

        return $modules;
    }
}
