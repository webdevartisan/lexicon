<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use MaxMind\Db\Reader;

/**
 * Downloads this month's DB-IP Lite country and network (ASN) databases.
 *
 * Scheduled daily. It does nothing once the current month is installed, and
 * falls back to last month's file early in a month, before the new one is out.
 * A download is opened as a database before it replaces the old file.
 *
 * Licence: CC BY 4.0, credited on the Insights pages.
 *
 * Usage: php cli analytics:update-geo
 */
class AnalyticsUpdateGeoCommand implements SchedulableCommandInterface
{
    private const TIMEOUT_SECONDS = 60;

    public static function scheduleLabel(): string
    {
        return 'Update the country and network databases';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return int Exit code, 0 for success
     */
    public function handle(array $arguments = []): int
    {
        $config = require ROOT_PATH.'/config/analytics.php';
        $failed = false;

        foreach (['country database' => $config['geo'], 'network database' => $config['networks']] as $name => $file) {
            $failed = !$this->update($name, $file) || $failed;
        }

        return $failed ? 1 : 0;
    }

    /**
     * @param  array{path: string, download_url: string}  $file
     */
    private function update(string $name, array $file): bool
    {
        $target = ROOT_PATH.'/'.$file['path'];

        $now = new \DateTimeImmutable('first day of this month', new \DateTimeZone('UTC'));
        $months = [$now->format('Y-m'), $now->modify('-1 month')->format('Y-m')];
        $installed = is_file($target.'.version') ? trim((string) file_get_contents($target.'.version')) : null;

        foreach ($months as $month) {
            if ($month === $installed) {
                echo "Keeping the installed {$name} for {$month}.\n";

                return true;
            }

            if ($this->installMonth($file['download_url'], $month, $target)) {
                echo "Installed the DB-IP Lite {$name} for {$month}.\n";

                return true;
            }
        }

        echo "Could not download the DB-IP Lite {$name} for ".implode(' or ', $months).".\n";

        return false;
    }

    private function installMonth(string $urlPattern, string $month, string $target): bool
    {
        $gzip = $this->download(sprintf($urlPattern, $month));

        if ($gzip === null) {
            return false;
        }

        $this->install($gzip, $target);
        file_put_contents($target.'.version', $month);

        return true;
    }

    private function download(string $url): ?string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'Lexicon analytics:update-geo',
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $status !== 200) {
            echo "Download of {$url} failed (HTTP {$status}".($error !== '' ? ", {$error}" : '').").\n";

            return null;
        }

        return $body;
    }

    private function install(string $gzip, string $target): void
    {
        $data = gzdecode($gzip);

        if ($data === false) {
            throw new \RuntimeException('The downloaded country database is not a valid gzip file.');
        }

        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create {$dir}.");
        }

        $temp = $target.'.tmp';
        if (file_put_contents($temp, $data) === false) {
            throw new \RuntimeException("Cannot write {$temp}.");
        }

        // Throws on a damaged file, before the working copy is touched.
        (new Reader($temp))->close();

        if (!rename($temp, $target)) {
            throw new \RuntimeException("Cannot replace {$target}.");
        }
    }
}
