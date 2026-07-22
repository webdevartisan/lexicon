<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Marks a console command as safe to configure from the control panel.
 *
 * Opting in is deliberate. The scheduler dropdown is built from commands
 * implementing this, so something like key:generate never appears and cannot
 * be reached from the web at all. Anything a task row names is checked against
 * this set again at dispatch, not just when the form was saved.
 *
 * argumentSchema() is what keeps the panel free of text inputs. The form
 * renders a control per declared argument and the runner rejects anything that
 * does not match, so no operator supplied string ever reaches a process.
 */
interface SchedulableCommandInterface
{
    /**
     * Short name for the command in the scheduler dropdown.
     */
    public static function scheduleLabel(): string;

    /**
     * Describe the arguments this command accepts.
     *
     * Each entry is keyed by argument name and holds:
     *   type      'enum', 'int' or 'bool'
     *   label     what the form field is called
     *   values    allowed options, enum only
     *   min, max  bounds, int only
     *   default   value used when the task row omits the argument
     *   required  whether the form may leave it empty
     *
     * Return an empty array for a command that takes nothing.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array;

    /**
     * Run the command.
     *
     * The array is defaulted so the plain `php cli <name>` path and the
     * scheduler share one signature.
     *
     * @param  array<string, mixed>  $arguments  Values already validated against the schema
     * @return int Exit code, 0 for success
     */
    public function handle(array $arguments = []): int;
}
