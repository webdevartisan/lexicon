<?php

declare(strict_types=1);

use Modules\JourneyMap\MapTool;
use Modules\JourneyMap\MapToolException;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\TempFolder;

/**
 * The page installs the official pmtiles tool for this server, checked against GitHub's published digest.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->root = TempFolder::make('lexicon-jm-tool');
    mkdir($this->root.'/source', 0775, true);
    $this->binDir = $this->root.'/bin';

    // A release archive holding the given entries, plus a release answer pointing at it.
    $this->release = function (string $suffix, array $entries, ?string $digest = null, ?string $url = null): array {
        $archive = $this->root.'/source/go-pmtiles_1.31.2'.$suffix;
        if (str_ends_with($suffix, '.zip')) {
            $zip = new ZipArchive();
            $zip->open($archive, ZipArchive::CREATE);
            foreach ($entries as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();
        } else {
            $tar = new PharData(substr($archive, 0, -3));
            foreach ($entries as $name => $content) {
                $tar->addFromString($name, $content);
            }
            $tar->compress(Phar::GZ);
        }

        return [
            'tag_name' => 'v1.31.2',
            'assets' => [[
                'name' => basename($archive),
                'browser_download_url' => $url
                    ?? 'https://github.com/protomaps/go-pmtiles/releases/download/v1.31.2/'.basename($archive),
                'digest' => $digest ?? 'sha256:'.hash_file('sha256', $archive),
            ]],
            'archive' => $archive,
        ];
    };

    $this->tool = function (string $os, string $arch, ?array $release = null): MapTool {
        $fetchJson = fn (string $url): ?array => $release;
        $download = function (string $url, string $path) use ($release): bool {
            return $release !== null && copy($release['archive'], $path);
        };

        return new MapTool($this->binDir, $fetchJson, $download, $os, $arch);
    };

    $this->reason = function (MapTool $tool): string {
        try {
            $tool->install();
        } catch (MapToolException $e) {
            return $e->reason;
        }

        return 'installed';
    };
});

afterEach(function () {
    TempFolder::remove($this->root);
});

test('the suffix matches each system and chip', function () {
    expect(($this->tool)('Linux', 'aarch64')->assetSuffix())->toBe('_Linux_arm64.tar.gz')
        ->and(($this->tool)('Darwin', 'x86_64')->assetSuffix())->toBe('_Darwin_x86_64.zip')
        ->and(($this->tool)('Windows', 'AMD64')->assetSuffix())->toBe('_Windows_x86_64.zip');
});

test('an unknown chip has no suffix and install says system', function () {
    $tool = ($this->tool)('Linux', 'riscv64');

    expect($tool->assetSuffix())->toBeNull()
        ->and(($this->reason)($tool))->toBe('system');
});

test('a digest mismatch stores nothing', function () {
    $release = ($this->release)('_Windows_x86_64.zip', ['pmtiles.exe' => 'tool'], 'sha256:'.str_repeat('0', 64));
    $tool = ($this->tool)('Windows', 'AMD64', $release);

    expect(($this->reason)($tool))->toBe('digest')
        ->and($tool->path())->toBeNull()
        ->and(glob($this->binDir.'/*') ?: [])->toBe([]);
});

test('a download url outside the official release is refused', function () {
    $elsewhere = 'https://elsewhere.example/x.zip';
    $release = ($this->release)('_Windows_x86_64.zip', ['pmtiles.exe' => 'tool'], null, $elsewhere);

    expect(($this->reason)(($this->tool)('Windows', 'AMD64', $release)))->toBe('release');
});

test('an archive with a path that climbs out is refused', function () {
    $release = ($this->release)('_Windows_x86_64.zip', ['../pmtiles.exe' => 'tool', 'README.md' => 'hi']);
    $tool = ($this->tool)('Windows', 'AMD64', $release);

    expect(($this->reason)($tool))->toBe('archive')
        ->and($tool->path())->toBeNull();
});

test('a good zip installs the tool and its version', function () {
    $release = ($this->release)('_Windows_x86_64.zip', ['pmtiles.exe' => 'tool', 'LICENSE' => 'BSD']);
    $tool = ($this->tool)('Windows', 'AMD64', $release);

    expect($tool->install())->toBe('v1.31.2')
        ->and($tool->path())->toBe($this->binDir.DIRECTORY_SEPARATOR.'pmtiles.exe')
        ->and(file_get_contents((string) $tool->path()))->toBe('tool')
        ->and($tool->version())->toBe('v1.31.2');
});

test('a good tar.gz installs the tool on linux', function () {
    $release = ($this->release)('_Linux_x86_64.tar.gz', ['pmtiles' => 'tool', 'LICENSE' => 'BSD']);
    $tool = ($this->tool)('Linux', 'x86_64', $release);

    expect($tool->install())->toBe('v1.31.2')
        ->and(file_get_contents((string) $tool->path()))->toBe('tool');
});
