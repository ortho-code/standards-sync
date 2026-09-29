<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\General\ListContribution;

use OrthoCode\StandardsSync\Rules\General\ListContribution\DeclaredEntries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclaredEntries::class)]
final class DeclaredEntriesTest extends TestCase
{
    public function testAStringIsItsOwnKey(): void
    {
        $entries = DeclaredEntries::fromString('a.neon');

        self::assertSame(['a.neon'], $entries->entries());
        self::assertSame(['a.neon'], $entries->keys());
        self::assertSame([], $entries->retired());
    }

    public function testMergingAppendsInDeclarationOrderAndKeepsEachKeysFirstDeclaration(): void
    {
        $key = static fn(array $entry): string => $entry[0];
        $merged = DeclaredEntries::fromEntry(['a', 'first'], $key)
            ->withMerged(DeclaredEntries::fromEntry(['b', 'only'], $key))
            ->withMerged(DeclaredEntries::fromEntry(['a', 'second'], $key));

        self::assertSame([['a', 'first'], ['b', 'only']], $merged->entries());
        self::assertSame(['a', 'b'], $merged->keys());
    }

    public function testRetiredKeysAreCarriedAlongsideTheDeclaredEntries(): void
    {
        $entries = DeclaredEntries::fromString('a.neon')->withRetired(['old.neon', 'strict.neon']);

        self::assertSame(['a.neon'], $entries->keys());
        self::assertSame(['old.neon', 'strict.neon'], $entries->retired());
    }

    public function testTellsWhichDeclaredEntriesAListLacksAndWhichRetiredKeysItStillHolds(): void
    {
        $entries = DeclaredEntries::fromString('a.neon')
            ->withMerged(DeclaredEntries::fromString('b.neon'))
            ->withRetired(['old.neon', 'strict.neon']);
        $present = ['own.neon', 'b.neon', 'strict.neon'];

        self::assertSame(['a.neon'], $entries->missingFrom($present));
        self::assertSame(['strict.neon'], $entries->retractedFrom($present));
    }

    public function testMissingEntriesAreReturnedAsDeclaredNotAsKeys(): void
    {
        $entries = DeclaredEntries::fromEntry(['a', 'rich'], static fn(array $entry): string => $entry[0]);

        self::assertSame([['a', 'rich']], $entries->missingFrom([]));
        self::assertSame([], $entries->missingFrom(['a']));
    }
}
