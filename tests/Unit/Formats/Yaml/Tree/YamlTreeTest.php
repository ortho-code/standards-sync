<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Yaml\Tree;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlEntry;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlItem;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlScalarDecoder;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTreeParser;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValue;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValueKind;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(YamlTree::class)]
#[CoversClass(YamlTreeParser::class)]
#[CoversClass(YamlMapping::class)]
#[CoversClass(YamlEntry::class)]
#[CoversClass(YamlSequence::class)]
#[CoversClass(YamlItem::class)]
#[CoversClass(YamlValue::class)]
#[CoversClass(YamlScalarDecoder::class)]
final class YamlTreeTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function documents(): iterable
    {
        yield 'a workflow' => [FileContent::fromString(
            <<<'YAML'
                name: Coding standards

                on:
                  pull_request:
                  push:
                    branches:
                      - main
                      - master

                jobs:
                  standards:
                    runs-on: ubuntu-26.04
                    permissions:
                      contents: write
                    steps:
                      - uses: actions/checkout@v7 # the pinned major
                      # setup-php before the install
                      - uses: shivammathur/setup-php@v2
                        with:
                          php-version: '8.5'
                          coverage: none
                      - run: |
                          version="${GITHUB_REF_NAME#v}"
                          awk -v v="$version" '
                            $0 ~ "^## " v { found = 1 }
                          ' CHANGELOG.md > notes.md
                        env:
                          GH_TOKEN: ${{ github.token }}
                YAML,
        )];
        yield 'a sequence at its key\'s own indentation' => [FileContent::fromString(
            <<<'YAML'
                steps:
                - uses: actions/checkout@v7
                - run: composer app-checks
                after: 1
                YAML,
        )];
        yield 'items with a wider dash' => [FileContent::fromString(
            <<<'YAML'
                steps:
                  -   name: Checkout
                      uses: actions/checkout@v7
                  -   run: composer app-checks
                YAML,
        )];
        yield 'a four-space indent' => [FileContent::fromString(
            <<<'YAML'
                jobs:
                    standards:
                        steps:
                            -   uses: actions/checkout@v7
                                with:
                                    fetch-depth: 0
                YAML,
        )];
        yield 'quoted keys and values' => [FileContent::fromString(
            <<<'YAML'
                "double": "a \"quoted\" value: with a colon"
                'single': 'it''s # not a comment'
                plain: it's plain # a comment
                YAML,
        )];
        yield 'block scalars with every chomping indicator' => [FileContent::fromString(
            <<<'YAML'
                clip: |
                  a

                keep: |+
                  b

                strip: |-
                  c
                folded: >
                  d
                  e
                indented: |2
                    f
                last: 1
                YAML,
        )];
        yield 'multi-line plain scalars' => [FileContent::fromString(
            <<<'YAML'
                if: github.event_name == 'push' &&
                  github.ref == 'refs/heads/main'
                next:
                  needs.a.outputs.b ||
                  needs.a.outputs.c
                after: 1
                YAML,
        )];
        yield 'flow collections on one line and across lines' => [FileContent::fromString(
            <<<'YAML'
                branches: [main, 'release/**']
                with: { a: 1, b: "x, y" }
                matrix: [
                  '8.4',
                  '8.5',
                ]
                empty: []
                YAML,
        )];
        yield 'keys with no value' => [FileContent::fromString(
            <<<'YAML'
                on:
                  pull_request:
                  workflow_dispatch: # manual runs
                  push: ~
                last:
                YAML,
        )];
        yield 'comments and blank lines between items' => [FileContent::fromString(
            <<<'YAML'
                steps:

                  # first
                  - a

                  # second
                  - b # trailing
                YAML,
        )];
        yield 'a key with a space before its colon' => [FileContent::fromString(
            <<<'YAML'
                a:  b
                c :  d
                YAML,
        )];
        yield 'a document marker at the start' => [FileContent::fromString(
            <<<'YAML'
                ---
                a: 1
                YAML,
        )];
    }

    #[DataProvider('documents')]
    public function testReadsADocumentAsSymfonyYamlDoes(string $content): void
    {
        self::assertSame(Yaml::parse($content), YamlTree::fromString($content)->decoded());
    }

    public function testReadsCarriageReturnLineEndings(): void
    {
        // The carriage returns are the point: each line's ending is kept apart from its text.
        $content = "steps:\r\n  - run: |\r\n      a\r\n  - b\r\n";

        self::assertSame([
            'steps' => [[
                'run' => "a\n",
            ], 'b'],
        ], YamlTree::fromString($content)->decoded());
    }

    public function testReadsPastAByteOrderMark(): void
    {
        self::assertSame([
            'a' => 1,
        ], YamlTree::fromString("\u{FEFF}" . FileContent::fromString('a: 1'))->decoded());
    }

    public function testReadsABlockScalarEndingADocumentWithoutAFinalLineBreak(): void
    {
        $content = <<<'YAML'
            a: |
              x
            YAML;

        self::assertSame(Yaml::parse($content), YamlTree::fromString($content)->decoded());
    }

    public function testDecodesEachValueFromItsOwnText(): void
    {
        $tree = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                # one
                # two
                a:
                  - b: |
                      x
                c: 1
                YAML,
        ));

        // The final line break is the point: a clipped block scalar keeps it, whatever comments open the document.
        self::assertSame("x\n", $tree->valueAt(['a', 0, 'b'])?->decoded());
    }

    public function testLocatesASingleLineValueAndItsComment(): void
    {
        $tree = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                jobs:
                  standards:
                    runs-on: ubuntu-26.04 # the pinned runner
                YAML,
        ));
        $value = $tree->valueAt(['jobs', 'standards', 'runs-on']);

        self::assertNotNull($value);
        self::assertSame(YamlValueKind::Plain, $value->kind());
        self::assertSame('ubuntu-26.04', $value->source());
        self::assertSame(' the pinned runner', $value->comment());
        self::assertSame([2, 13, 25, 3], [$value->line(), $value->startColumn(), $value->endColumn(), $value->end()]);
        self::assertFalse($value->isMultiline());
    }

    public function testGivesABlockScalarItsWholeText(): void
    {
        $tree = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                run: |
                  a
                    b
                next: 1
                YAML,
        ));
        $value = $tree->valueAt(['run']);

        self::assertNotNull($value);
        self::assertSame(YamlValueKind::Block, $value->kind());
        self::assertSame(FileContent::fromString(
            <<<'YAML'
                |
                  a
                    b
                YAML,
        ), $value->source());
        self::assertSame([0, 3], [$value->line(), $value->end()]);
        self::assertTrue($value->isMultiline());
    }

    public function testLocatesSequenceItemsAndTheirDashes(): void
    {
        $tree = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                steps:
                  # the checkout leads
                  -   uses: actions/checkout@v7
                      with:
                        fetch-depth: 0
                  - run: composer app-checks
                YAML,
        ));
        $sequence = $tree->valueAt(['steps'])?->node();

        self::assertInstanceOf(YamlSequence::class, $sequence);
        self::assertSame([2, 1, 6], [$sequence->dashColumn(), $sequence->leadStart(), $sequence->end()]);

        [$checkout, $checks] = $sequence->items();
        self::assertSame([2, 2, 6], [$checkout->line(), $checkout->dashColumn(), $checkout->contentColumn()]);
        self::assertSame([5, 4], [$checks->line(), $checks->contentColumn()]);

        $mapping = $checkout->value()->node();
        self::assertInstanceOf(YamlMapping::class, $mapping);
        [$uses, $with] = $mapping->entries();
        self::assertSame(['uses', 2, 6, true], [$uses->key(), $uses->line(), $uses->column(), $uses->opensItem()]);
        self::assertSame(['with', 3, 6, false], [$with->key(), $with->line(), $with->column(), $with->opensItem()]);
    }

    public function testFollowsAPathOfKeysAndIndexesOnlyWhereItLeads(): void
    {
        $tree = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                name: Checks
                steps:
                  - run: a
                YAML,
        ));

        self::assertSame('a', $tree->valueAt(['steps', 0, 'run'])?->decoded());
        self::assertNull($tree->valueAt(['steps', 1]));
        self::assertNull($tree->valueAt(['missing']));
        self::assertNull($tree->valueAt(['name', 'x']));
        self::assertNull($tree->valueAt(['steps', 'run']));
        self::assertNull($tree->valueAt([]));
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusedDocuments(): iterable
    {
        yield 'an anchor' => [FileContent::fromString('a: &anchor 1'), 'Line 1 holds an anchor, an alias or a tag'];
        yield 'an alias' => [FileContent::fromString(
            <<<'YAML'
                a: 1
                b: *a
                YAML,
        ), 'Line 2 holds an anchor, an alias or a tag'];
        yield 'a tag' => [FileContent::fromString('a: !custom 1'), 'Line 1 holds an anchor, an alias or a tag'];
        yield 'a merge key' => [FileContent::fromString(
            <<<'YAML'
                a:
                  <<: {b: 1}
                YAML,
        ), 'Line 2 holds a merge key'];
        yield 'a complex key' => [FileContent::fromString('? a'), 'Line 1 holds a complex key'];
        yield 'a second document' => [FileContent::fromString(
            <<<'YAML'
                a: 1
                ---
                b: 2
                YAML,
        ), 'Line 2 starts a document or holds a directive'];
        yield 'a directive' => [FileContent::fromString(
            <<<'YAML'
                %YAML 1.2
                ---
                a: 1
                YAML,
        ), 'Line 1 starts a document or holds a directive'];
        yield 'tab indentation' => [FileContent::fromString(
            <<<'YAML'
                a:
                	b: 1
                YAML,
        ), 'Line 2 is indented with a tab'];
        yield 'a repeated key' => [FileContent::fromString(
            <<<'YAML'
                a: 1
                a: 2
                YAML,
        ), 'Line 2 repeats the key "a"'];
        yield 'a sequence as the document' => [FileContent::fromString('- a'), 'The document is a sequence'];
        yield 'a document of only comments' => [FileContent::fromString('# nothing'), 'The document is empty'];
        yield 'a quoted value on the line after its key' => [FileContent::fromString(
            <<<'YAML'
                a:
                  "b"
                YAML,
        ), 'Line 2 starts a value that is not a plain scalar on the line after its key'];
        yield 'a quoted value that never closes' => [FileContent::fromString('a: "b'), 'Line 1 opens a quoted value that never closes'];
        yield 'a sequence opening on a dash line' => [FileContent::fromString(
            <<<'YAML'
                a:
                  - - b
                YAML,
        ), 'Line 2 opens a sequence on a key\'s or a dash\'s line'];
        yield 'more after a closed value' => [FileContent::fromString('a: "b" c'), 'Line 1 holds more after a closed value'];
    }

    #[DataProvider('refusedDocuments')]
    public function testRefusesWhatItDoesNotHandle(string $content, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains($message);

        YamlTree::fromString($content);
    }
}
