<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Yaml;

use OrthoCode\StandardsSync\Formats\Yaml\YamlListWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Yaml's entry points into the block-list writer; the list edits themselves are the block-list writer's to test. */
#[CoversClass(YamlListWriter::class)]
final class YamlListWriterTest extends TestCase
{
    public function testCreatesTheSectionIndentedWithTwoSpaces(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - vendor/acme/standards/deptrac.yaml
                    YAML,
            ),
            YamlListWriter::ensureEntry('', 'imports', 'vendor/acme/standards/deptrac.yaml'),
        );
    }

    public function testReadsTheSectionsEntries(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/deptrac.yaml
                  - local/architecture.yaml
                YAML,
        );

        self::assertSame(['vendor/acme/standards/deptrac.yaml', 'local/architecture.yaml'], YamlListWriter::readList($content, 'imports'));
    }

    public function testRemovesEntries(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/strict.yaml
                  - local/architecture.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - local/architecture.yaml
                    YAML,
            ),
            YamlListWriter::removeEntries($content, 'imports', ['vendor/acme/standards/strict.yaml']),
        );
    }
}
