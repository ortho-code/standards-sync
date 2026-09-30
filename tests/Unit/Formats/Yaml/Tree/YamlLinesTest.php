<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Yaml\Tree;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlLines;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlLines::class)]
final class YamlLinesTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function documents(): iterable
    {
        yield 'line feeds' => [FileContent::fromString(
            <<<'YAML'
                a: 1
                b: 2
                YAML,
        )];
        // The escapes are the point in the next five: line endings and a byte-order mark are what the lines keep.
        yield 'carriage returns' => ["a: 1\r\nb: 2\r\n"];
        yield 'mixed endings' => ["a: 1\r\nb: 2\nc: 3\r\n"];
        yield 'a byte-order mark' => ["\u{FEFF}a: 1\n"];
        yield 'no final line break' => ['a: 1'];
        yield 'a lone line break' => ["\n"];
        yield 'nothing' => [''];
    }

    #[DataProvider('documents')]
    public function testJoinsBackToTheDocumentByteForByte(string $content): void
    {
        self::assertSame($content, YamlLines::fromString($content)->toString());
    }

    public function testGivesEachLineWithoutItsEnding(): void
    {
        // The carriage return is the point: it belongs to the ending, not to the line.
        $lines = YamlLines::fromString("a: 1\r\nb: 2");

        self::assertSame(2, $lines->count());
        self::assertSame(['a: 1', 'b: 2'], [$lines->line(0), $lines->line(1)]);
        self::assertSame([true, false], [$lines->endsWithBreak(0), $lines->endsWithBreak(1)]);
    }

    public function testKeepsTheByteOrderMarkOutOfTheFirstLine(): void
    {
        self::assertSame('a: 1', YamlLines::fromString("\u{FEFF}" . FileContent::fromString('a: 1'))->line(0));
    }

    public function testInsertsLinesWithTheDocumentsFirstEnding(): void
    {
        // The carriage returns are the point: inserted lines take the ending the document already uses.
        self::assertSame("a: 1\r\nb: 2\r\nc: 3\r\n", YamlLines::fromString("a: 1\r\nc: 3\r\n")->withInserted(1, ['b: 2'])->toString());
    }

    public function testInsertingAfterALastLineWithoutABreakGivesItOne(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    a: 1
                    b: 2
                    YAML,
            ),
            YamlLines::fromString('a: 1')->withInserted(1, ['b: 2'])->toString(),
        );
    }

    public function testRemovingALastLineWithoutABreakKeepsTheNewLastLinesBreak(): void
    {
        $lines = YamlLines::fromString(
            <<<'YAML'
                a: 1
                b: 2
                YAML,
        );

        self::assertSame(FileContent::fromString('a: 1'), $lines->withRemoved(1, 2)->toString());
    }

    public function testRemovesLinesInTheMiddle(): void
    {
        $lines = YamlLines::fromString(FileContent::fromString(
            <<<'YAML'
                a: 1
                b: 2
                c: 3
                YAML,
        ));

        self::assertSame(FileContent::fromString('a: 1'), $lines->withRemoved(1, 3)->toString());
    }

    public function testReplacesALineKeepingItsEnding(): void
    {
        // The carriage return is the point: the replaced line keeps its own ending.
        self::assertSame("a: 2\r\nb: 2\n", YamlLines::fromString("a: 1\r\nb: 2\n")->withLine(0, 'a: 2')->toString());
    }
}
