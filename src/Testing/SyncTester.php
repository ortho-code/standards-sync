<?php

declare(strict_types=1);

namespace StandardsSync\Testing;

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\Engine\Engine;
use StandardsSync\Core\Plan\Plan;
use StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;

/**
 * Runs a sync in memory so a package can test its config without temp directories.
 * Seed the files a target repo already has, then inspect the resulting contents or the plan.
 * Framework-neutral: it returns plain data, so consumers assert with whatever they use.
 */
final class SyncTester
{
    /**
     * Applies the config to an in-memory filesystem seeded with $existingFiles and returns the resulting path => contents map.
     *
     * @param array<string, string> $existingFiles
     * @return array<string, string>
     */
    public function sync(SyncConfig $config, array $existingFiles = []): array
    {
        $filesystem = new InMemoryFilesystem($existingFiles);
        $engine = new Engine($filesystem);
        $engine->apply($engine->plan($config));

        return $filesystem->contents();
    }

    /**
     * Plans the config against $existingFiles without writing anything.
     *
     * @param array<string, string> $existingFiles
     */
    public function plan(SyncConfig $config, array $existingFiles = []): Plan
    {
        return new Engine(new InMemoryFilesystem($existingFiles))->plan($config);
    }
}
