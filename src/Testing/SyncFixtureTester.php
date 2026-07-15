<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing;

use AlleKnalle\StandardsSync\Core\Config\ConfigLoader;
use AlleKnalle\StandardsSync\Core\Filesystem\Filesystem;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\SymfonyFilesystem;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Runs a sync against an on-disk fixture and reports how the result differs from the expected tree.
 * A fixture holds an input tree (a target repo's files before the sync) and an expected tree (its files after); the config is the fixture's own standards-sync.php by default, or one you supply, so the maintainer reads real files to see what a config does.
 * Framework-neutral: it returns the differences, so consumers assert with whatever they use.
 */
final class SyncFixtureTester
{
    /** The current-directory root every fixture repo is synced against. */
    private const string ROOT = '.';

    /** The config file loaded from a fixture by default, and the subdirectories holding its before and after trees. */
    private const string CONFIG = 'standards-sync.php';
    private const string INPUT = 'input';
    private const string EXPECTED = 'expected';

    public function __construct(
        private readonly Filesystem $filesystem = new SymfonyFilesystem(),
        private readonly SyncTester $syncTester = new SyncTester(),
    ) {
    }

    /**
     * Loads the config (the fixture's own standards-sync.php by default, or $configFile if given), syncs the input tree in memory, then compares the result to the expected tree.
     *
     * @return list<Mismatch> empty when the sync matches the expected tree
     */
    public function diff(string $fixtureDirectory, ?string $configFile = null): array
    {
        $fixture = Path::fromString($fixtureDirectory);
        $root = Path::fromString(self::ROOT);

        $configPath = $configFile !== null
            ? Path::fromString($configFile)
            : $fixture->join(Path::fromString(self::CONFIG));
        $config = (new ConfigLoader())->loadFrom($configPath);

        $result = $this->syncTester->sync(
            $config->withRoots([$root->value()]),
            $this->readTree($fixture->join(Path::fromString(self::INPUT)), $root),
        );

        return $this->compare($result, $this->readTree($fixture->join(Path::fromString(self::EXPECTED)), $root));
    }

    /**
     * Reads a fixture tree into the path => contents map the engine works with, keying each file the way the resolver does.
     *
     * @return array<string, string>
     */
    private function readTree(Path $directory, Path $root): array
    {
        $tree = [];
        foreach ($this->relativePaths($directory) as $relativePath) {
            $content = $this->filesystem->read($directory->join(Path::fromString($relativePath)));
            if ($content !== null) {
                $tree[$root->join(Path::fromString($relativePath))->value()] = $content;
            }
        }

        return $tree;
    }

    /**
     * @param array<string, string> $actual
     * @param array<string, string> $expected
     * @return list<Mismatch>
     */
    private function compare(array $actual, array $expected): array
    {
        $paths = array_unique([...array_keys($actual), ...array_keys($expected)]);
        sort($paths);

        $mismatches = [];
        foreach ($paths as $path) {
            if (($actual[$path] ?? null) !== ($expected[$path] ?? null)) {
                $mismatches[] = new Mismatch(Path::fromString($path), $expected[$path] ?? null, $actual[$path] ?? null);
            }
        }

        return $mismatches;
    }

    /** @return list<string> */
    private function relativePaths(Path $directory): array
    {
        $path = $directory->value();
        if (!is_dir($path)) {
            return [];
        }

        $paths = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            $paths[] = substr($file->getPathname(), strlen($path) + 1);
        }

        return $paths;
    }
}
