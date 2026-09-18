<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Gate;
use App\Resources\SystemResource;
use App\Services\LogFileService;
use Framework\Core\Response;
use Framework\Database;

/**
 * System diagnostics: PHP runtime, database footprint, and application logs,
 * which an administrator can empty.
 */
class SystemController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'viewSystem';

    private const LOG_TAIL_LINES = 200;

    public function __construct(
        protected Database $database,
        private LogFileService $logFiles,
    ) {}

    /**
     * Show runtime info, database table stats, and the selected log tail.
     */
    public function index(): Response
    {
        $logs = $this->logFiles->all();

        $selected = (string) ($this->request->get['log'] ?? '');
        $logContent = null;
        $logError = null;

        if ($selected !== '' && array_key_exists($selected, $logs)) {
            try {
                $logContent = $this->logFiles->tail($selected, self::LOG_TAIL_LINES);
            } catch (\RuntimeException $e) {
                error_log('System log viewer: '.$e->getMessage());
                $logError = $e->getMessage();
            }
        } else {
            $selected = '';
        }

        return $this->view([
            'php' => $this->phpInfo(),
            'tables' => $this->tableStats(),
            'logs' => $logs,
            'selectedLog' => $selected,
            'logContent' => $logContent,
            'logError' => $logError,
            'canClearLogs' => Gate::allows('clearLogs', SystemResource::class, auth()->user() ?? []),
        ]);
    }

    /**
     * Empty one log file, keeping the file itself.
     */
    public function clearLog(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        Gate::authorize('clearLogs', SystemResource::class, $user);

        $name = (string) ($this->request->postParam('log') ?? '');

        try {
            $bytes = $this->logFiles->clear($name);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            error_log('Clearing log failed: '.$e->getMessage());
            $this->flash('error', 'The log was not cleared. '.$e->getMessage());

            return $this->redirect('/admin/system?log='.rawurlencode($name));
        }

        audit()->log(
            (int) $user['id'],
            'system.log_cleared',
            'log',
            null,
            ['file' => $name, 'bytes' => $bytes],
            $this->request->ip()
        );

        $this->flash('success', "{$name} was emptied.");

        return $this->redirect('/admin/system?log='.rawurlencode($name));
    }

    /**
     * @return array<string, mixed> PHP runtime facts
     */
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
     *
     * @return array<int, array<string, mixed>> Table rows with name, rows_estimate, size_bytes
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
}
