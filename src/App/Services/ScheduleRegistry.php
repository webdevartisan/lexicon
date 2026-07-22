<?php

declare(strict_types=1);

namespace App\Services;

use App\Console\Kernel;
use App\Interfaces\SchedulableCommandInterface;
use App\Interfaces\ScheduleHintInterface;
use InvalidArgumentException;

/**
 * Decides what the scheduler is allowed to run, and with what.
 *
 * A panel that starts processes is the most attractive thing in the
 * application to anyone who gets a foothold, so the command name on a task row
 * is never trusted. It has to be a command registered in the console kernel
 * that also opted in by implementing SchedulableCommandInterface, and that is
 * checked again when the task is about to run rather than only when the form
 * was submitted. A row written by any other route than the form, or one left
 * behind after a command was renamed, gets refused.
 *
 * Arguments go through the same treatment. Each command declares what it
 * accepts and anything outside that is rejected, so the panel needs no free
 * text field and nothing an operator types reaches a process.
 */
class ScheduleRegistry
{
    public function __construct(
        private Kernel $kernel,
    ) {}

    /**
     * Commands that may be scheduled, keyed by console name.
     *
     * @return array<string, class-string>
     */
    public function schedulableCommands(): array
    {
        $schedulable = [];

        foreach ($this->kernel->commandMap() as $name => $class) {
            if (is_subclass_of($class, SchedulableCommandInterface::class)) {
                $schedulable[$name] = $class;
            }
        }

        ksort($schedulable);

        return $schedulable;
    }

    /**
     * Options for the command dropdown, name to label.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->schedulableCommands() as $name => $class) {
            $options[$name] = $class::scheduleLabel();
        }

        return $options;
    }

    public function isSchedulable(string $command): bool
    {
        return isset($this->schedulableCommands()[$command]);
    }

    /**
     * Resolve a command name to its class.
     *
     * @return class-string
     *
     * @throws InvalidArgumentException When the command is not schedulable
     */
    public function classFor(string $command): string
    {
        $commands = $this->schedulableCommands();

        if (!isset($commands[$command])) {
            throw new InvalidArgumentException("'{$command}' is not a schedulable command.");
        }

        return $commands[$command];
    }

    /**
     * Argument schema declared by a command.
     *
     * @return array<string, array<string, mixed>>
     */
    public function schemaFor(string $command): array
    {
        return $this->classFor($command)::argumentSchema();
    }

    /**
     * Every declared schema at once, so the form can switch fields without
     * another request per command.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function allSchemas(): array
    {
        $schemas = [];

        foreach ($this->schedulableCommands() as $name => $class) {
            $schemas[$name] = $class::argumentSchema();
        }

        return $schemas;
    }

    /**
     * Check submitted arguments against what the command declared.
     *
     * Returns only declared keys, so anything extra on the row is dropped
     * rather than passed along.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed> Clean values ready to store or run
     *
     * @throws InvalidArgumentException On anything the schema does not allow
     */
    public function validate(string $command, array $arguments): array
    {
        $schema = $this->schemaFor($command);
        $clean = [];

        foreach ($schema as $name => $rule) {
            $type = (string) ($rule['type'] ?? 'enum');
            $given = $arguments[$name] ?? null;
            $missing = $given === null || $given === '';

            if ($missing) {
                if (array_key_exists('default', $rule)) {
                    $clean[$name] = $rule['default'];

                    continue;
                }

                if (!empty($rule['required'])) {
                    throw new InvalidArgumentException("'{$name}' is required for {$command}.");
                }

                continue;
            }

            $clean[$name] = match ($type) {
                'enum' => $this->validateEnum($name, $given, $rule),
                'int' => $this->validateInt($name, $given, $rule),
                'bool' => (bool) filter_var($given, FILTER_VALIDATE_BOOLEAN),
                default => throw new InvalidArgumentException("Unknown argument type '{$type}' on {$command}."),
            };
        }

        return $clean;
    }

    /**
     * Throughput sentence for the form, when the command offers one.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function hintFor(string $command, array $arguments, int $runsPerHour): ?string
    {
        $class = $this->classFor($command);

        if (!is_subclass_of($class, ScheduleHintInterface::class)) {
            return null;
        }

        return $class::scheduleHint($arguments, $runsPerHour);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function validateEnum(string $name, mixed $given, array $rule): string
    {
        $values = (array) ($rule['values'] ?? []);

        if (!in_array((string) $given, array_map('strval', $values), true)) {
            throw new InvalidArgumentException("'{$given}' is not an accepted value for '{$name}'.");
        }

        return (string) $given;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function validateInt(string $name, mixed $given, array $rule): int
    {
        if (!is_numeric($given)) {
            throw new InvalidArgumentException("'{$name}' must be a number.");
        }

        $value = (int) $given;
        $min = isset($rule['min']) ? (int) $rule['min'] : null;
        $max = isset($rule['max']) ? (int) $rule['max'] : null;

        if ($min !== null && $value < $min) {
            throw new InvalidArgumentException("'{$name}' must be at least {$min}.");
        }

        if ($max !== null && $value > $max) {
            throw new InvalidArgumentException("'{$name}' must be at most {$max}.");
        }

        return $value;
    }
}
