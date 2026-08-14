<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Formats\Json5;

use AlleKnalle\StandardsSync\Formats\Json5\Json5ListWriter;
use AlleKnalle\StandardsSync\Testing\FileContent;
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
                    JSON5
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
                    JSON5
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
                JSON5
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
                    JSON5
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
                JSON5
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
                    JSON5
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
                JSON5
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
                    JSON5
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testAppendsToAOneLineList(): void
    {
        self::assertSame(
            "{ \"extends\": [\"config:recommended\", \"local>acme/renovate-config\"] }\n",
            Json5ListWriter::ensureEntry("{ \"extends\": [\"config:recommended\"] }\n", 'extends', self::ENTRY),
        );
    }

    public function testFillsAnEmptyListInline(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  extends: [],
                }
                JSON5
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: ["local>acme/renovate-config"],
                    }
                    JSON5
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
                JSON5
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
                    JSON5
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
                JSON5
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
                JSON5
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'local>acme/renovate-config', // org standard
                      ],
                    }
                    JSON5
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
                JSON5
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'local>acme/renovate-config', // org standard
                      ],
                    }
                    JSON5
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
                JSON5
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
                JSON5
        );

        self::assertSame($content, Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY));
    }

    public function testSkipsTheCommentWhereTheEntryDoesNotEndItsLine(): void
    {
        $content = "{ extends: ['local>acme/renovate-config', 'config:recommended'] }\n";

        self::assertSame($content, Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY, 'org standard'));
    }

    public function testCreatesTheKeyInAnObjectWithoutIt(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  labels: ['dependencies'],
                }
                JSON5
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
                    JSON5
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testAppendsTheKeyToAOneLineObject(): void
    {
        self::assertSame(
            "{ labels: [\"x\"], extends: [\"local>acme/renovate-config\"] }\n",
            Json5ListWriter::ensureEntry("{ labels: [\"x\"] }\n", 'extends', self::ENTRY),
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
                    JSON5
            ),
            Json5ListWriter::ensureEntry("{}\n", 'extends', self::ENTRY),
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
                JSON5
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      extends: [
                        'config:recommended', 'local>acme/renovate-config' ]
                    }
                    JSON5
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
                JSON5
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
                    JSON5
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
                JSON5
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      labels: ['x'], extends: ["local>acme/renovate-config"] }
                    JSON5
            ),
            Json5ListWriter::ensureEntry($content, 'extends', self::ENTRY),
        );
    }

    public function testRefusesContentThatIsNotAnObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not hold a JSON5 object');

        Json5ListWriter::ensureEntry("[]\n", 'extends', self::ENTRY);
    }

    public function testRefusesADuplicateKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sets "extends" more than once');

        Json5ListWriter::ensureEntry("{ extends: [], \"extends\": [] }\n", 'extends', self::ENTRY);
    }

    public function testRefusesAKeyThatHoldsNoList(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"extends" does not hold a list');

        Json5ListWriter::ensureEntry("{ extends: true }\n", 'extends', self::ENTRY);
    }

    public function testRefusesAStringThatNeverCloses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A JSON5 string never closes');

        Json5ListWriter::ensureEntry("{ extends: ['broken] }\n", 'extends', self::ENTRY);
    }

    public function testRefusesACommentThatNeverCloses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A JSON5 comment never closes');

        Json5ListWriter::ensureEntry("{ /* broken\n  extends: [] }\n", 'extends', self::ENTRY);
    }

    /** Applying a write twice is the engine's free property test; the writer owes the same guarantee on its own. */
    public function testEnsuringIsIdempotent(): void
    {
        $once = Json5ListWriter::ensureEntry("{\n  extends: [\n    'config:recommended',\n  ],\n}\n", 'extends', self::ENTRY, 'org standard');

        self::assertSame($once, Json5ListWriter::ensureEntry($once, 'extends', self::ENTRY, 'org standard'));
    }
}
