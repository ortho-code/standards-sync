<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Yaml;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlEntry;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlItem;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\YamlFragment;
use OrthoCode\StandardsSync\Formats\Yaml\YamlStyle;
use OrthoCode\StandardsSync\Formats\Yaml\YamlTreeWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(YamlTreeWriter::class)]
#[CoversClass(YamlFragment::class)]
#[CoversClass(YamlStyle::class)]
final class YamlTreeWriterTest extends TestCase
{
    public function testRaisesARefKeepingItsComment(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      - uses: actions/checkout@v7 # pinned
                    YAML,
            ),
            YamlTreeWriter::replaceScalar(FileContent::fromString(
                <<<'YAML'
                    steps:
                      - uses: actions/checkout@v6 # pinned
                    YAML,
            ), ['steps', 0, 'uses'], 'actions/checkout@v7'),
        );
    }

    public function testReplacesADigestPinTogetherWithItsComment(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      - uses: actions/checkout@v7
                    YAML,
            ),
            YamlTreeWriter::replaceScalar(FileContent::fromString(
                <<<'YAML'
                    steps:
                      - uses: actions/checkout@0123456789abcdef0123456789abcdef01234567 # v6.0.1
                    YAML,
            ), ['steps', 0, 'uses'], 'actions/checkout@v7', keepComment: false),
        );
    }

    public function testWritesAScalarAsItsSourceReads(): void
    {
        self::assertSame(
            FileContent::fromString('php-version: \'8.5\''),
            YamlTreeWriter::replaceScalar(FileContent::fromString('php-version: 8.4'), ['php-version'], '\'8.5\''),
        );
    }

    public function testRefusesToReplaceABlockScalarInPlace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"run" does not hold a single-line scalar');

        YamlTreeWriter::replaceScalar(FileContent::fromString(
            <<<'YAML'
                run: |
                  a
                YAML,
        ), ['run'], 'b');
    }

    public function testRefusesAPathThatLeadsNowhere(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"steps.0.uses" leads to no value');

        YamlTreeWriter::replaceScalar(FileContent::fromString('steps: []'), ['steps', 0, 'uses'], 'a');
    }

    public function testReplacesAValueInTheDocumentsOwnStyle(): void
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches:
                      - main
                      - master
                YAML,
        ));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                        push:
                            branches:
                                - main
                                - master
                    jobs: {}
                    YAML,
            ),
            YamlTreeWriter::replaceValue(FileContent::fromString(
                <<<'YAML'
                    on:
                        push:
                            branches: [main, '!release/**']
                    jobs: {}
                    YAML,
            ), ['on', 'push', 'branches'], YamlFragment::fromValue($template, self::entry($template, ['on', 'push'], 'branches'))),
        );
    }

    public function testReplacesAScalarWithABlockScalarKeepingItsInnerIndentation(): void
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                run: |
                  a
                    b
                YAML,
        ));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    run: |
                      a
                        b
                    next: 1
                    YAML,
            ),
            YamlTreeWriter::replaceValue(FileContent::fromString(
                <<<'YAML'
                    run: echo old
                    next: 1
                    YAML,
            ), ['run'], YamlFragment::fromValue($template, self::entry($template, [], 'run'))),
        );
    }

    public function testAddsAnEntryWithItsCommentInTheDocumentsIndentation(): void
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                jobs:
                  standards:
                    # the job's token needs to push
                    permissions:
                      contents: write
                YAML,
        ));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    jobs:
                        standards:
                            runs-on: ubuntu-26.04
                            # the job's token needs to push
                            permissions:
                                contents: write
                        other:
                            runs-on: ubuntu-26.04
                    YAML,
            ),
            YamlTreeWriter::addEntry(FileContent::fromString(
                <<<'YAML'
                    jobs:
                        standards:
                            runs-on: ubuntu-26.04
                        other:
                            runs-on: ubuntu-26.04
                    YAML,
            ), ['jobs', 'standards'], YamlFragment::fromEntry($template, self::entry($template, ['jobs', 'standards'], 'permissions'))),
        );
    }

    public function testAddsAnEntryToAnItemsMapping(): void
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                steps:
                  - uses: shivammathur/setup-php@v2
                    with:
                      php-version: '8.5'
                YAML,
        ));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      -   uses: shivammathur/setup-php@v2
                          with:
                            php-version: '8.5'
                      -   run: composer app-checks
                    YAML,
            ),
            YamlTreeWriter::addEntry(FileContent::fromString(
                <<<'YAML'
                    steps:
                      -   uses: shivammathur/setup-php@v2
                      -   run: composer app-checks
                    YAML,
            ), ['steps', 0], YamlFragment::fromEntry($template, self::entry($template, ['steps', 0], 'with'))),
        );
    }

    public function testGivesAKeyHoldingNoValueANestedEntryWithTheDocumentsSequenceStyle(): void
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                on:
                  pull_request:
                    branches:
                      - main
                YAML,
        ));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                        branches:
                        - main
                      push:
                        branches:
                        - main
                    YAML,
            ),
            YamlTreeWriter::addEntry(FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                      push:
                        branches:
                        - main
                    YAML,
            ), ['on', 'pull_request'], YamlFragment::fromEntry($template, self::entry($template, ['on', 'pull_request'], 'branches'))),
        );
    }

    public function testAddsAScalarEntry(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      - run: composer app-checks
                        id: checks
                    YAML,
            ),
            YamlTreeWriter::addEntry(FileContent::fromString(
                <<<'YAML'
                    steps:
                      - run: composer app-checks
                    YAML,
            ), ['steps', 0], YamlFragment::fromScalarEntry('id', 'checks')),
        );
    }

    public function testRefusesToAddAnEntryToAScalar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"a" holds neither a block mapping nor nothing');

        YamlTreeWriter::addEntry(FileContent::fromString('a: 1'), ['a'], YamlFragment::fromScalarEntry('b', '2'));
    }

    public function testInsertsAnItemAfterTheOneBeforeItWithTheSequencesDash(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      # checkout first
                      -   uses: actions/checkout@v7
                      -   uses: shivammathur/setup-php@v2
                          with:
                            php-version: '8.5'
                      -   run: composer app-checks
                    YAML,
            ),
            YamlTreeWriter::insertItem(self::checkoutAndChecks(), ['steps'], 1, self::setupPhp()),
        );
    }

    public function testInsertsAFirstItemBeforeTheCommentsLeadingTheOldFirst(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      -   uses: shivammathur/setup-php@v2
                          with:
                            php-version: '8.5'
                      # checkout first
                      -   uses: actions/checkout@v7
                      -   run: composer app-checks
                    YAML,
            ),
            YamlTreeWriter::insertItem(self::checkoutAndChecks(), ['steps'], 0, self::setupPhp()),
        );
    }

    public function testInsertsAnItemAfterTheLast(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      # checkout first
                      -   uses: actions/checkout@v7
                      -   run: composer app-checks
                      -   uses: shivammathur/setup-php@v2
                          with:
                            php-version: '8.5'
                    YAML,
            ),
            YamlTreeWriter::insertItem(self::checkoutAndChecks(), ['steps'], 2, self::setupPhp()),
        );
    }

    public function testInsertsAnItemWithItsCommentAndItsScriptsInnerIndentation(): void
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                steps:
                  # extracts the notes
                  - run: |
                      awk '
                        { print }
                      ' CHANGELOG.md
                    env:
                      GH_TOKEN: x
                YAML,
        ));
        $written = YamlTreeWriter::insertItem(FileContent::fromString(
            <<<'YAML'
                jobs:
                    release:
                        steps:
                            -   run: echo first
                YAML,
        ), ['jobs', 'release', 'steps'], 1, YamlFragment::fromItem($template, self::item($template, ['steps'], 0)));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    jobs:
                        release:
                            steps:
                                -   run: echo first
                                # extracts the notes
                                -   run: |
                                      awk '
                                        { print }
                                      ' CHANGELOG.md
                                    env:
                                        GH_TOKEN: x
                    YAML,
            ),
            $written,
        );
        self::assertSame($template->valueAt(['steps', 0])?->decoded(), YamlTree::fromString($written)->valueAt(['jobs', 'release', 'steps', 1])?->decoded());
    }

    public function testInsertsWithTheDocumentsLineEnding(): void
    {
        // The carriage returns are the point: inserted lines take the document's ending.
        self::assertSame(
            "steps:\r\n  - a\r\n  - b\r\n",
            YamlTreeWriter::appendScalarItem("steps:\r\n  - a\r\n", ['steps'], 'b'),
        );
    }

    public function testAppendsToABlockSequenceAtItsKeysIndentation(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    branches:
                    - main
                    - master
                    after: 1
                    YAML,
            ),
            YamlTreeWriter::appendScalarItem(FileContent::fromString(
                <<<'YAML'
                    branches:
                    - main
                    after: 1
                    YAML,
            ), ['branches'], 'master'),
        );
    }

    public function testAppendsToAFlowSequenceOnOneLine(): void
    {
        self::assertSame(FileContent::fromString('branches: [main, master] # both'), YamlTreeWriter::appendScalarItem(FileContent::fromString('branches: [main] # both'), ['branches'], 'master'));
        self::assertSame(FileContent::fromString('branches: [ main, master ]'), YamlTreeWriter::appendScalarItem(FileContent::fromString('branches: [ main ]'), ['branches'], 'master'));
        self::assertSame(FileContent::fromString('branches: [main, master]'), YamlTreeWriter::appendScalarItem(FileContent::fromString('branches: [main,]'), ['branches'], 'master'));
        self::assertSame(FileContent::fromString('branches: [master]'), YamlTreeWriter::appendScalarItem(FileContent::fromString('branches: []'), ['branches'], 'master'));
    }

    public function testRefusesToAppendToAString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"branches" holds neither a block sequence nor a flow sequence on one line');

        YamlTreeWriter::appendScalarItem(FileContent::fromString('branches: main'), ['branches'], 'master');
    }

    public function testRemovesAnItemWithTheCommentsDirectlyAboveIt(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      - uses: a

                      - run: c
                    YAML,
            ),
            YamlTreeWriter::removeItem(FileContent::fromString(
                <<<'YAML'
                    steps:
                      - uses: a

                      # the checks
                      - run: b
                      - run: c
                    YAML,
            ), ['steps'], 1),
        );
    }

    public function testRemovingTheLastLineLeavesTheBlockScalarAboveItsFinalLineBreak(): void
    {
        $content = <<<'YAML'
            steps:
              - run: |
                  a
              - b
            YAML;
        $written = YamlTreeWriter::removeItem($content, ['steps'], 1);

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      - run: |
                          a
                    YAML,
            ),
            $written,
        );
        // The final line break is the point: the script keeps it, as it had it before the item below it went.
        self::assertSame("a\n", YamlTree::fromString($written)->valueAt(['steps', 0, 'run'])?->decoded());
    }

    public function testInsertingAfterTheLastLineGivesTheInsertedBlockScalarItsFinalLineBreak(): void
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                steps:
                  - run: |
                      b
                YAML,
        ));
        $content = <<<'YAML'
            steps:
              - a
            YAML;
        $written = YamlTreeWriter::insertItem($content, ['steps'], 1, YamlFragment::fromItem($template, self::item($template, ['steps'], 0)));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      - a
                      - run: |
                          b
                    YAML,
            ),
            $written,
        );
        // The final line break is the point: the inserted script means what the template's does.
        self::assertSame("b\n", YamlTree::fromString($written)->valueAt(['steps', 1, 'run'])?->decoded());
    }

    public function testRefusesToRemoveTheOnlyItem(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"steps" holds only this item');

        YamlTreeWriter::removeItem(FileContent::fromString(
            <<<'YAML'
                steps:
                  - a
                YAML,
        ), ['steps'], 0);
    }

    public function testRemovesAnEntryWithTheCommentsDirectlyAboveIt(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    job:
                      runs-on: ubuntu-26.04
                      steps: []
                    YAML,
            ),
            YamlTreeWriter::removeEntry(FileContent::fromString(
                <<<'YAML'
                    job:
                      runs-on: ubuntu-26.04
                      # never fail
                      continue-on-error: true
                      steps: []
                    YAML,
            ), ['job'], 'continue-on-error'),
        );
    }

    public function testRemovingTheKeyAnItemOpensWithMovesTheDashToItsNextKey(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    steps:
                      - run: composer app-checks
                    YAML,
            ),
            YamlTreeWriter::removeEntry(FileContent::fromString(
                <<<'YAML'
                    steps:
                      - if: false
                        run: composer app-checks
                    YAML,
            ), ['steps', 0], 'if'),
        );
    }

    public function testRefusesToRemoveTheOnlyEntry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"job" holds only the entry "steps"');

        YamlTreeWriter::removeEntry(FileContent::fromString(
            <<<'YAML'
                job:
                  steps: []
                YAML,
        ), ['job'], 'steps');
    }

    private static function checkoutAndChecks(): string
    {
        return FileContent::fromString(
            <<<'YAML'
                steps:
                  # checkout first
                  -   uses: actions/checkout@v7
                  -   run: composer app-checks
                YAML,
        );
    }

    private static function setupPhp(): YamlFragment
    {
        $template = YamlTree::fromString(FileContent::fromString(
            <<<'YAML'
                steps:
                  - uses: shivammathur/setup-php@v2
                    with:
                      php-version: '8.5'
                YAML,
        ));

        return YamlFragment::fromItem($template, self::item($template, ['steps'], 0));
    }

    /** @param list<string|int> $mappingPath */
    private static function entry(YamlTree $tree, array $mappingPath, string $key): YamlEntry
    {
        $mapping = $mappingPath === [] ? $tree->root() : $tree->valueAt($mappingPath)?->node();
        self::assertInstanceOf(YamlMapping::class, $mapping);
        $entry = $mapping->entry($key);
        self::assertNotNull($entry);

        return $entry;
    }

    /** @param list<string|int> $sequencePath */
    private static function item(YamlTree $tree, array $sequencePath, int $index): YamlItem
    {
        $sequence = $tree->valueAt($sequencePath)?->node();
        self::assertInstanceOf(YamlSequence::class, $sequence);

        return $sequence->items()[$index];
    }
}
