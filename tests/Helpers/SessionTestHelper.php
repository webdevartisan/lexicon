<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\UserModel;
use App\Services\Analytics\CityLookup;
use App\Services\Analytics\CountryLookup;
use App\Services\Analytics\UserAgentClassifier;
use App\Services\Sessions\SessionLocation;
use App\Services\Sessions\SessionRecordFactory;
use App\Services\Sessions\SessionRevoker;
use Framework\Core\Container;
use Framework\Database;
use Framework\Session;
use Framework\Session\SessionManager;
use Framework\Session\SessionRegistry;

/**
 * Real session pieces over the test database, for tests that hand-wire the
 * services which sign people in or out.
 */
final class SessionTestHelper
{
    /**
     * @return array<string, mixed> config/session.php with the database driver
     */
    public static function config(array $overrides = []): array
    {
        $config = require ROOT_PATH.'/config/session.php';

        return array_replace($config, ['driver' => 'database'], $overrides);
    }

    public static function registry(Database $db): SessionRegistry
    {
        return new SessionRegistry($db, 'user_sessions');
    }

    public static function manager(Database $db, array $overrides = []): SessionManager
    {
        $container = new Container();
        $container->setShared(Database::class, static fn () => $db);

        return new SessionManager($container, self::config($overrides));
    }

    public static function revoker(Database $db, ?Session $session = null): SessionRevoker
    {
        return new SessionRevoker(self::registry($db), self::manager($db), $session ?? new Session());
    }

    public static function records(Database $db, ?Session $session = null): SessionRecordFactory
    {
        return new SessionRecordFactory(
            $session ?? new Session(),
            new UserModel($db),
            fn () => new UserAgentClassifier([]),
            fn () => new SessionLocation(new CityLookup('/nonexistent.mmdb'), new CountryLookup('/nonexistent.mmdb')),
            self::config()
        );
    }

    /**
     * A user_sessions row as a signed-in session would have it.
     *
     * @return string The row's hash
     */
    public static function signedInRow(Database $db, int $userId, array $columns = []): string
    {
        $hash = SessionRegistry::hash(bin2hex(random_bytes(16)));
        $now = time();

        self::registry($db)->open($hash, array_replace([
            'user_id' => $userId,
            'device' => 'desktop',
            'browser' => 'Firefox',
            'os' => 'Linux',
            'ip_address' => '203.0.113.9',
            'signed_in_at' => $now,
            'idle_seconds' => 3600,
            'absolute_expires_at' => $now + 86400,
        ], $columns), $now);

        return $hash;
    }
}
