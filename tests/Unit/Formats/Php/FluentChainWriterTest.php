<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Formats\Php;

use AlleKnalle\StandardsSync\Formats\Php\FluentChainWriter;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FluentChainWriter::class)]
final class FluentChainWriterTest extends TestCase
{
    public function testInsertsTheEntryAfterTheLastExistingEntry(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'PHP'
                    return RectorConfig::configure()
                        ->withSets([
                            'a.php',
                            'b.php',
                        ]);
                    PHP
            ),
            FluentChainWriter::ensureArrayEntry(
                FileContent::fromString(
                    <<<'PHP'
                        return RectorConfig::configure()
                            ->withSets([
                                'a.php',
                            ]);
                        PHP
                ),
                'withSets',
                "'b.php'",
            ),
        );
    }

    public function testACommentedEntryIsRecognizedAsPresent(): void
    {
        $withLineComment = FileContent::fromString(
            <<<'PHP'
                return RectorConfig::configure()
                    ->withSets([
                        __DIR__ . '/x.php', // the org set
                    ]);
                PHP
        );
        $withHashComment = FileContent::fromString(
            <<<'PHP'
                return RectorConfig::configure()
                    ->withSets([
                        __DIR__ . '/x.php', # the org set
                    ]);
                PHP
        );

        self::assertSame($withLineComment, FluentChainWriter::ensureArrayEntry($withLineComment, 'withSets', "__DIR__ . '/x.php'"));
        self::assertSame($withHashComment, FluentChainWriter::ensureArrayEntry($withHashComment, 'withSets', "__DIR__ . '/x.php'"));
    }

    public function testSlashesInsideAQuotedEntryAreNotACommentBoundary(): void
    {
        $content = FileContent::fromString(
            <<<'PHP'
                return RectorConfig::configure()
                    ->withSets([
                        'https://example.com/sets/a.php',
                    ]);
                PHP
        );

        self::assertSame($content, FluentChainWriter::ensureArrayEntry($content, 'withSets', "'https://example.com/sets/a.php'"));
    }

    public function testMatchesAnEntryWithAndWithoutTrailingComma(): void
    {
        $withComma = FileContent::fromString(
            <<<'PHP'
                return RectorConfig::configure()
                    ->withSets([
                        'a.php',
                    ]);
                PHP
        );
        $withoutComma = FileContent::fromString(
            <<<'PHP'
                return RectorConfig::configure()
                    ->withSets([
                        'a.php'
                    ]);
                PHP
        );

        self::assertSame($withComma, FluentChainWriter::ensureArrayEntry($withComma, 'withSets', "'a.php'"));
        self::assertSame($withoutComma, FluentChainWriter::ensureArrayEntry($withoutComma, 'withSets', "'a.php'"));
    }

    public function testInsertsIntoAnEmptyBlockArray(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'PHP'
                    return RectorConfig::configure()
                        ->withSets([
                            'a.php',
                        ]);
                    PHP
            ),
            FluentChainWriter::ensureArrayEntry(
                FileContent::fromString(
                    <<<'PHP'
                        return RectorConfig::configure()
                            ->withSets([
                            ]);
                        PHP
                ),
                'withSets',
                "'a.php'",
            ),
        );
    }

    public function testBracketsInsideStringsDoNotDerailTheScan(): void
    {
        $content = FileContent::fromString(
            <<<'PHP'
                return RectorConfig::configure()
                    ->withSets([
                        'a)]b.php',
                    ]);
                PHP
        );

        self::assertSame($content, FluentChainWriter::ensureArrayEntry($content, 'withSets', "'a)]b.php'"));
    }

    public function testCreatesTheCallAtTheEndOfAMultilineChain(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'PHP'
                    return RectorConfig::configure()
                        ->withDeadCodeLevel(10)
                        ->withSets([
                            'a.php',
                        ]);
                    PHP
            ),
            FluentChainWriter::ensureArrayEntry(
                FileContent::fromString(
                    <<<'PHP'
                        return RectorConfig::configure()
                            ->withDeadCodeLevel(10);
                        PHP
                ),
                'withSets',
                "'a.php'",
            ),
        );
    }

    public function testCreatesTheCallOnASingleLineStatement(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'PHP'
                    return RectorConfig::configure()
                        ->withSets([
                            'a.php',
                        ]);
                    PHP
            ),
            FluentChainWriter::ensureArrayEntry(FileContent::fromString('return RectorConfig::configure();'), 'withSets', "'a.php'"),
        );
    }

    public function testRefusesToAppendWithoutATerminatedStatement(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('terminated statement');

        FluentChainWriter::ensureArrayEntry(FileContent::fromString('return RectorConfig::configure()'), 'withSets', "'a.php'");
    }

    public function testRefusesASingleLineArray(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('one entry per line');

        FluentChainWriter::ensureArrayEntry("return RectorConfig::configure()->withSets(['a.php']);", 'withSets', "'b.php'");
    }

    public function testRefusesANonArrayArgument(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not an array');

        FluentChainWriter::ensureArrayEntry('return RectorConfig::configure()->withSets($sets);', 'withSets', "'a.php'");
    }

    public function testRefusesACallThatNeverCloses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('never closes');

        FluentChainWriter::ensureArrayEntry('return RectorConfig::configure()->withSets([;', 'withSets', "'a.php'");
    }

    public function testRendersTheBlockFormArrayCall(): void
    {
        self::assertSame(
            <<<'PHP'
                ->withSets([
                        __DIR__ . '/vendor/acme/standards/config/rector.php',
                    ])
                PHP,
            FluentChainWriter::createArrayCall('withSets', "__DIR__ . '/vendor/acme/standards/config/rector.php'"),
        );
    }
}
