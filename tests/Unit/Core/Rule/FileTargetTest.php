<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Core\Rule;

use StandardsSync\Core\Filesystem\Path;
use StandardsSync\Core\Rule\FileTarget;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileTarget::class)]
final class FileTargetTest extends TestCase
{
    public function testFromStringIsTheSingleCandidateCase(): void
    {
        $candidates = FileTarget::fromString('phpstan.neon')->candidates();

        self::assertCount(1, $candidates);
        self::assertSame('phpstan.neon', $candidates[0]->value());
    }

    public function testKeepsCandidatesInPrecedenceOrder(): void
    {
        $candidates = FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon', 'phpstan.neon.dist')->candidates();

        self::assertSame(
            ['phpstan.neon', 'phpstan.dist.neon', 'phpstan.neon.dist'],
            array_map(static fn (Path $path): string => $path->value(), $candidates),
        );
    }

    public function testRejectsAnAbsoluteCandidate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FileTarget::fromStrings('phpstan.neon', '/etc/phpstan.neon');
    }

    public function testNeedsAtLeastOneCandidate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FileTarget::fromStrings();
    }

    public function testToStringJoinsTheCandidatesInPrecedenceOrder(): void
    {
        self::assertSame(
            'phpstan.neon | phpstan.dist.neon',
            FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon')->toString(),
        );
    }
}
