<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\ScheduledTaskModel;
use App\Models\ScheduledTaskRunModel;
use App\Models\UserPreferencesModel;
use App\Services\PseudoCron;
use App\Services\ScheduleCalculator;
use App\Services\ScheduleRegistry;
use App\Services\ScheduleService;
use DateTimeZone;
use Framework\Core\Response;
use InvalidArgumentException;

/**
 * Control panel for recurring tasks.
 *
 * Cron only ever calls schedule:run, so this is where operators decide what
 * that tick actually does. Tasks can be added, paced, switched off, started by
 * hand and read back afterwards without anyone touching a crontab.
 *
 * Nothing here builds a command from what was typed. The command has to be one
 * the console kernel registered and that opted into scheduling, and its
 * arguments have to match the schema that command declared, so every control on
 * the form is a dropdown or a bounded number and there is nothing to inject
 * into.
 */
class ScheduledTaskController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageScheduledTasks';

    /** Schedule shapes the picker offers. */
    private const SCHEDULE_TYPES = ['every_minute', 'every_n_minutes', 'hourly', 'daily'];

    public function __construct(
        protected Response $response,
        private ScheduledTaskModel $tasks,
        private ScheduledTaskRunModel $runs,
        private ScheduleService $schedule,
        private ScheduleRegistry $registry,
        private ScheduleCalculator $calculator,
        private UserPreferencesModel $preferences,
        private PseudoCron $pseudoCron,
    ) {}

    /**
     * List every task with its current state.
     */
    public function index(): Response
    {
        return $this->view('areas/admin/ScheduledTask/index.lex.php', [
            'tasks' => $this->tasks->allWithStatus(),
            'heartbeatAge' => $this->schedule->heartbeatAge(),
            'heartbeatStale' => $this->schedule->heartbeatIsStale(),
            'runsDetached' => $this->schedule->runsDetached(),
            'viewerTimezone' => $this->viewerTimezone(),
            'cronLine' => $this->cronLine(),
            'labels' => $this->registry->options(),
            'pseudoCronEnabled' => $this->pseudoCron->isEnabled(),
            'pseudoCronOperational' => $this->pseudoCron->isOperational(),
        ]);
    }

    /**
     * Live status for the list, polled while something is running.
     */
    public function statuses(): Response
    {
        $statuses = [];

        foreach ($this->tasks->allWithStatus() as $task) {
            $statuses[(int) $task['id']] = [
                'running' => !empty($task['running_since']),
                'last_status' => $task['last_status'],
                'last_duration_ms' => $task['last_duration_ms'] === null ? null : (int) $task['last_duration_ms'],
                'last_run_at' => $task['last_run_at'],
                'next_run_at' => $task['next_run_at'],
                'is_active' => (bool) $task['is_active'],
            ];
        }

        $this->response->setJson([
            'tasks' => $statuses,
            'heartbeat_stale' => $this->schedule->heartbeatIsStale(),
        ]);

        return $this->response;
    }

    /**
     * What a schedule works out to, for the form to show as it is edited.
     *
     * Interval and batch size multiply and the answer catches people out, so
     * the number is put in front of them while they are choosing rather than
     * left to be discovered days later.
     */
    public function hint(): Response
    {
        $command = (string) ($this->request->get['command'] ?? '');

        if (!$this->registry->isSchedulable($command)) {
            $this->response->setJson(['hint' => null, 'runs_per_hour' => 0]);

            return $this->response;
        }

        $draft = [
            'schedule_type' => (string) ($this->request->get['schedule_type'] ?? 'every_minute'),
            'interval_minutes' => (int) ($this->request->get['interval_minutes'] ?? 5),
        ];

        $runsPerHour = $this->calculator->runsPerHour($draft);

        try {
            // Half-finished argument values are normal while someone is still
            // choosing, so a rejection here just means no hint yet.
            $arguments = $this->registry->validate($command, (array) ($this->request->get['arguments'] ?? []));
            $hint = $this->registry->hintFor($command, $arguments, $runsPerHour);
        } catch (InvalidArgumentException) {
            $hint = null;
        }

        $this->response->setJson(['hint' => $hint, 'runs_per_hour' => $runsPerHour]);

        return $this->response;
    }

    /**
     * Blank form for a new task.
     */
    public function create(): Response
    {
        return $this->view('areas/admin/ScheduledTask/form.lex.php', $this->formData(null));
    }

    /**
     * Form for an existing task.
     *
     * @param  string  $id  Task ID
     */
    public function edit(string $id): Response
    {
        $task = $this->tasks->findById((int) $id);

        if ($task === null) {
            $this->flash('error', 'That task no longer exists.');

            return $this->response->redirect(lurl('/admin/scheduled-tasks'));
        }

        return $this->view('areas/admin/ScheduledTask/form.lex.php', $this->formData($task));
    }

    /**
     * Save a new task.
     */
    public function store(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        try {
            $data = $this->readForm();
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());

            return $this->redirectBack();
        }

        $this->tasks->createTask($data);
        $this->flash('success', 'Task created. It will run on the next tick after '.$data['next_run_at'].' UTC.');

        return $this->response->redirect(lurl('/admin/scheduled-tasks'));
    }

    /**
     * Update an existing task.
     *
     * @param  string  $id  Task ID
     */
    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $taskId = (int) $id;

        if ($this->tasks->findById($taskId) === null) {
            $this->flash('error', 'That task no longer exists.');

            return $this->response->redirect(lurl('/admin/scheduled-tasks'));
        }

        try {
            $data = $this->readForm();
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());

            return $this->redirectBack();
        }

        $this->tasks->updateTask($taskId, $data);
        $this->flash('success', 'Task saved.');

        return $this->response->redirect(lurl('/admin/scheduled-tasks'));
    }

    /**
     * Start a task by hand.
     *
     * @param  string  $id  Task ID
     */
    public function run(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->schedule->runNow((int) $id);

        $this->flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->response->redirect(lurl('/admin/scheduled-tasks'));
    }

    /**
     * Switch a task on or off.
     *
     * Re-enabling recomputes next_run_at, since a task that was switched off
     * had its schedule cleared and would otherwise sit there never coming due.
     *
     * @param  string  $id  Task ID
     */
    public function toggle(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $task = $this->tasks->findById((int) $id);

        if ($task === null) {
            $this->flash('error', 'That task no longer exists.');

            return $this->response->redirect(lurl('/admin/scheduled-tasks'));
        }

        $task['is_active'] = empty($task['is_active']) ? 1 : 0;
        $task['next_run_at'] = $this->calculator->nextRunAt($task);
        $task['arguments'] = $task['arguments'] ?? null;

        $this->tasks->updateTask((int) $id, $task);

        $this->flash('success', $task['is_active']
            ? 'Task switched on and scheduled.'
            : 'Task switched off. It will not run until you switch it back on.');

        return $this->response->redirect(lurl('/admin/scheduled-tasks'));
    }

    /**
     * Delete a task and its history.
     *
     * @param  string  $id  Task ID
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($this->tasks->deleteTask((int) $id)) {
            $this->flash('success', 'Task deleted.');
        } else {
            $this->flash('error', 'That task no longer exists.');
        }

        return $this->response->redirect(lurl('/admin/scheduled-tasks'));
    }

    /**
     * Run history and captured output for one task.
     *
     * @param  string  $id  Task ID
     */
    public function history(string $id): Response
    {
        $taskId = (int) $id;
        $task = $this->tasks->findById($taskId);

        if ($task === null) {
            $this->flash('error', 'That task no longer exists.');

            return $this->response->redirect(lurl('/admin/scheduled-tasks'));
        }

        $page = max(1, (int) ($this->request->get['page'] ?? 1));
        $result = $this->runs->historyFor($taskId, $page, 20);

        return $this->view('areas/admin/ScheduledTask/history.lex.php', [
            'task' => $task,
            'runs' => $result['data'],
            'pagination' => $result['pagination'],
            'viewerTimezone' => $this->viewerTimezone(),
        ]);
    }

    /**
     * Everything the form needs, for a new task or an existing one.
     *
     * @param  array<string, mixed>|null  $task
     * @return array<string, mixed>
     */
    private function formData(?array $task): array
    {
        $arguments = [];

        if ($task !== null && is_string($task['arguments'] ?? null) && $task['arguments'] !== '') {
            $decoded = json_decode((string) $task['arguments'], true);
            $arguments = is_array($decoded) ? $decoded : [];
        }

        return [
            'task' => $task,
            'arguments' => $arguments,
            'commandOptions' => $this->registry->options(),
            'schemas' => $this->registry->allSchemas(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'viewerTimezone' => $this->viewerTimezone(),
            'scheduleTypes' => self::SCHEDULE_TYPES,
        ];
    }

    /**
     * Read and check everything the form submitted.
     *
     * @return array<string, mixed> Ready for the model
     *
     * @throws InvalidArgumentException On anything that does not add up
     */
    private function readForm(): array
    {
        $this->validateOrFail([
            'label' => 'required|max:100',
            'command' => 'required',
            'schedule_type' => 'required',
            'timeout_seconds' => 'required|numeric',
        ]);

        $post = $this->request->post;

        $command = (string) $post['command'];

        // Checked against the registry rather than trusted, so a renamed or
        // withdrawn command cannot be saved and quietly fail later.
        if (!$this->registry->isSchedulable($command)) {
            throw new InvalidArgumentException('That command cannot be scheduled.');
        }

        $scheduleType = (string) $post['schedule_type'];

        if (!in_array($scheduleType, self::SCHEDULE_TYPES, true)) {
            throw new InvalidArgumentException('Pick a schedule from the list.');
        }

        $timezone = (string) ($post['schedule_timezone'] ?? 'UTC');

        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('That is not a timezone this server knows about.');
        }

        $timeout = (int) $post['timeout_seconds'];

        if ($timeout < 10 || $timeout > 86400) {
            throw new InvalidArgumentException('Timeout has to be between 10 seconds and 24 hours.');
        }

        // Throws with the command own wording when a value is outside what it
        // declared it accepts.
        $arguments = $this->registry->validate($command, (array) ($post['arguments'] ?? []));

        $data = [
            'label' => trim((string) $post['label']),
            'command' => $command,
            'arguments' => $arguments === [] ? null : json_encode($arguments),
            'schedule_type' => $scheduleType,
            'interval_minutes' => null,
            'minute_of_hour' => null,
            'run_at' => null,
            'schedule_timezone' => $timezone,
            'timeout_seconds' => $timeout,
            'is_active' => empty($post['is_active']) ? 0 : 1,
        ];

        // array_merge, not the union operator. Union keeps the value already
        // on the left, so the nulls seeded above would win and the chosen
        // interval or time would silently never be stored.
        $data = array_merge($data, $this->readScheduleParts($scheduleType, $post));

        // Worked out here rather than left to the dispatcher so the task is
        // due at the right moment the instant it is saved.
        $data['next_run_at'] = $this->calculator->nextRunAt($data);

        return $data;
    }

    /**
     * Pull the one schedule field that applies to the chosen shape.
     *
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When the field for that shape is missing or out of range
     */
    private function readScheduleParts(string $scheduleType, array $post): array
    {
        if ($scheduleType === 'every_n_minutes') {
            $minutes = (int) ($post['interval_minutes'] ?? 0);

            if ($minutes < 1 || $minutes > 1440) {
                throw new InvalidArgumentException('The interval has to be between 1 minute and 24 hours.');
            }

            return ['interval_minutes' => $minutes];
        }

        if ($scheduleType === 'hourly') {
            $minute = (int) ($post['minute_of_hour'] ?? -1);

            if ($minute < 0 || $minute > 59) {
                throw new InvalidArgumentException('Pick a minute between 0 and 59.');
            }

            return ['minute_of_hour' => $minute];
        }

        if ($scheduleType === 'daily') {
            $runAt = (string) ($post['run_at'] ?? '');

            if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $runAt) !== 1) {
                throw new InvalidArgumentException('Give the daily time as HH:MM.');
            }

            return ['run_at' => $runAt.':00'];
        }

        return [];
    }

    /**
     * Timezone to show timestamps in, from the signed in user preferences.
     */
    private function viewerTimezone(): string
    {
        $userId = (int) (auth()->user()['id'] ?? 0);

        if ($userId < 1) {
            return 'UTC';
        }

        $timezone = (string) ($this->preferences->findOrCreate($userId)['timezone'] ?? '');

        return $timezone !== '' ? $timezone : 'UTC';
    }

    /**
     * The crontab line this install needs, shown when the heartbeat is quiet.
     */
    private function cronLine(): string
    {
        return '* * * * * cd '.ROOT_PATH.' && php cli schedule:run >> /dev/null 2>&1';
    }
}
