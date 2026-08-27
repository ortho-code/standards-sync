<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Base for integration tests that touch the real filesystem.
 * Gives each test a throwaway temp workspace that is removed afterwards, and restores the working directory.
 */
abstract class IntegrationTestCase extends TestCase
{
    private ?string $workspace = null;
    private string $originalCwd;

    #[Before]
    protected function rememberWorkingDirectory(): void
    {
        $this->originalCwd = (string) getcwd();
    }

    #[After]
    protected function cleanUpWorkspace(): void
    {
        // A test may chdir (the sync command does), so restore the directory before removing the workspace.
        chdir($this->originalCwd);

        if ($this->workspace !== null) {
            (new Filesystem())->remove($this->workspace);
        }
    }

    /** The test's temp directory, created on first use. */
    protected function workspace(): string
    {
        if ($this->workspace === null) {
            $this->workspace = sys_get_temp_dir() . '/cs-' . uniqid('', true);
            mkdir($this->workspace, 0777, true);
        }

        return $this->workspace;
    }

    /** Writes a file inside the workspace and returns its absolute path. */
    protected function writeToWorkspace(string $relativePath, string $contents): string
    {
        $path = $this->workspace() . '/' . $relativePath;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
