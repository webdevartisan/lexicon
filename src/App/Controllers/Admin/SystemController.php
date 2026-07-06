<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use Framework\Core\Response;
use Framework\Database;

/**
 * Read-only system diagnostics: PHP runtime, database footprint, and
 * application log files. Nothing here mutates state.
 */
class SystemController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'viewSystem';

    private const LOG_DIR = 'storage/logs';
    private const LOG_TAIL_LINES = 200;

    public function __construct(
        protected Database $database
    ) {}

    /**
     * Show runtime info, database table stats, and the selected log tail.
     */
    public function index(): Response
    {
        $logs = $this->logFiles();

        // only ever open a file that scandir itself listed
        $selected = (string) ($this->request->get['log'] ?? '');
        $logContent = null;
        if ($selected !== '' && array_key_exists($selected, $logs)) {
            $logContent = $this->tail($logs[$selected]['path'], self::LOG_TAIL_LINES);
        } else {
            $selected = '';
        }

        return $this->view([
            'php' => $this->phpInfo(),
            'tables' => $this->tableStats(),
            'logs' => $logs,
            'selectedLog' => $selected,
            'logContent' => $logContent,
        ]);
    }

    private function phpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time').'s',
            'opcache' => function_exists('opcache_get_status') && @opcache_get_status(false) !== false ? 'enabled' : 'disabled',
            'extensions' => count(get_loaded_extensions()),
        ];
    }

    /**
     * Row counts and disk size per table for the current schema.
     */
    private function tableStats(): array
    {
        $sql = 'SELECT TABLE_NAME AS name, TABLE_ROWS AS rows_estimate,
                       DATA_LENGTH + INDEX_LENGTH AS size_bytes
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                ORDER BY size_bytes DESC';

        return $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Log files available in storage/logs, keyed by filename.
     */
    private function logFiles(): array
    {
        $dir = dirname(__DIR__, 4).DIRECTORY_SEPARATOR.self::LOG_DIR;
        $files = [];

        if (is_dir($dir)) {
            foreach (scandir($dir) ?: [] as $file) {
                $path = $dir.DIRECTORY_SEPARATOR.$file;
                if (is_file($path) && preg_match('/^[A-Za-z0-9._-]+\.log$/', $file)) {
                    $files[$file] = [
                        'path' => $path,
                        'size' => filesize($path),
                        'modified' => filemtime($path),
                    ];
                }
            }
        }

        // newest first so the interesting log is on top
        uasort($files, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * Last N lines of a file without loading the whole thing.
     */
    private function tail(string $path, int $lines): string
    {
        $content = @file($path, FILE_IGNORE_NEW_LINES);
        if ($content === false) {
            return '';
        }

        return implode("\n", array_slice($content, -$lines));
    }
}
