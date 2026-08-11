<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Formats\Xml;

use AlleKnalle\StandardsSync\Formats\Xml\XmlElementWriter;
use AlleKnalle\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(XmlElementWriter::class)]
final class XmlElementWriterTest extends TestCase
{
    public function testReadsAnAttributeFromASingleLineOpenTag(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm errorLevel="3">
                </psalm>
                XML
        );

        self::assertSame('3', XmlElementWriter::readAttribute($content, 'psalm', 'errorLevel'));
    }

    public function testReadsAnAttributeFromAMultilineOpenTag(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                    resolveFromConfigFile="true"
                    xmlns="https://getpsalm.org/schema/config"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML
        );

        self::assertSame('1', XmlElementWriter::readAttribute($content, 'psalm', 'errorLevel'));
    }

    public function testReadsASingleQuotedValue(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm errorLevel='3'>
                </psalm>
                XML
        );

        self::assertSame('3', XmlElementWriter::readAttribute($content, 'psalm', 'errorLevel'));
    }

    public function testReadsNullWhenTheAttributeIsNotWritten(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm resolveFromConfigFile="true">
                </psalm>
                XML
        );

        self::assertNull(XmlElementWriter::readAttribute($content, 'psalm', 'errorLevel'));
    }

    public function testReadsTheRawValueWithoutEntityDecoding(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm autoloader="a&amp;b.php">
                </psalm>
                XML
        );

        self::assertSame('a&amp;b.php', XmlElementWriter::readAttribute($content, 'psalm', 'autoloader'));
    }

    public function testReadsPastACommentedOutOpenTag(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <!-- the old config: <psalm errorLevel="8"> -->
                <psalm errorLevel="3">
                </psalm>
                XML
        );

        self::assertSame('3', XmlElementWriter::readAttribute($content, 'psalm', 'errorLevel'));
    }

    public function testReadsPastAnElementSharingTheNamePrefix(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalmodie level="8">
                <psalm errorLevel="3">
                </psalm>
                </psalmodie>
                XML
        );

        self::assertSame('3', XmlElementWriter::readAttribute($content, 'psalm', 'errorLevel'));
    }

    public function testReadsThroughAQuotedGreaterThan(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm title="a>b" errorLevel="3">
                </psalm>
                XML
        );

        self::assertSame('3', XmlElementWriter::readAttribute($content, 'psalm', 'errorLevel'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function unmanageableFiles(): iterable
    {
        yield 'no open tag' => [
            <<<'XML'
                <other errorLevel="3" />
                XML,
            'No <psalm> open tag found; the file cannot be managed.',
        ];
        yield 'more than one open tag' => [
            <<<'XML'
                <psalm errorLevel="1"></psalm>
                <psalm errorLevel="2"></psalm>
                XML,
            'More than one <psalm> open tag found; the file cannot be managed.',
        ];
        yield 'unterminated open tag' => [
            <<<'XML'
                <psalm errorLevel="1"
                XML,
            'The <psalm> open tag never closes; the file cannot be managed.',
        ];
        yield 'unterminated comment' => [
            <<<'XML'
                <!-- gone
                <psalm errorLevel="1">
                </psalm>
                XML,
            'An XML comment never closes; the file cannot be managed.',
        ];
        yield 'duplicate attribute' => [
            <<<'XML'
                <psalm errorLevel="1" errorLevel="2">
                </psalm>
                XML,
            'The <psalm> open tag sets "errorLevel" more than once; the file cannot be managed.',
        ];
        yield 'valueless attribute' => [
            <<<'XML'
                <psalm phpVersion errorLevel="1">
                </psalm>
                XML,
            'Unrecognized content "phpVersion" in the <psalm> open tag; the file cannot be managed.',
        ];
    }

    #[DataProvider('unmanageableFiles')]
    public function testReadingFailsLoudOnAnUnmanageableFile(string $body, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains($message);

        XmlElementWriter::readAttribute(FileContent::fromString($body), 'psalm', 'errorLevel');
    }

    public function testWritingFailsLoudWithoutAnOpenTag(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('No <psalm> open tag found; the file cannot be managed.');

        XmlElementWriter::writeAttribute(FileContent::fromString('<other />'), 'psalm', 'errorLevel', '2');
    }

    public function testReplacesAValueInAMultilineOpenTagKeepingEverythingElse(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm
                    errorLevel="8"
                    resolveFromConfigFile="true"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm
                    errorLevel="4"
                    resolveFromConfigFile="true"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '4'));
    }

    public function testReplacesASingleQuotedValueKeepingItsQuotes(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm errorLevel='8'>
                </psalm>
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <psalm errorLevel='4'>
                </psalm>
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '4'));
    }

    public function testReplacingWithTheWrittenValueReturnsTheContentUnchanged(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm errorLevel="4">
                </psalm>
                XML
        );

        self::assertSame($content, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '4'));
    }

    public function testReplacesOnASelfClosingTag(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm errorLevel="8" />
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <psalm errorLevel="4" />
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '4'));
    }

    public function testInsertsAfterTheLastAttributeOnASingleLineTag(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm resolveFromConfigFile="true">
                </psalm>
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <psalm resolveFromConfigFile="true" errorLevel="2">
                </psalm>
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '2'));
    }

    public function testInsertsOnANewLineCopyingTheIndentationOfTheLastAttribute(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm
                    resolveFromConfigFile="true"
                    xmlns="https://getpsalm.org/schema/config"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm
                    resolveFromConfigFile="true"
                    xmlns="https://getpsalm.org/schema/config"
                    errorLevel="2"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '2'));
    }

    public function testInsertsCopyingTabIndentation(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm
                	resolveFromConfigFile="true"
                >
                </psalm>
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <psalm
                	resolveFromConfigFile="true"
                	errorLevel="2"
                >
                </psalm>
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '2'));
    }

    public function testInsertsInlineIntoAnAttributelessTag(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm>
                </psalm>
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <psalm errorLevel="2">
                </psalm>
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '2'));
    }

    public function testInsertsBeforeTheSelfClosingSlash(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm resolveFromConfigFile="true" />
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <psalm resolveFromConfigFile="true" errorLevel="2" />
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '2'));
    }

    public function testInsertsInlineWhenTheLastAttributeSharesTheElementNameLine(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm resolveFromConfigFile="true"
                >
                </psalm>
                XML
        );

        $expected = FileContent::fromString(
            <<<'XML'
                <psalm resolveFromConfigFile="true" errorLevel="2"
                >
                </psalm>
                XML
        );

        self::assertSame($expected, XmlElementWriter::writeAttribute($content, 'psalm', 'errorLevel', '2'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function valuesNeedingEncoding(): iterable
    {
        yield 'an ampersand' => ['a&b', '&'];
        yield 'an angle bracket' => ['a<b', '<'];
        yield 'the wrapping quote' => ['a"b', '"'];
    }

    #[DataProvider('valuesNeedingEncoding')]
    public function testWritingRefusesAValueNeedingEntityEncoding(string $value, string $needsEncoding): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm>
                </psalm>
                XML
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(sprintf('"%s" would need entity encoding', $needsEncoding));

        XmlElementWriter::writeAttribute($content, 'psalm', 'autoloader', $value);
    }

    public function testReplacingIntoSingleQuotesRefusesAValueCarryingThatQuote(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
                <psalm autoloader='old.php'>
                </psalm>
                XML
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('"\'" would need entity encoding');

        XmlElementWriter::writeAttribute($content, 'psalm', 'autoloader', "it's.php");
    }
}
