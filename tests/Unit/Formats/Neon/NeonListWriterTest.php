<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Formats\Neon;

use AlleKnalle\StandardsSync\Formats\Neon\NeonListWriter;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(NeonListWriter::class)]
final class NeonListWriterTest extends TestCase
{
    public function testCreatesTheSectionWithTheEntryFromEmptyContent(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- vendor/acme/standards/phpstan.neon
                    NEON
            ),
            NeonListWriter::ensureEntry('', 'includes', 'vendor/acme/standards/phpstan.neon'),
        );
    }

    public function testCreatesAMissingSectionAtTheTopOfTheDocument(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 6
                NEON
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 6
                    NEON
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'),
        );
    }

    public function testKeepsContentWithThePresentEntryUnchanged(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/phpstan.neon
                NEON
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testACommentedEntryIsRecognizedAsPresent(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/phpstan.neon # the org baseline
                NEON
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testMatchesAQuotedEntryAgainstThePlainValue(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- 'vendor/acme/standards/phpstan.neon'
                NEON
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testAnEntryBelowACommentLineIsStillRecognized(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	# the org baseline
                	- vendor/acme/standards/phpstan.neon
                NEON
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testASectionHeaderWithATrailingCommentIsStillTheSection(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes: # org standards
                	- vendor/acme/standards/phpstan.neon
                NEON
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testInsertsAfterTheLastEntryCopyingItsIndentation(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                    - phar://phpstan.phar/conf/bleedingEdge.neon

                parameters:
                	level: 6
                NEON
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                        - phar://phpstan.phar/conf/bleedingEdge.neon
                        - vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 6
                    NEON
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'),
        );
    }

    public function testInsertsIntoAnEmptySectionUsingTheFileIndentation(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:

                parameters:
                	level: 6
                NEON
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 6
                    NEON
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'),
        );
    }

    public function testRefusesAnInlineFormSection(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes: [vendor/acme/standards/phpstan.neon]
                NEON
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('block list');

        NeonListWriter::ensureEntry($content, 'includes', 'vendor/other/phpstan.neon');
    }

    public function testOnlyTouchesTheNamedSection(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                services:
                	- App\Some\Service

                includes:
                	- vendor/acme/standards/phpstan.neon
                NEON
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    services:
                    	- App\Some\Service

                    includes:
                    	- vendor/acme/standards/phpstan.neon
                    	- vendor/acme/standards/strict.neon
                    NEON
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/strict.neon'),
        );
    }
}
