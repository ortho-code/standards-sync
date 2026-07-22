<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Infrastructure\Filesystem;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\TemplateDirectory;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Exception\IOException;
use Tests\AlleKnalle\StandardsSync\Integration\IntegrationTestCase;

#[CoversClass(TemplateDirectory::class)]
final class TemplateDirectoryTest extends IntegrationTestCase
{
    public function testReadsAFileRelativeToTheDirectory(): void
    {
        $this->writeToWorkspace('templates/.editorconfig', FileContent::fromString('root = true'));
        $templates = new TemplateDirectory(Path::fromString($this->workspace() . '/templates'));

        self::assertSame(FileContent::fromString('root = true'), $templates->read('.editorconfig'));
    }

    public function testThrowsWhenTheFileIsMissing(): void
    {
        $templates = new TemplateDirectory(Path::fromString($this->workspace()));

        $this->expectException(IOException::class);

        $templates->read('missing');
    }
}
