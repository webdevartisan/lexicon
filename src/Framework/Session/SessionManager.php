<?php

declare(strict_types=1);

namespace Framework\Session;

use Closure;
use Framework\Core\Container;
use Framework\Database;
use Framework\Session\Drivers\DatabaseSessionDriver;
use Framework\Session\Drivers\FileSessionDriver;
use Framework\Session\Drivers\RedisSessionDriver;
use Framework\Session\Redis\PhpRedisConnection;
use Framework\Session\Redis\PredisConnection;
use Framework\Session\Redis\RedisConnection;
use InvalidArgumentException;

/**
 * Resolves the session driver named in config/session.php.
 *
 * Built-in drivers are create{Name}Driver() methods; extend() registers
 * another under its own name, checked first so it can replace a built-in.
 * An unknown name throws instead of quietly picking a different store.
 */
class SessionManager
{
    /** @var array<string, Closure(Container, array<string, mixed>): SessionDriver> */
    private array $customCreators = [];

    private ?SessionDriver $driver = null;

    /**
     * @param  array<string, mixed>  $config  config/session.php
     */
    public function __construct(
        private Container $container,
        private array $config,
    ) {}

    /**
     * @param  Closure(Container, array<string, mixed>): SessionDriver  $creator
     */
    public function extend(string $name, Closure $creator): static
    {
        $this->customCreators[$name] = $creator;

        return $this;
    }

    public function name(): string
    {
        return (string) ($this->config['driver'] ?? '');
    }

    public function driver(): SessionDriver
    {
        return $this->driver ??= $this->create($this->name());
    }

    /**
     * Fail on a misspelt driver without opening any connection.
     *
     * @throws InvalidArgumentException
     */
    public function assertSupported(): void
    {
        $name = $this->name();

        if (!isset($this->customCreators[$name]) && !method_exists($this, $this->creatorMethod($name))) {
            throw new InvalidArgumentException(sprintf(
                'Session driver [%s] is not supported. Set SESSION_DRIVER to file, database or redis, '
                .'or register it with SessionManager::extend().',
                $name
            ));
        }
    }

    private function create(string $name): SessionDriver
    {
        $this->assertSupported();

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->container, $this->config);
        }

        return $this->{$this->creatorMethod($name)}();
    }

    private function creatorMethod(string $name): string
    {
        return 'create'.str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name))).'Driver';
    }

    protected function createFileDriver(): SessionDriver
    {
        return new FileSessionDriver();
    }

    protected function createDatabaseDriver(): SessionDriver
    {
        return new DatabaseSessionDriver(
            $this->container->get(Database::class),
            (string) $this->config['table'],
            (int) $this->config['lock_seconds'],
            (int) $this->config['guest_idle_minutes'] * 60,
            (int) $this->config['activity_interval_seconds'],
        );
    }

    protected function createRedisDriver(): SessionDriver
    {
        $redis = $this->config['redis'];

        return new RedisSessionDriver(
            $this->redisConnection($redis),
            (string) $redis['prefix'],
            $this->longestIdleSeconds(),
            (int) $this->config['lock_seconds'],
        );
    }

    /**
     * phpredis when the extension is loaded, otherwise Predis.
     *
     * @param  array<string, mixed>  $redis
     */
    private function redisConnection(array $redis): RedisConnection
    {
        $settings = [
            'host' => (string) $redis['host'],
            'port' => (int) $redis['port'],
            'password' => $redis['password'] !== null ? (string) $redis['password'] : null,
            'database' => (int) $redis['database'],
            'timeout' => (float) $redis['timeout'],
        ];

        return extension_loaded('redis') ? PhpRedisConnection::connect($settings) : PredisConnection::connect($settings);
    }

    /**
     * Data must outlive the longest idle limit; the user_sessions row enforces
     * the shorter ones.
     */
    public function longestIdleSeconds(): int
    {
        return 60 * max(
            (int) $this->config['idle_minutes'],
            (int) $this->config['privileged_idle_minutes'],
            (int) $this->config['guest_idle_minutes'],
        );
    }
}
