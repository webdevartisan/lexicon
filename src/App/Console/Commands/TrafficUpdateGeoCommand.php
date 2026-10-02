<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use MaxMind\Db\Reader;

/**
 * Downloads this month's DB-IP Lite country database.
 *
 * Scheduled daily. It does nothing once the current month is installed, and
 * falls back to last month's file early in a month, before the new one is out.
 * A download is opened as a database before it replaces the old file.
 *
 * Licence: CC BY 4.0, credited on the Traffic dashboard.
 *
 * Usage: php cli traffic:update-geo
 */
class TrafficUpdateGeoCommand implements SchedulableCommandInterface
{
    private const TIMEOUT_SECONDS = 60;

    public static function scheduleLabel(): string
    {
        return 'Update the country database';
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
        $config = (require ROOT_PATH.'/config/traffic.php')['geo'];
        $target = ROOT_PATH.'/'.$config['path'];

        $now = new \DateTimeImmutable('first day of this month', new \DateTimeZone('UTC'));
        $months = [$now->format('Y-m'), $now->modify('-1 month')->format('Y-m')];
        $installed = is_file($target.'.version') ? trim((string) file_get_contents($target.'.version')) : null;

        foreach ($months as $month) {
            if ($month === $installed) {
                echo "Keeping the installed country database for {$month}.\n";

                return 0;
            }

            if ($this->installMonth($config['download_url'], $month, $target)) {
                echo "Installed the DB-IP Lite country database for {$month}.\n";

                return 0;
            }
        }

        echo 'Could not download the DB-IP Lite country database for '.implode(' or ', $months).".\n";

        return 1;
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
            CURLOPT_USERAGENT => 'Lexicon traffic:update-geo',
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
