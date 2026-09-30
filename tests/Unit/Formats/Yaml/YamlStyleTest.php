<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Yaml;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\YamlStyle;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlStyle::class)]
final class YamlStyleTest extends TestCase
{
    public function testReadsTheStyleADocumentUsesMost(): void
    {
        $style = YamlStyle::fromTree(YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                jobs:
                    standards:
                        steps:
                        -   uses: actions/checkout@v7
                        -   run: composer app-checks
                    other:
                        steps:
                        -   run: make
                YAML,
        )));

        self::assertSame([4, 0, 4], [$style->unit(), $style->sequenceOffset(), $style->itemOffset()]);
    }

    public function testFallsBackToTheConventionalStyleWhereTheDocumentShowsNone(): void
    {
        $style = YamlStyle::fromTree(YamlTree::fromString(FileContent::fromString('name: Checks')));

        self::assertSame([2, 2, 2], [$style->unit(), $style->sequenceOffset(), $style->itemOffset()]);
    }

    public function testTakesTheUnitForASequenceOffsetTheDocumentDoesNotShow(): void
    {
        $style = YamlStyle::fromTree(YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                jobs:
                    standards:
                        runs-on: ubuntu-26.04
                YAML,
        )));

        self::assertSame([4, 4], [$style->unit(), $style->sequenceOffset()]);
    }
}
