<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Neon;

use OrthoCode\StandardsSync\Formats\Neon\NeonListWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
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
                    NEON,
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
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 6
                    NEON,
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
                NEON,
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testACommentedEntryIsRecognizedAsPresent(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/phpstan.neon # the org baseline
                NEON,
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testMatchesAQuotedEntryAgainstThePlainValue(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- 'vendor/acme/standards/phpstan.neon'
                NEON,
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
                NEON,
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testASectionHeaderWithATrailingCommentIsStillTheSection(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes: # org standards
                	- vendor/acme/standards/phpstan.neon
                NEON,
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
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                        - phar://phpstan.phar/conf/bleedingEdge.neon
                        - vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 6
                    NEON,
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
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 6
                    NEON,
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'),
        );
    }

    public function testRefusesAnInlineFormSection(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes: [vendor/acme/standards/phpstan.neon]
                NEON,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('block list');

        NeonListWriter::ensureEntry($content, 'includes', 'vendor/other/phpstan.neon');
    }

    public function testAnEntryAtTheSectionsOwnIndentationIsRecognized(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                - vendor/acme/standards/phpstan.neon
                NEON,
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'));
    }

    public function testInsertsAfterAZeroIndentedEntryCopyingItsIndentation(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                - phpstan-baseline.neon

                parameters:
                	level: 6
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    - phpstan-baseline.neon
                    - vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 6
                    NEON,
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon'),
        );
    }

    public function testASupersededEntryAtTheSectionsOwnIndentationIsReplacedInPlace(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                - vendor/acme/standards/rules.neon
                - phpstan-baseline.neon
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    - vendor/acme/standards/phpstan.neon
                    - phpstan-baseline.neon
                    NEON,
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon', ['vendor/acme/standards/rules.neon']),
        );
    }

    public function testAHashWithoutWhitespaceBeforeItIsPartOfTheEntry(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/phpstan.neon#note
                NEON,
        );

        self::assertSame(['vendor/acme/standards/phpstan.neon#note'], NeonListWriter::readList($content, 'includes'));
    }

    public function testRefusesASectionHoldingAValueOnTheLinesBelow(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	-vendor/acme/standards/phpstan.neon
                NEON,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "includes:" section holds a value rather than a block list');

        NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/strict.neon');
    }

    public function testASupersededEntryIsReplacedInPlaceKeepingItsLine(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                    -  'vendor/acme/standards/rules.neon' # the org ruleset
                    - phpstan-baseline.neon
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                        -  vendor/acme/standards/phpstan.neon # the org ruleset
                        - phpstan-baseline.neon
                    NEON,
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon', ['vendor/acme/standards/rules.neon']),
        );
    }

    public function testOnlyTheFirstSupersededEntryIsReplaced(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/rules.neon
                	- vendor/acme/standards/strict.neon
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- vendor/acme/standards/phpstan.neon
                    	- vendor/acme/standards/strict.neon
                    NEON,
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon', ['vendor/acme/standards/strict.neon', 'vendor/acme/standards/rules.neon']),
        );
    }

    public function testAPresentEntryIsKeptWhateverItSupersedes(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/rules.neon
                	- vendor/acme/standards/phpstan.neon
                NEON,
        );

        self::assertSame($content, NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon', ['vendor/acme/standards/rules.neon']));
    }

    public function testAnEntrySupersedingNothingPresentIsInserted(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- phpstan-baseline.neon
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- phpstan-baseline.neon
                    	- vendor/acme/standards/phpstan.neon
                    NEON,
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/phpstan.neon', ['vendor/acme/standards/rules.neon']),
        );
    }

    public function testRemovesEveryLineHoldingOneOfTheEntries(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/strict.neon
                	# the baseline
                	- phpstan-baseline.neon
                	- 'vendor/acme/standards/strict.neon' # again
                	- vendor/acme/standards/rules.neon

                parameters:
                	level: 6
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	# the baseline
                    	- phpstan-baseline.neon

                    parameters:
                    	level: 6
                    NEON,
            ),
            NeonListWriter::removeEntries($content, 'includes', ['vendor/acme/standards/strict.neon', 'vendor/acme/standards/rules.neon']),
        );
    }

    public function testRemovingAbsentEntriesLeavesTheContentUntouched(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                services:
                	- vendor/acme/standards/strict.neon

                includes:
                	- phpstan-baseline.neon
                NEON,
        );

        self::assertSame($content, NeonListWriter::removeEntries($content, 'includes', ['vendor/acme/standards/strict.neon']));
        self::assertSame($content, NeonListWriter::removeEntries($content, 'excludes', ['vendor/acme/standards/strict.neon']));
        self::assertSame($content, NeonListWriter::removeEntries($content, 'includes', []));
    }

    public function testReadsTheSectionsEntriesUnquoted(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- 'vendor/acme/standards/phpstan.neon' # the org ruleset
                	- phpstan-baseline.neon
                NEON,
        );

        self::assertSame(['vendor/acme/standards/phpstan.neon', 'phpstan-baseline.neon'], NeonListWriter::readList($content, 'includes'));
        self::assertNull(NeonListWriter::readList($content, 'excludes'));
    }

    public function testOnlyTouchesTheNamedSection(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                services:
                	- App\Some\Service

                includes:
                	- vendor/acme/standards/phpstan.neon
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    services:
                    	- App\Some\Service

                    includes:
                    	- vendor/acme/standards/phpstan.neon
                    	- vendor/acme/standards/strict.neon
                    NEON,
            ),
            NeonListWriter::ensureEntry($content, 'includes', 'vendor/acme/standards/strict.neon'),
        );
    }
}
