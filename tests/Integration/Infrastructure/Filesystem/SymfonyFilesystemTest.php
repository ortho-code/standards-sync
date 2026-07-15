<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Infrastructure\Filesystem;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\SymfonyFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\AlleKnalle\StandardsSync\Integration\IntegrationTestCase;

#[CoversClass(SymfonyFilesystem::class)]
final class SymfonyFilesystemTest extends IntegrationTestCase
{
    private SymfonyFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new SymfonyFilesystem();
    }

    public function testWritesThenReadsTheSameContent(): void
    {
        $path = Path::fromString($this->workspace() . '/.editorconfig');

        $this->filesystem->write($path, "root = true\n");

        self::assertSame("root = true\n", $this->filesystem->read($path));
    }

    public function testReadReturnsNullForAMissingFile(): void
    {
        self::assertNull($this->filesystem->read(Path::fromString($this->workspace() . '/nope.txt')));
    }

    public function testWriteCreatesMissingParentDirectories(): void
    {
        $path = Path::fromString($this->workspace() . '/nested/deep/.gitignore');

        $this->filesystem->write($path, "vendor/\n");

        self::assertSame("vendor/\n", $this->filesystem->read($path));
    }
}
