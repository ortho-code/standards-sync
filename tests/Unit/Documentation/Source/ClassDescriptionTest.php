<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Documentation\Source;

use StandardsSync\Documentation\Source\ClassDescription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassDescription::class)]
final class ClassDescriptionTest extends TestCase
{
    public function testAOneLineDocblockBecomesItsSentence(): void
    {
        self::assertSame('One line.', ClassDescription::fromClass(OneLineDocblock::class)?->text());
    }

    public function testLinesJoinIntoParagraphsAndTagLinesDrop(): void
    {
        self::assertSame(
            "First sentence. Second sentence.\n\nNew paragraph.",
            ClassDescription::fromClass(MultiParagraphDocblock::class)?->text(),
        );
    }

    public function testAClassWithoutADocblockHasNoDescription(): void
    {
        self::assertNull(ClassDescription::fromClass(NoDocblock::class));
    }

    public function testADocblockOfOnlyTagsHasNoDescription(): void
    {
        self::assertNull(ClassDescription::fromClass(TagOnlyDocblock::class));
    }
}

/** One line. */
final class OneLineDocblock
{
}

/**
 * First sentence.
 * Second sentence.
 *
 * New paragraph.
 *
 * @internal a tag line the description drops
 */
final class MultiParagraphDocblock
{
}

final class NoDocblock
{
}

/** @internal */
final class TagOnlyDocblock
{
}
