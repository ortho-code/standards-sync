<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Formats\Json;

use AlleKnalle\StandardsSync\Formats\Json\JsonObjectWriter;
use AlleKnalle\StandardsSync\Testing\FileContent;
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
                JSON
        );

        self::assertSame('^2.5', JsonObjectWriter::read($content, ['require-dev', 'phpstan/phpstan']));
    }

    public function testRefusesToReadANonStringAsAString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"config.sort-packages" does not hold a string');

        JsonObjectWriter::read(self::manifest(), ['config', 'sort-packages']);
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
                JSON
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
                JSON
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
                JSON
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5"
                    }
                }
                JSON
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
                JSON
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "name": "acme/project",
                    "require-dev": {
                        "phpstan/phpstan": "^2.5"
                    }
                }
                JSON
        );

        self::assertSame($expected, JsonObjectWriter::write($content, ['require-dev', 'phpstan/phpstan'], '^2.5'));
    }

    /** @return iterable<string, array{string}> */
    public static function indentations(): iterable
    {
        yield 'two spaces' => ['  '];
        yield 'four spaces' => ['    '];
        yield 'tabs' => ["\t"];
    }

    #[DataProvider('indentations')]
    public function testInsertionCopiesTheFilesOwnIndentation(string $indent): void
    {
        $content = FileContent::fromString(
            '{' . "\n" . $indent . '"name": "acme/project"' . "\n" . '}'
        );
        $expected = FileContent::fromString(
            '{' . "\n" . $indent . '"name": "acme/project",' . "\n"
            . $indent . '"require-dev": {' . "\n"
            . $indent . $indent . '"phpstan/phpstan": "^2.5"' . "\n"
            . $indent . '}' . "\n" . '}'
        );

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
                JSON
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "config": {"sort-packages": true, "vendor-dir": "vendor"}
                }
                JSON
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
                JSON
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {"test": "phpunit", "check": ["one", "two"]}
                }
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
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
                JSON
        );
        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "require-dev": {}
                }
                JSON
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
                JSON
        );

        self::assertSame('^2.5', JsonObjectWriter::read($content, ['require-dev', 'phpstan/phpstan']));
    }

    public function testRefusesMalformedJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not valid JSON');

        JsonObjectWriter::read('{"name": }', ['name']);
    }

    public function testRefusesARootThatIsNotAnObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not hold a JSON object');

        JsonObjectWriter::read('["a"]', ['name']);
    }

    public function testRefusesToWriteBeneathANonObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"name" does not hold an object');

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
                JSON
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sets "phpstan/phpstan" more than once');

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
                JSON
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
                JSON
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
                    JSON
            ),
            JsonObjectWriter::ensureListEntry($content, ['extends'], 'local>acme/renovate-config'),
        );
    }

    public function testEnsureListEntryAppendsToAOneLineList(): void
    {
        self::assertSame(
            "{ \"extends\": [\"a\", \"b\"] }\n",
            JsonObjectWriter::ensureListEntry("{ \"extends\": [\"a\"] }\n", ['extends'], 'b'),
        );
    }

    public function testEnsureListEntryFillsAnEmptyListInline(): void
    {
        self::assertSame(
            "{ \"extends\": [\"a\"] }\n",
            JsonObjectWriter::ensureListEntry("{ \"extends\": [] }\n", ['extends'], 'a'),
        );
    }

    public function testEnsureListEntryCreatesTheMissingMember(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                  "labels": ["dependencies"]
                }
                JSON
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
                    JSON
            ),
            JsonObjectWriter::ensureListEntry($content, ['extends'], 'local>acme/renovate-config'),
        );
    }

    public function testEnsureListEntryRefusesANonListMember(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"extends" does not hold a list');

        JsonObjectWriter::ensureListEntry("{ \"extends\": true }\n", ['extends'], 'a');
    }

    public function testEnsureListEntryIsIdempotent(): void
    {
        $once = JsonObjectWriter::ensureListEntry("{ \"extends\": [\"a\"] }\n", ['extends'], 'b');

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
                JSON
        );
    }
}
