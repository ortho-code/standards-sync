<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Json5;

use OrthoCode\StandardsSync\Formats\Json5\Json5ListWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Json5ListWriter::class)]
final class Json5ListWriterTest extends TestCase
{
    private const string ENTRY = 'local>acme/renovate-config';

    public function testCreatesTheDocumentFromEmptyContent(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        "extends": [
                            "local>acme/renovate-config"
                        ]
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry('', 'extends', self::ENTRY),
        );
    }

    public function testCreatesTheDocumentWithTheEnforcedComment(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        "extends": [
                            "local>acme/renovate-config" // org standard
                        ]
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry('', 'extends', self::ENTRY, 'org standard'),
        );
    }

    public function testAppendsCopyingQuoteAndTrailingCommaStyle(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  // keep majors quiet
                  extends: [
                    'config:recommended',
                  ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      // keep majors quiet
                      extends: [
                        'config:recommended',
                        'local>acme/renovate-config',
                      ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testAppendsWithoutATrailingCommaWhereTheListHasNone(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  "extends": [
                    "config:recommended"
                  ]
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      "extends": [
                        "config:recommended",
                        "local>acme/renovate-config"
                      ]
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testAppendsAfterAnEntryCarryingItsOwnComment(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'config:recommended' // keep
                  ]
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'config:recommended', // keep
                        'local>acme/renovate-config'
                      ]
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testAppendsToAOneLineList(): void
    {
        self::assertSame(
            FileContent::fromString('{ "extends": ["config:recommended", "local>acme/renovate-config"] }'),
            Json5ListWriter::ensureEntry(FileContent::fromString('{ "extends": ["config:recommended"] }'), 'extends', self::ENTRY),
        );
    }

    public function testFillsAnEmptyListInline(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: ["local>acme/renovate-config"],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testInsertsWithTheCommentOnTheNewLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'config:recommended',
                  ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'config:recommended',
                        'local>acme/renovate-config', // org standard
                      ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard'),
        );
    }

    public function testKeepsAPresentEntryByteIdenticalAcrossQuoteSpellings(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'local>acme/renovate-config',
                  ],
                }
                JSON5,
        );

        self::assertSame($content, Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY));
    }

    public function testAddsTheEnforcedCommentToACompliantEntry(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'local>acme/renovate-config',
                  ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'local>acme/renovate-config', // org standard
                      ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard'),
        );
    }

    public function testRewritesADeviatingCommentOnTheEntryLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'local>acme/renovate-config', // my own note
                  ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'local>acme/renovate-config', // org standard
                      ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard'),
        );
    }

    public function testKeepsTheCanonicalCommentPut(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'local>acme/renovate-config', // org standard
                  ],
                }
                JSON5,
        );

        self::assertSame($content, Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard'));
    }

    public function testPreservesAConsumerCommentWhenNoCommentIsConfigured(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'local>acme/renovate-config', // my own note
                  ],
                }
                JSON5,
        );

        self::assertSame($content, Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY));
    }

    public function testSkipsTheCommentWhereTheEntryDoesNotEndItsLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                { extends: ['local>acme/renovate-config', 'config:recommended'] }
                JSON5,
        );

        self::assertSame($content, Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard'));
    }

    public function testCreatesTheKeyInAnObjectWithoutIt(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  labels: ['dependencies'],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      labels: ['dependencies'],
                      extends: [
                        "local>acme/renovate-config"
                      ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testAppendsTheKeyToAOneLineObject(): void
    {
        self::assertSame(
            FileContent::fromString('{ labels: ["x"], extends: ["local>acme/renovate-config"] }'),
            Json5ListWriter::ensureEntry(FileContent::fromString('{ labels: ["x"] }'), 'extends', self::ENTRY),
        );
    }

    public function testFillsAnEmptyObject(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        "extends": [
                            "local>acme/renovate-config"
                        ]
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry(FileContent::fromString('{}'), 'extends', self::ENTRY),
        );
    }

    public function testAppendsInlineWhenTheClosingBracketSharesTheLastEntryLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'config:recommended' ]
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'config:recommended', 'local>acme/renovate-config' ]
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testKeepsALeadingCommaStyleListValid(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'config:recommended'
                    ,
                  ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'config:recommended',
                        'local>acme/renovate-config'
                        ,
                      ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testAppendsTheKeyInlineWhenTheClosingBraceSharesTheLastMemberLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  labels: ['x'] }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      labels: ['x'], extends: ["local>acme/renovate-config"] }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testRefusesContentThatIsNotAnObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not hold a JSON5 object');

        Json5ListWriter::ensureEntry(FileContent::fromString('[]'), 'extends', self::ENTRY);
    }

    public function testRefusesADuplicateKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sets "extends" more than once');

        Json5ListWriter::ensureEntry(FileContent::fromString('{ extends: [], "extends": [] }'), 'extends', self::ENTRY);
    }

    public function testRefusesAKeyThatHoldsNoList(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"extends" does not hold a list');

        Json5ListWriter::ensureEntry(FileContent::fromString('{ extends: true }'), 'extends', self::ENTRY);
    }

    public function testRefusesAStringThatNeverCloses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A JSON5 string never closes');

        Json5ListWriter::ensureEntry(
            FileContent::fromString(
                <<<'JSON5'
                    { extends: ['broken] }
                    JSON5,
            ),
            'extends',
            self::ENTRY,
        );
    }

    public function testRefusesACommentThatNeverCloses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A JSON5 comment never closes');

        Json5ListWriter::ensureEntry(
            FileContent::fromString(
                <<<'JSON5'
                    { /* broken
                      extends: [] }
                    JSON5,
            ),
            'extends',
            self::ENTRY,
        );
    }

    /** Applying a write twice is the engine's free property test; the writer owes the same guarantee on its own. */
    public function testASupersededEntryIsReplacedInPlaceKeepingItsQuotesAndComment(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                    extends: [
                        'config:recommended',
                        'local>acme/old-config', // ours
                    ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        extends: [
                            'config:recommended',
                            'local>acme/renovate-config', // ours
                        ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, replacing: ['local>acme/old-config']),
        );
    }

    public function testAReplacedEntryCarriesTheEnforcedComment(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                    extends: [
                        'local>acme/old-config', // ours
                        'config:recommended',
                    ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        extends: [
                            'local>acme/renovate-config', // org standard
                            'config:recommended',
                        ],
                    }
                    JSON5,
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard', ['local>acme/old-config']),
        );
    }

    public function testRemovesAnEntryWithItsWholeLine(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                    extends: [
                        'local>acme/old-config', // org standard
                        'config:recommended',
                        'local>acme/strict-config'
                    ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        extends: [
                            'config:recommended',
                        ],
                    }
                    JSON5,
            ),
            Json5ListWriter::removeEntries($content, 'extends', ['local>acme/old-config', 'local>acme/strict-config']),
        );
    }

    public function testRemovingFromALeadingCommaListKeepsItValid(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                    extends: [
                        'local>acme/old-config'
                      , 'config:recommended'
                      , 'local>acme/strict-config'
                    ],
                }
                JSON5,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        extends: [
                            'config:recommended'
                        ],
                    }
                    JSON5,
            ),
            Json5ListWriter::removeEntries($content, 'extends', ['local>acme/old-config', 'local>acme/strict-config']),
        );
    }

    public function testRemovesFromAOneLineList(): void
    {
        self::assertSame(
            FileContent::fromString('{ extends: ["config:recommended"] }'),
            Json5ListWriter::removeEntries(FileContent::fromString('{ extends: ["local>acme/old-config", "config:recommended", "local>acme/strict-config"] }'), 'extends', ['local>acme/old-config', 'local>acme/strict-config']),
        );
    }

    public function testRemovingAbsentEntriesLeavesTheContentUntouched(): void
    {
        $content = FileContent::fromString('{ extends: ["config:recommended"] }');

        self::assertSame($content, Json5ListWriter::removeEntries($content, 'extends', ['local>acme/old-config']));
        self::assertSame($content, Json5ListWriter::removeEntries($content, 'labels', ['local>acme/old-config']));
        self::assertSame($content, Json5ListWriter::removeEntries($content, 'extends', []));
        self::assertSame('', Json5ListWriter::removeEntries('', 'extends', ['local>acme/old-config']));
    }

    public function testReadsTheStringEntriesUnquoted(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                    extends: [
                        'local>acme/renovate-config', // org standard
                        "config:recommended",
                        42,
                    ],
                }
                JSON5,
        );

        self::assertSame([self::ENTRY, 'config:recommended'], Json5ListWriter::readList($content, 'extends'));
        self::assertNull(Json5ListWriter::readList($content, 'labels'));
        self::assertNull(Json5ListWriter::readList('', 'extends'));
    }

    public function testEnsuringIsIdempotent(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [
                    'config:recommended',
                  ],
                }
                JSON5,
        );
        $once = Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard');

        self::assertSame($once, Json5ListWriter::ensureEntry($once, 'extends', self::ENTRY, 'org standard'));
    }
}
