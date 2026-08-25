<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Integration\Infrastructure\Filesystem;

use StandardsSync\Core\Filesystem\Path;
use StandardsSync\Infrastructure\Filesystem\SymfonyFilesystem;
use StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\StandardsSync\Integration\IntegrationTestCase;

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

        $this->filesystem->write($path, FileContent::fromString('root = true'));

        self::assertSame(FileContent::fromString('root = true'), $this->filesystem->read($path));
    }

    public function testReadReturnsNullForAMissingFile(): void
    {
        self::assertNull($this->filesystem->read(Path::fromString($this->workspace() . '/nope.txt')));
    }

    public function testWriteCreatesMissingParentDirectories(): void
    {
        $path = Path::fromString($this->workspace() . '/nested/deep/.gitignore');

        $this->filesystem->write($path, FileContent::fromString('vendor/'));

        self::assertSame(FileContent::fromString('vendor/'), $this->filesystem->read($path));
    }
}
