<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Neon;

use OrthoCode\StandardsSync\Formats\Neon\NeonListWriter;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Neon's entry points into the block-list writer; the list edits themselves are the block-list writer's to test. */
#[CoversClass(NeonListWriter::class)]
final class NeonListWriterTest extends TestCase
{
    public function testCreatesTheSectionIndentedWithATab(): void
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

    public function testReadsTheSectionsEntries(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/phpstan.neon
                	- phpstan-baseline.neon
                NEON,
        );

        self::assertSame(['vendor/acme/standards/phpstan.neon', 'phpstan-baseline.neon'], NeonListWriter::readList($content, 'includes'));
    }

    public function testRemovesEntries(): void
    {
        $content = FileContent::fromString(
            <<<'NEON'
                includes:
                	- vendor/acme/standards/strict.neon
                	- phpstan-baseline.neon
                NEON,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- phpstan-baseline.neon
                    NEON,
            ),
            NeonListWriter::removeEntries($content, 'includes', ['vendor/acme/standards/strict.neon']),
        );
    }
}
