<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Json;

use OrthoCode\StandardsSync\Formats\Json\JsonObjectWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(JsonObjectWriter::class)]
final class JsonObjectWriterTest extends TestCase
{
    public function testReadsAStringAtANestedPath(): void
    {
        self::assertSame('^2.5', JsonObjectWriter::read(self::manifest(), ['require-dev', 'phpstan/phpstan']));
    }

    public function testReadsNullForAnAbsentMember(): void
    {
        self::assertNull(JsonObjectWriter::read(self::manifest(), ['require-dev', 'vimeo/psalm']));
    }

    public function testReadsNullForAnAbsentSection(): void
    {
        self::assertNull(JsonObjectWriter::read(self::manifest(), ['conflict', 'vimeo/psalm']));
    }

    /** A key composer wrote with an escaped slash names the same member as the plain spelling. */
    public function testMatchesAKeyWrittenWithAnEscapedSlash(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "require-dev": {
                        "phpstan\/phpstan": "^2.5"
                    }
                }
                JSON,
        );

        self::assertSame('^2.5', JsonObjectWriter::read($content, ['require-dev', 'phpstan/phpstan']));
    }

    public function testRefusesToReadANonStringAsAString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('"config.sort-packages" does not hold a string');

        JsonObjectWriter::read(self::manifest(), ['config', 'sort-packages']);
    }

    public function testReadsAListOfStrings(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-checks": [
                            "@app-sync-check",
                            "@app-run-tests"
                        ]
                    }
                }
                JSON,
        );

        self::assertSame(['@app-sync-check', '@app-run-tests'], JsonObjectWriter::readList($content, ['scripts', 'app-checks']));
    }

    public function testReadsALoneStringAsAOneEntryList(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-checks": "@app-sync-check"
                    }
                }
                JSON,
        );

        self::assertSame(['@app-sync-check'], JsonObjectWriter::readList($content, ['scripts', 'app-checks']));
    }

    public function testReadsNullForAnAbsentList(): void
    {
        self::assertNull(JsonObjectWriter::readList(self::manifest(), ['scripts', 'app-checks']));
    }

    public function testRefusesToReadANonListAsAList(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('"config.sort-packages" does not hold a string or a list of strings');

        JsonObjectWriter::readList(self::manifest(), ['config', 'sort-packages']);
    }

    public function testReplacesAnExistingValueLeavingEverythingElseByteIdentical(): void
    {
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.6",
                        "rector/rector": "^2.5"
                    },
                    "config": {
                        "sort-packages": true
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::write(self::manifest(), ['require-dev', 'phpstan/phpstan'], '^2.6'));
    }

    public function testWritingAnEqualValueLeavesTheContentUntouched(): void
    {
        $content = self::manifest();

        self::assertSame($content, JsonObjectWriter::write($content, ['require-dev', 'phpstan/phpstan'], '^2.5'));
    }

    public function testAppendsAMemberAfterTheLastOneCopyingItsIndentation(): void
    {
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5",
                        "rector/rector": "^2.5",
                        "vimeo/psalm": "^6"
                    },
                    "config": {
                        "sort-packages": true
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::write(self::manifest(), ['require-dev', 'vimeo/psalm'], '^6'));
    }

    /** An object the writer creates is written in the canonical shape, whatever the file's other objects look like. */
    public function testCreatesAMissingObjectOnItsOwnLines(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project"
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5"
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::write($content, ['require-dev', 'phpstan/phpstan'], '^2.5'));
    }

    public function testFillsAnEmptyObjectOnItsOwnLines(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {}
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5"
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::write($content, ['require-dev', 'phpstan/phpstan'], '^2.5'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function indentations(): iterable
    {
        yield 'two spaces' => [
            FileContent::fromString(
                <<<'JSON'
                    {
                      "name": "acme/project"
                    }
                    JSON,
            ),
            FileContent::fromString(
                <<<'JSON'
                    {
                      "name": "acme/project",
                      "require-dev": {
                        "phpstan/phpstan": "^2.5"
                      }
                    }
                    JSON,
            ),
        ];
        yield 'four spaces' => [
            FileContent::fromString(
                <<<'JSON'
                    {
                        "name": "acme/project"
                    }
                    JSON,
            ),
            FileContent::fromString(
                <<<'JSON'
                    {
                        "name": "acme/project",
                        "require-dev": {
                            "phpstan/phpstan": "^2.5"
                        }
                    }
                    JSON,
            ),
        ];
        yield 'tabs' => [
            FileContent::fromString(
                <<<'JSON'
                    {
                    	"name": "acme/project"
                    }
                    JSON,
            ),
            FileContent::fromString(
                <<<'JSON'
                    {
                    	"name": "acme/project",
                    	"require-dev": {
                    		"phpstan/phpstan": "^2.5"
                    	}
                    }
                    JSON,
            ),
        ];
    }

    #[DataProvider('indentations')]
    public function testInsertionCopiesTheFilesOwnIndentation(string $content, string $expected): void
    {
        self::assertSame($expected, JsonObjectWriter::write($content, ['require-dev', 'phpstan/phpstan'], '^2.5'));
    }

    /** An object the project wrote on one line keeps that layout, so a sync never relayouts what it did not create. */
    public function testKeepsAOneLineObjectOnOneLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "config": {"sort-packages": true}
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "config": {"sort-packages": true, "vendor-dir": "vendor"}
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::write($content, ['config', 'vendor-dir'], 'vendor'));
    }

    /** A list inserted into a one-line object renders on that line too, rather than breaking the layout it is joining. */
    public function testRendersAListInlineWhenTheObjectIsOnOneLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {"test": "phpunit"}
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {"test": "phpunit", "check": ["one", "two"]}
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::writeList($content, ['scripts', 'check'], ['one', 'two']));
    }

    public function testWritesAListOneEntryPerLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project"
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "scripts": {
                        "app-check-standards": [
                            "vendor/bin/standards-sync sync --check"
                        ]
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::writeList($content, ['scripts', 'app-check-standards'], ['vendor/bin/standards-sync sync --check']));
    }

    /** @return iterable<string, array{string|bool|int, string}> */
    public static function scalars(): iterable
    {
        yield 'a boolean' => [true, 'true'];
        yield 'a false boolean' => [false, 'false'];
        yield 'an integer' => [900, '900'];
        yield 'a string' => ['vendor', '"vendor"'];
    }

    /** A boolean must land as JSON's own literal, not as the text "true". */
    #[DataProvider('scalars')]
    public function testWritesAScalarInItsJsonForm(string|bool|int $value, string $written): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "config": {
                        "optimize-autoloader": true
                    }
                }
                JSON,
        );

        $result = JsonObjectWriter::write($content, ['config', 'sort-packages'], $value);

        self::assertStringContainsString('"sort-packages": ' . $written, $result);
    }

    public function testWritingAnEqualBooleanLeavesTheContentUntouched(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "config": {
                        "sort-packages": true
                    }
                }
                JSON,
        );

        self::assertSame($content, JsonObjectWriter::write($content, ['config', 'sort-packages'], true));
    }

    public function testWritesEveryEntryOfAMultiEntryListOnItsOwnLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project"
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "scripts": {
                        "app-check-standards": [
                            "vendor/bin/standards-sync sync --check",
                            "vendor/bin/phpstan"
                        ]
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::writeList($content, ['scripts', 'app-check-standards'], ['vendor/bin/standards-sync sync --check', 'vendor/bin/phpstan']));
    }

    public function testReplacesAStringValuedScriptWithTheListForm(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-check-standards": "vendor/bin/standards-sync sync"
                    }
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-check-standards": [
                            "vendor/bin/standards-sync sync --check"
                        ]
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::writeList($content, ['scripts', 'app-check-standards'], ['vendor/bin/standards-sync sync --check']));
    }

    /** An equal list is left alone whatever layout it was written in, so a sync never reformats for its own sake. */
    public function testWritingAnEqualListLeavesADifferentLayoutUntouched(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-check-standards": ["one", "two"]
                    }
                }
                JSON,
        );

        self::assertSame($content, JsonObjectWriter::writeList($content, ['scripts', 'app-check-standards'], ['one', 'two']));
    }

    public function testRemovesAMiddleMemberWithItsComma(): void
    {
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "rector/rector": "^2.5"
                    },
                    "config": {
                        "sort-packages": true
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::remove(self::manifest(), ['require-dev', 'phpstan/phpstan']));
    }

    public function testRemovesTheLastMemberTakingThePrecedingComma(): void
    {
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5"
                    },
                    "config": {
                        "sort-packages": true
                    }
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::remove(self::manifest(), ['require-dev', 'rector/rector']));
    }

    public function testRemovingTheOnlyMemberCollapsesTheObject(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "require-dev": {
                        "phpstan/phpstan": "^2.5"
                    }
                }
                JSON,
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "require-dev": {}
                }
                JSON,
        );

        self::assertSame($expected, JsonObjectWriter::remove($content, ['require-dev', 'phpstan/phpstan']));
    }

    public function testRemovesFromAOneLineObject(): void
    {
        $content = FileContent::fromString('{"require": {"a/a": "^1.0", "b/b": "^2.0"}}');

        self::assertSame(FileContent::fromString('{"require": {"b/b": "^2.0"}}'), JsonObjectWriter::remove($content, ['require', 'a/a']));
    }

    public function testRemovingAnAbsentMemberLeavesTheContentUntouched(): void
    {
        $content = self::manifest();

        self::assertSame($content, JsonObjectWriter::remove($content, ['require-dev', 'vimeo/psalm']));
    }

    public function testStructuralCharactersInsideValuesDoNotConfuseTheScan(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "description": "a {tricky, \"quoted\"} description: with punctuation",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5"
                    }
                }
                JSON,
        );

        self::assertSame('^2.5', JsonObjectWriter::read($content, ['require-dev', 'phpstan/phpstan']));
    }

    public function testRefusesMalformedJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('is not valid JSON');

        JsonObjectWriter::read('{"name": }', ['name']);
    }

    public function testRefusesARootThatIsNotAnObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('does not hold a JSON object');

        JsonObjectWriter::read('["a"]', ['name']);
    }

    public function testRefusesToWriteBeneathANonObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('"name" does not hold an object');

        JsonObjectWriter::write(self::manifest(), ['name', 'nested'], 'x');
    }

    public function testRefusesADuplicateKey(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "require-dev": {
                        "phpstan/phpstan": "^2.5",
                        "phpstan/phpstan": "^2.6"
                    }
                }
                JSON,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('sets "phpstan/phpstan" more than once');

        JsonObjectWriter::read($content, ['require-dev', 'phpstan/phpstan']);
    }

    public function testEnsureListEntryKeepsAPresentEntryByteIdenticalAcrossSpellings(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "extends": [
                        "local>acme\/renovate-config"
                    ]
                }
                JSON,
        );

        self::assertSame($content, JsonObjectWriter::ensureListEntry($content, ['extends'], 'local>acme/renovate-config'));
    }

    public function testEnsureListEntryAppendsInTheListsOwnLayout(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                  "extends": [
                    "config:recommended"
                  ]
                }
                JSON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON'
                    {
                      "extends": [
                        "config:recommended",
                        "local>acme/renovate-config"
                      ]
                    }
                    JSON,
            ),
            JsonObjectWriter::ensureListEntry($content, ['extends'], 'local>acme/renovate-config'),
        );
    }

    public function testEnsureListEntryAppendsToAOneLineList(): void
    {
        self::assertSame(
            FileContent::fromString('{ "extends": ["a", "b"] }'),
            JsonObjectWriter::ensureListEntry(FileContent::fromString('{ "extends": ["a"] }'), ['extends'], 'b'),
        );
    }

    public function testEnsureListEntryFillsAnEmptyListInline(): void
    {
        self::assertSame(
            FileContent::fromString('{ "extends": ["a"] }'),
            JsonObjectWriter::ensureListEntry(FileContent::fromString('{ "extends": [] }'), ['extends'], 'a'),
        );
    }

    public function testEnsureListEntryCreatesTheMissingMember(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                  "labels": ["dependencies"]
                }
                JSON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON'
                    {
                      "labels": ["dependencies"],
                      "extends": [
                        "local>acme/renovate-config"
                      ]
                    }
                    JSON,
            ),
            JsonObjectWriter::ensureListEntry($content, ['extends'], 'local>acme/renovate-config'),
        );
    }

    public function testEnsureListEntryRefusesANonListMember(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('"extends" does not hold a list');

        JsonObjectWriter::ensureListEntry(FileContent::fromString('{ "extends": true }'), ['extends'], 'a');
    }

    public function testEnsureListEntryReplacesTheFirstSupersededEntryInPlace(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "extends": [
                        "config:recommended",
                        "local>acme/old-config",
                        "local>acme/older-config"
                    ]
                }
                JSON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON'
                    {
                        "extends": [
                            "config:recommended",
                            "local>acme/renovate-config",
                            "local>acme/older-config"
                        ]
                    }
                    JSON,
            ),
            JsonObjectWriter::ensureListEntry($content, ['extends'], 'local>acme/renovate-config', ['local>acme/older-config', 'local>acme/old-config']),
        );
    }

    public function testRemovesListEntriesWithTheirSeparatingCommas(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "extends": [
                        "local>acme/old-config",
                        "config:recommended",
                        "local>acme/strict-config"
                    ],
                    "labels": ["local>acme/old-config", "dependencies", "local>acme/strict-config"]
                }
                JSON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON'
                    {
                        "extends": [
                            "config:recommended"
                        ],
                        "labels": ["dependencies"]
                    }
                    JSON,
            ),
            JsonObjectWriter::removeListEntries(
                JsonObjectWriter::removeListEntries($content, ['extends'], ['local>acme/old-config', 'local>acme/strict-config']),
                ['labels'],
                ['local>acme/old-config', 'local>acme/strict-config'],
            ),
        );
    }

    public function testRemovingTheOnlyListEntryLeavesAnEmptyList(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "extends": [
                        "local>acme/old-config"
                    ]
                }
                JSON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON'
                    {
                        "extends": []
                    }
                    JSON,
            ),
            JsonObjectWriter::removeListEntries($content, ['extends'], ['local>acme/old-config']),
        );
    }

    public function testRemovingAbsentListEntriesLeavesTheContentUntouched(): void
    {
        $content = FileContent::fromString('{ "extends": ["config:recommended"] }');

        self::assertSame($content, JsonObjectWriter::removeListEntries($content, ['extends'], ['local>acme/old-config']));
        self::assertSame($content, JsonObjectWriter::removeListEntries($content, ['labels'], ['local>acme/old-config']));
        self::assertSame($content, JsonObjectWriter::removeListEntries($content, ['extends'], []));
    }

    public function testRemoveListEntriesRefusesANonListMember(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('"extends" does not hold a list');

        JsonObjectWriter::removeListEntries(FileContent::fromString('{ "extends": true }'), ['extends'], ['a']);
    }

    public function testEnsureListEntryIsIdempotent(): void
    {
        $once = JsonObjectWriter::ensureListEntry(FileContent::fromString('{ "extends": ["a"] }'), ['extends'], 'b');

        self::assertSame($once, JsonObjectWriter::ensureListEntry($once, ['extends'], 'b'));
    }

    /** Applying a write twice is the engine's free property test; the writer owes the same guarantee on its own. */
    public function testWritingIsIdempotent(): void
    {
        $once = JsonObjectWriter::write(self::manifest(), ['require-dev', 'vimeo/psalm'], '^6');

        self::assertSame($once, JsonObjectWriter::write($once, ['require-dev', 'vimeo/psalm'], '^6'));
    }

    private static function manifest(): string
    {
        return FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5",
                        "rector/rector": "^2.5"
                    },
                    "config": {
                        "sort-packages": true
                    }
                }
                JSON,
        );
    }
}
