<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Yaml;

use OrthoCode\StandardsSync\Formats\Yaml\YamlListWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(YamlListWriter::class)]
final class YamlListWriterTest extends TestCase
{
    public function testCreatesTheSectionWithTheEntryFromEmptyContent(): void
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

    public function testCreatesAMissingSectionAtTheTopOfTheDocument(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                deptrac:
                  paths:
                    - ./src
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - vendor/acme/standards/deptrac.yaml

                    deptrac:
                      paths:
                        - ./src
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'),
        );
    }

    public function testKeepsContentWithThePresentEntryUnchanged(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/deptrac.yaml
                YAML,
        );

        self::assertSame($content, YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'));
    }

    public function testACommentedEntryIsRecognizedAsPresent(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/deptrac.yaml # the org architecture
                YAML,
        );

        self::assertSame($content, YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'));
    }

    public function testMatchesAQuotedEntryAgainstThePlainValue(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - 'vendor/acme/standards/deptrac.yaml'
                YAML,
        );

        self::assertSame($content, YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'));
    }

    public function testAnEntryBelowACommentLineIsStillRecognized(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  # the org architecture
                  - vendor/acme/standards/deptrac.yaml
                YAML,
        );

        self::assertSame($content, YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'));
    }

    public function testASectionHeaderWithATrailingCommentIsStillTheSection(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports: # architecture standards
                  - vendor/acme/standards/deptrac.yaml
                YAML,
        );

        self::assertSame($content, YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'));
    }

    public function testAnEntryAtTheSectionsOwnIndentationIsRecognized(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                - vendor/acme/standards/deptrac.yaml
                YAML,
        );

        self::assertSame($content, YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'));
    }

    public function testInsertsAfterTheLastEntryCopyingItsIndentation(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                    - local/architecture.yaml

                deptrac:
                  paths:
                    - ./src
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                        - local/architecture.yaml
                        - vendor/acme/standards/deptrac.yaml

                    deptrac:
                      paths:
                        - ./src
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'),
        );
    }

    public function testInsertsAfterAZeroIndentedEntryCopyingItsIndentation(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                - local/architecture.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                    - local/architecture.yaml
                    - vendor/acme/standards/deptrac.yaml
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'),
        );
    }

    public function testInsertsIntoAnEmptySectionUsingTheFileIndentation(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:

                deptrac:
                  paths:
                    - ./src
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - vendor/acme/standards/deptrac.yaml

                    deptrac:
                      paths:
                        - ./src
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml'),
        );
    }

    public function testRefusesAnInlineFormSection(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports: [vendor/acme/standards/deptrac.yaml]
                YAML,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('block list');

        YamlListWriter::ensureEntry($content, 'imports', 'vendor/other/deptrac.yaml');
    }

    public function testRefusesASectionHoldingAValueOnTheLinesBelow(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  -vendor/acme/standards/deptrac.yaml
                YAML,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "imports:" section holds a value rather than a block list');

        YamlListWriter::ensureEntry($content, 'imports', 'vendor/other/deptrac.yaml');
    }

    public function testASupersededEntryIsReplacedInPlaceKeepingItsLine(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  -  'vendor/acme/standards/layers.yaml' # the org layers
                  - local/architecture.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      -  vendor/acme/standards/deptrac.yaml # the org layers
                      - local/architecture.yaml
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml', ['vendor/acme/standards/layers.yaml']),
        );
    }

    public function testASupersededEntryAtTheSectionsOwnIndentationIsReplacedInPlace(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                - vendor/acme/standards/layers.yaml
                - local/architecture.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                    - vendor/acme/standards/deptrac.yaml
                    - local/architecture.yaml
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml', ['vendor/acme/standards/layers.yaml']),
        );
    }

    public function testOnlyTheFirstSupersededEntryIsReplaced(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/layers.yaml
                  - vendor/acme/standards/strict.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - vendor/acme/standards/deptrac.yaml
                      - vendor/acme/standards/strict.yaml
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml', ['vendor/acme/standards/strict.yaml', 'vendor/acme/standards/layers.yaml']),
        );
    }

    public function testAPresentEntryIsKeptWhateverItSupersedes(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/layers.yaml
                  - vendor/acme/standards/deptrac.yaml
                YAML,
        );

        self::assertSame($content, YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml', ['vendor/acme/standards/layers.yaml']));
    }

    public function testAnEntrySupersedingNothingPresentIsInserted(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - local/architecture.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - local/architecture.yaml
                      - vendor/acme/standards/deptrac.yaml
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/deptrac.yaml', ['vendor/acme/standards/layers.yaml']),
        );
    }

    public function testRemovesEveryLineHoldingOneOfTheEntries(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/strict.yaml
                  # the local layers
                  - local/architecture.yaml
                  - 'vendor/acme/standards/strict.yaml' # again
                  - vendor/acme/standards/layers.yaml

                deptrac:
                  paths:
                    - ./src
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      # the local layers
                      - local/architecture.yaml

                    deptrac:
                      paths:
                        - ./src
                    YAML,
            ),
            YamlListWriter::removeEntries($content, 'imports', ['vendor/acme/standards/strict.yaml', 'vendor/acme/standards/layers.yaml']),
        );
    }

    public function testRemovingAbsentEntriesLeavesTheContentUntouched(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                services:
                  - vendor/acme/standards/strict.yaml

                imports:
                  - local/architecture.yaml
                YAML,
        );

        self::assertSame($content, YamlListWriter::removeEntries($content, 'imports', ['vendor/acme/standards/strict.yaml']));
        self::assertSame($content, YamlListWriter::removeEntries($content, 'excludes', ['vendor/acme/standards/strict.yaml']));
        self::assertSame($content, YamlListWriter::removeEntries($content, 'imports', []));
    }

    public function testReadsTheSectionsEntriesUnquoted(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - 'vendor/acme/standards/deptrac.yaml' # the org layers
                  - local/architecture.yaml
                YAML,
        );

        self::assertSame(['vendor/acme/standards/deptrac.yaml', 'local/architecture.yaml'], YamlListWriter::readList($content, 'imports'));
        self::assertNull(YamlListWriter::readList($content, 'excludes'));
    }

    public function testOnlyTouchesTheNamedSection(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                services:
                  - App\Some\Service

                imports:
                  - vendor/acme/standards/deptrac.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    services:
                      - App\Some\Service

                    imports:
                      - vendor/acme/standards/deptrac.yaml
                      - vendor/acme/standards/strict.yaml
                    YAML,
            ),
            YamlListWriter::ensureEntry($content, 'imports', 'vendor/acme/standards/strict.yaml'),
        );
    }
}
