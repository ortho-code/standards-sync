<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\GitHub\Workflow;

use OrthoCode\StandardsSync\Rules\GitHub\Workflow\ActionReference;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\RunnerLabel;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\SpelledVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** How action references and runner labels compare against a declared minimum. */
#[CoversClass(SpelledVersion::class)]
#[CoversClass(ActionReference::class)]
#[CoversClass(RunnerLabel::class)]
final class VersionComparisonTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function versions(): iterable
    {
        yield 'a higher major' => ['v8', 'v7', true];
        yield 'the same major tag' => ['v7', 'v7', true];
        yield 'a moving major tag against an exact minimum' => ['v7', 'v7.2.0', true];
        yield 'an exact version against a moving major tag' => ['v7.0.1', 'v7', true];
        yield 'a lower minor' => ['v7.1', 'v7.2.0', false];
        yield 'a lower major' => ['v6', 'v7', false];
        yield 'without the v' => ['2.36.0', 'v2', true];
        yield 'a runner version' => ['28.04', '26.04', true];
    }

    #[DataProvider('versions')]
    public function testComparesOnTheComponentsBothSidesSpell(string $version, string $minimum, bool $atLeast): void
    {
        $spelled = SpelledVersion::fromString($version);
        $floor = SpelledVersion::fromString($minimum);

        self::assertNotNull($spelled);
        self::assertNotNull($floor);
        self::assertSame($atLeast, $spelled->isAtLeast($floor));
    }

    public function testSpellsNoVersionForABranchOrAHash(): void
    {
        self::assertNull(SpelledVersion::fromString('main'));
        self::assertNull(SpelledVersion::fromString('0123456789abcdef0123456789abcdef01234567'));
        self::assertNull(SpelledVersion::fromString('latest'));
    }

    /** @return iterable<string, array{string, ?string, string, ?bool}> */
    public static function references(): iterable
    {
        yield 'a newer tag' => ['actions/checkout@v8', null, 'actions/checkout@v7', true];
        yield 'an older tag' => ['actions/checkout@v6', null, 'actions/checkout@v7', false];
        yield 'another action' => ['acme/checkout@v9', null, 'actions/checkout@v7', false];
        yield 'another path in the same repository' => ['acme/aws/ec2@v2', null, 'acme/aws@v2', false];
        yield 'a branch' => ['actions/checkout@main', null, 'actions/checkout@v7', null];
        yield 'a hash without a comment' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567', null, 'actions/checkout@v7', null];
        yield 'a hash pinned at the minimum' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567', ' v7.0.1', 'actions/checkout@v7', true];
        yield 'a hash pinned below it' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567', ' v6.0.0', 'actions/checkout@v7', false];
        yield 'a hash pinned in the pin spelling' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567', ' pin @v7.0.1', 'actions/checkout@v7', true];
        yield 'a hash pinned in the tag spelling' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567', ' tag=v7.0.1', 'actions/checkout@v7', true];
        yield 'a hash with a comment naming no version' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567', ' the pinned one', 'actions/checkout@v7', null];
        yield 'a local action against itself' => ['./.github/actions/setup', null, './.github/actions/setup', null];
        yield 'a docker image against another' => ['docker://alpine:3.20', null, 'docker://alpine:3.19', false];
    }

    #[DataProvider('references')]
    public function testHoldsAReferenceToTheDeclaredActionAtItsVersionOrLater(string $uses, ?string $comment, string $minimum, ?bool $atLeast): void
    {
        self::assertSame($atLeast, ActionReference::fromUses($uses, $comment)->isAtLeast(ActionReference::fromUses($minimum, null)));
    }

    public function testReadsAPinsVersionFromItsCommentOnly(): void
    {
        self::assertTrue(ActionReference::fromUses('actions/checkout@0123456789abcdef0123456789abcdef01234567', ' v7.0.1')->namesVersionInComment());
        self::assertFalse(ActionReference::fromUses('actions/checkout@v7', ' v7.0.1')->namesVersionInComment());
    }

    /** @return iterable<string, array{string, string, ?bool}> */
    public static function runners(): iterable
    {
        yield 'a newer image' => ['ubuntu-28.04', 'ubuntu-26.04', true];
        yield 'an older image' => ['ubuntu-24.04', 'ubuntu-26.04', false];
        yield 'another architecture' => ['ubuntu-26.04-arm', 'ubuntu-26.04', false];
        yield 'the same architecture' => ['ubuntu-28.04-arm', 'ubuntu-26.04-arm', true];
        yield 'another system' => ['windows-2025', 'ubuntu-26.04', false];
        yield 'a moving label' => ['ubuntu-latest', 'ubuntu-26.04', null];
        yield 'an unversioned label' => ['ubuntu-slim', 'ubuntu-26.04', null];
    }

    #[DataProvider('runners')]
    public function testHoldsARunnerOfTheDeclaredKindAtItsVersionOrLater(string $label, string $minimum, ?bool $atLeast): void
    {
        self::assertSame($atLeast, RunnerLabel::fromString($label)->isAtLeast(RunnerLabel::fromString($minimum)));
    }
}
