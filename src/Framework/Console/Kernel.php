<?php

declare(strict_types=1);

namespace Framework\Console;

use Exception;
use Framework\Core\Container;

/**
 * Console Kernel (Base)
 *
 * We provide the base console routing logic. Application-specific
 * command registration is handled by extending this class in the
 * application layer (App\Console\Kernel).
 *
 * This follows the Open/Closed Principle: the framework is closed
 * for modification but open for extension.
 */
class Kernel
{
    /**
     * Registered console commands.
     *
     * We map command names to their handler classes.
     * This is populated by child classes via the commands() method.
     *
     * @var array<string, class-string>
     */
    protected array $commands = [];

    /**
     * Constructor.
     *
     * We load commands from the child class and initialize the kernel.
     *
     * @param  Container  $app  Application container
     */
    public function __construct(private Container $app)
    {
        // load commands from the child class implementation
        $this->commands = $this->commands();
    }

    /**
     * Register console commands.
     *
     * We override this method in the application layer to register
     * application-specific commands. This keeps the framework generic.
     *
     * @return array<string, class-string>
     */
    protected function commands(): array
    {
        // return an empty array by default
        // Child classes override this to register their commands
        return [];
    }

    /**
     * Handle an incoming console command.
     *
     * We parse the command-line arguments, validate the command exists,
     * resolve its dependencies, and execute it. Returns an exit code
     * (0 = success, non-zero = failure).
     *
     * @param  array<int, string>  $argv  Command-line arguments from PHP
     * @return int Exit code (0 = success)
     */
    public function handle(array $argv): int
    {
        try {
            // extract the command name (first argument after script name)
            $commandName = $argv[1] ?? null;

            // show help if no command provided
            if (!$commandName || $commandName === '--help' || $commandName === '-h') {
                $this->showHelp();

                return 0;
            }

            // check if the command exists
            if (!isset($this->commands[$commandName])) {
                echo "Error: Command '{$commandName}' not found.\n\n";
                $this->showHelp();

                return 1;
            }

            // resolve the command handler from the container
            $commandClass = $this->commands[$commandName];
            $command = $this->app->get($commandClass);

            // check that the command has a handle() method
            if (!method_exists($command, 'handle')) {
                echo "Error: Command '{$commandName}' does not have a handle() method.\n";

                return 1;
            }

            // execute the command and return its exit code
            return $command->handle();

        } catch (Exception $e) {
            // catch any unhandled exceptions and display them
            echo "Error: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }

    /**
     * Display available commands.
     *
     * We output a formatted list of all registered commands
     * to help users discover what's available.
     */
    protected function showHelp(): void
    {
        echo "Available Commands:\n";
        echo "==================\n\n";

        foreach ($this->commands as $name => $class) {
            // pad command names for alignment
            echo '  '.str_pad($name, 20).' - '.$this->getCommandDescription($class)."\n";
        }

        echo "\nUsage:\n";
        echo "  php cli [command]\n";
        echo "  php cli cache:prune\n\n";
    }

    /**
     * Extract command description from class docblock.
     *
     * We parse the class-level PHPDoc comment to extract the
     * short description for the help output. Falls back to
     * the class name if no description is found.
     *
     * @param  class-string  $class  Command class name
     * @return string Command description
     */
    protected function getCommandDescription(string $class): string
    {
        try {
            $reflection = new \ReflectionClass($class);
            $docComment = $reflection->getDocComment();

            if ($docComment) {
                // extract the first line of the docblock (short description)
                preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\n/s', $docComment, $matches);

                return $matches[1] ?? $class;
            }
        } catch (\ReflectionException $e) {
            // fall back silently if reflection fails
        }

        return $class;
    }

    /**
     * Terminate the console request.
     *
     * We perform any cleanup operations after command execution.
     * Currently this is a no-op, but we keep it for symmetry with
     * the HTTP kernel and future extension points.
     *
     * @param  int  $status  Exit code from command execution
     */
    public function terminate(int $status): void
    {
        // could add cleanup logic here (close connections, flush logs, etc.)
        // For now, this is a no-op but provides a hook for future needs
    }

    /**
     * Get all registered commands.
     *
     * We return the full command registry for testing or introspection.
     *
     * @return array<string, class-string>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
