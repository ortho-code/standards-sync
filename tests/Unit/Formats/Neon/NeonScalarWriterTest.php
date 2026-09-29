<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Neon;

use OrthoCode\StandardsSync\Formats\Neon\NeonScalarWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(NeonScalarWriter::class)]
final class NeonScalarWriterTest extends TestCase
{
    public function testReadsAScalarAtANestedPath(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 6
                	paths:
                		- src
                NEON,
        );

        self::assertSame('6', NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadsAValueWithoutItsTrailingComment(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 6 # keep in step with CI
                NEON,
        );

        self::assertSame('6', NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadsAHashWithoutWhitespaceBeforeItAsPartOfTheValue(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 6#x
                NEON,
        );

        self::assertSame('6#x', NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadsAQuotedValueVerbatim(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: '6'
                NEON,
        );

        self::assertSame('\'6\'', NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadsNullWhenTheKeyIsNotWritten(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	paths:
                		- src
                NEON,
        );

        self::assertNull(NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadsNullWhenTheSectionIsNotWritten(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/phpstan.neon
                NEON,
        );

        self::assertNull(NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadsNullWhenTheLeafOpensASectionInsteadOfAScalar(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level:
                		nested: 1
                NEON,
        );

        self::assertNull(NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadDoesNotMatchADeeperNestedKeyOfTheSameName(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	type_coverage:
                		level: 9
                NEON,
        );

        self::assertNull(NeonScalarWriter::read($content, ['parameters', 'level']));
    }

    public function testReadsNullFromEmptyContent(): void
    {
        self::assertNull(NeonScalarWriter::read('', ['parameters', 'level']));
    }

    public function testReplacesAWrittenValue(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 4
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 7
                    NEON,
            ),
            NeonScalarWriter::write($content, ['parameters', 'level'], 7),
        );
    }

    public function testReplacingWithTheWrittenValueReturnsTheContentUnchanged(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 7
                NEON,
        );

        self::assertSame($content, NeonScalarWriter::write($content, ['parameters', 'level'], 7));
    }

    public function testATrailingCommentSurvivesTheReplace(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 4 # keep in step with CI
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 7 # keep in step with CI
                    NEON,
            ),
            NeonScalarWriter::write($content, ['parameters', 'level'], 7),
        );
    }

    public function testInsertsAMissingLeafAsTheSectionsFirstChild(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	paths:
                		- src
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 7
                    	paths:
                    		- src
                    NEON,
            ),
            NeonScalarWriter::write($content, ['parameters', 'level'], 7),
        );
    }

    public function testCreatesAMissingTopLevelSectionAtTheEndOfTheDocument(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/phpstan.neon
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- vendor/acme/standards/phpstan.neon

                    parameters:
                    	level: 7
                    NEON,
            ),
            NeonScalarWriter::write($content, ['parameters', 'level'], 7),
        );
    }

    public function testCreatesTheWholePathFromEmptyContent(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 7
                    NEON,
            ),
            NeonScalarWriter::write('', ['parameters', 'level'], 7),
        );
    }

    public function testRendersABooleanAsNeonText(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	treatPhpDocTypesAsCertain: false
                    NEON,
            ),
            NeonScalarWriter::write('', ['parameters', 'treatPhpDocTypesAsCertain'], false),
        );
    }

    public function testQuotesAStringTheNeonGrammarNeedsQuoted(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	message: 'hello world'
                    NEON,
            ),
            NeonScalarWriter::write('', ['parameters', 'message'], 'hello world'),
        );
    }

    public function testAQuotedValueHoldingAHashIsNotACommentBoundary(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	pattern: '~foo#bar~'
                NEON,
        );

        self::assertSame('\'~foo#bar~\'', NeonScalarWriter::read($content, ['parameters', 'pattern']));
        self::assertSame($content, NeonScalarWriter::write($content, ['parameters', 'pattern'], '~foo#bar~'));
    }

    public function testACommentAfterAQuotedHashValueSurvivesTheReplace(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	pattern: '~foo#bar~' # the org pattern
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	pattern: '~other#thing~' # the org pattern
                    NEON,
            ),
            NeonScalarWriter::write($content, ['parameters', 'pattern'], '~other#thing~'),
        );
    }

    public function testAnEnforcedCommentIsWrittenWithTheValue(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 4 # we lowered this deliberately
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 7 # org minimum: raise freely
                    NEON,
            ),
            NeonScalarWriter::write($content, ['parameters', 'level'], 7, 'org minimum: raise freely'),
        );
    }

    public function testAnEnforcedCommentCarriesIntoACreatedLine(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 7 # org minimum: raise freely
                    NEON,
            ),
            NeonScalarWriter::write('', ['parameters', 'level'], 7, 'org minimum: raise freely'),
        );
    }

    public function testACanonicalValueAndCommentAreLeftUnchanged(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 7 # org minimum: raise freely
                NEON,
        );

        self::assertSame($content, NeonScalarWriter::write($content, ['parameters', 'level'], 7, 'org minimum: raise freely'));
    }

    public function testEnsuresATrailingCommentWithoutTouchingTheValueText(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: max
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: max # org minimum: raise freely
                    NEON,
            ),
            NeonScalarWriter::ensureTrailingComment($content, ['parameters', 'level'], 'org minimum: raise freely'),
        );
    }

    public function testEnsureTrailingCommentReplacesADeviatingComment(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 8 # our own note
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 8 # org minimum: raise freely
                    NEON,
            ),
            NeonScalarWriter::ensureTrailingComment($content, ['parameters', 'level'], 'org minimum: raise freely'),
        );
    }

    public function testEnsureTrailingCommentLeavesACanonicalLineAlone(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 8 # org minimum: raise freely
                NEON,
        );

        self::assertSame($content, NeonScalarWriter::ensureTrailingComment($content, ['parameters', 'level'], 'org minimum: raise freely'));
    }

    public function testEnsureTrailingCommentAbstainsOnAMissingKey(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	paths:
                		- src
                NEON,
        );

        self::assertSame($content, NeonScalarWriter::ensureTrailingComment($content, ['parameters', 'level'], 'org minimum: raise freely'));
    }

    public function testRefusesAScalarWhereASectionOpens(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level:
                		nested: 1
                NEON,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('holds a section');

        NeonScalarWriter::write($content, ['parameters', 'level'], 7);
    }

    public function testRefusesAPathBeneathAWrittenValue(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 6
                NEON,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('holds a value');

        NeonScalarWriter::write($content, ['parameters', 'level', 'nested'], 7);
    }

    public function testRefusesAValueMixingBothQuoteStyles(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mixes both quote styles');

        NeonScalarWriter::write('', ['parameters', 'message'], 'both \' and "');
    }
}
