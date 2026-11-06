<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * What a module says about itself in its module.json, checked against the rules core enforces.
 */
final class ModuleManifest
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9-]{1,40}$/';

    private const SCOPES = ['blog', 'platform'];

    /**
     * @param list<string> $slots
     * @param list<string> $tables
     * @param list<string> $requires
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly int $version,
        public readonly string $scope,
        public readonly string $class,
        public readonly string $path,
        public readonly array $slots,
        public readonly array $tables,
        public readonly array $requires,
        public readonly bool $hasPage = false,
        public readonly bool $hasAdminPage = false,
    ) {
    }

    /**
     * Read and validate a module.json file.
     *
     * @param string $jsonPath Absolute path to the module.json file
     * @return self The validated manifest
     * @throws InvalidManifestException When the file is missing or invalid
     */
    public static function fromFile(string $jsonPath): self
    {
        $json = is_file($jsonPath) ? file_get_contents($jsonPath) : false;
        if ($json === false) {
            throw new InvalidManifestException("Cannot read {$jsonPath}");
        }

        return self::fromJson($json, dirname($jsonPath));
    }

    /**
     * Build a manifest from JSON text.
     *
     * @param string $json The module.json contents
     * @param string $path Absolute path to the module folder
     * @return self The validated manifest
     * @throws InvalidManifestException When the JSON breaks a manifest rule
     */
    public static function fromJson(string $json, string $path): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidManifestException('module.json is not a JSON object');
        }

        return self::fromArray($data, $path);
    }

    /**
     * Build a manifest from decoded data, as stored in the registry cache.
     *
     * @param array<string, mixed> $data The decoded manifest fields
     * @param string $path Absolute path to the module folder
     * @return self The validated manifest
     * @throws InvalidManifestException When a field breaks a manifest rule
     */
    public static function fromArray(array $data, string $path): self
    {
        $key = self::key($data);

        return new self(
            key: $key,
            name: self::string($data, 'name'),
            description: self::string($data, 'description'),
            version: self::version($data, $key),
            scope: self::scope($data, $key),
            class: self::string($data, 'class'),
            path: $path,
            slots: self::slots($data, $key),
            tables: self::list($data, 'tables'),
            requires: self::requires($data, $key),
            hasPage: self::flag($data, 'page', $key),
            hasAdminPage: self::flag($data, 'admin_page', $key),
        );
    }

    /**
     * The fields to store in the registry cache, the inverse of fromArray().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'scope' => $this->scope,
            'class' => $this->class,
            'slots' => $this->slots,
            'tables' => $this->tables,
            'requires' => $this->requires,
            'page' => $this->hasPage,
            'admin_page' => $this->hasAdminPage,
        ];
    }


    private static function key(array $data): string
    {
        $key = self::string($data, 'key');
        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidManifestException("Module key \"{$key}\" must be lowercase letters, digits and hyphens");
        }

        return $key;
    }

    private static function scope(array $data, string $key): string
    {
        $scope = self::string($data, 'scope');
        if (!in_array($scope, self::SCOPES, true)) {
            throw new InvalidManifestException("Module {$key} has unknown scope \"{$scope}\"");
        }

        return $scope;
    }

    /**
     * @return list<string>
     */
    private static function slots(array $data, string $key): array
    {
        $slots = self::list($data, 'slots');
        $unknown = array_diff($slots, ModuleRegistry::SLOTS);
        if ($unknown !== []) {
            throw new InvalidManifestException("Module {$key} asks for unknown slots: ".implode(', ', $unknown));
        }

        return $slots;
    }

    /**
     * Dependencies between modules are out of scope for now, so a manifest that needs one cannot load.
     *
     * @return list<string>
     */
    private static function requires(array $data, string $key): array
    {
        $requires = self::list($data, 'requires');
        if ($requires !== []) {
            throw new InvalidManifestException("Module {$key} requires other modules, which is not supported yet");
        }

        return $requires;
    }

    /**
     * A true or false switch that defaults to false: "page" for a page at the root of the manage routes,
     * which the blog's Extras card links, and "admin_page" for one in the control panel, linked from /admin/extras.
     */
    private static function flag(array $data, string $field, string $key): bool
    {
        $value = $data[$field] ?? false;
        if (!is_bool($value)) {
            throw new InvalidManifestException("Module {$key} must set \"{$field}\" to true or false");
        }

        return $value;
    }

    private static function string(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidManifestException("module.json needs a text \"{$field}\"");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function list(array $data, string $field): array
    {
        $value = $data[$field] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidManifestException("module.json \"{$field}\" must be a list");
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new InvalidManifestException("module.json \"{$field}\" must hold only text");
            }
        }

        return $value;
    }

    private static function version(array $data, string $key): int
    {
        $version = $data['version'] ?? null;
        if (!is_int($version) || $version < 1) {
            throw new InvalidManifestException("Module {$key} needs a whole number version of 1 or more");
        }

        return $version;
    }
}
