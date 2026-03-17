<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Framework\Helpers\KeyGenerator;

/**
 * Generate a new application encryption key.
 *
 * Generates a cryptographically secure key and optionally updates the .env file.
 * Useful for initial setup or key rotation.
 */
class KeyGenerateCommand
{
    /**
     * Execute the key generation command.
     *
     * @param array<int, string> $args Command arguments
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function execute(array $args): int
    {
        echo "🔐 Generating application key...\n\n";

        $key = KeyGenerator::generateForEnv(32);

        echo "Generated key:\n";
        echo "  {$key}\n\n";

        $envPath = ROOT_PATH.'/.env';

        if (!file_exists($envPath)) {
            echo "⚠️  No .env file found\n";
            echo "   Run 'php setup.php' for first-time setup\n";

            return 1;
        }

        echo 'Update .env file with this key? (y/n): ';
        $response = trim(fgets(STDIN));

        if (strtolower($response) === 'y') {
            $envContent = file_get_contents($envPath);

            // Replace existing APP_KEY or append if missing
            if (preg_match('/^APP_KEY=.*/m', $envContent)) {
                $newContent = preg_replace(
                    '/^APP_KEY=.*/m',
                    "APP_KEY={$key}",
                    $envContent
                );
            } else {
                $newContent = $envContent."\nAPP_KEY={$key}\n";
            }

            file_put_contents($envPath, $newContent);

            echo "Application key updated in .env\n";
        } else {
            echo "Skipped updating .env\n";
            echo "   Copy the key above and add it to your .env file manually\n";
        }

        return 0;
    }
}
