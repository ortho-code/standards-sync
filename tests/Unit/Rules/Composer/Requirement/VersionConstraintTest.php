<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Rules\Composer\Requirement;

use StandardsSync\Rules\Composer\Requirement\VersionConstraint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VersionConstraint::class)]
final class VersionConstraintTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function meetingConstraints(): iterable
    {
        yield 'the minimum itself' => ['^2.5'];
        yield 'a narrower caret' => ['^2.5.7'];
        yield 'a tilde range' => ['~2.5'];
        yield 'an exact version' => ['2.5.7'];
        yield 'a stability flag' => ['^2.5@dev'];
        yield 'an explicit range' => ['>=2.5 <3.0'];
        yield 'a wildcard' => ['2.5.*'];
        yield 'a newer major' => ['^3.0'];
        yield 'an unbounded lower bound' => ['>=2.5'];
        yield 'a branch alias of a newer major' => ['3.x-dev'];
        yield 'the minimum beside a newer major' => ['^2.5 || ^3.0'];
    }

    #[DataProvider('meetingConstraints')]
    public function testAConstraintThatCannotResolveBelowTheMinimumMeetsIt(string $constraint): void
    {
        self::assertTrue(VersionConstraint::fromString($constraint)->meets(VersionConstraint::fromString('^2.5')));
    }

    /** @return iterable<string, array{string}> */
    public static function failingConstraints(): iterable
    {
        yield 'an older major' => ['^2.0'];
        yield 'anything at all' => ['*'];
        yield 'a branch' => ['dev-main'];
        yield 'a feature branch' => ['dev-feature/x'];
        yield 'a branch beside a meeting range' => ['^2.5 || dev-main'];
        yield 'a pair whose other alternative reaches below' => ['^1.0 || ^3.0'];
    }

    #[DataProvider('failingConstraints')]
    public function testAConstraintReachingBelowTheMinimumDoesNotMeetIt(string $constraint): void
    {
        self::assertFalse(VersionConstraint::fromString($constraint)->meets(VersionConstraint::fromString('^2.5')));
    }

    public function testTheLowestVersionOfARangeIsItsStart(): void
    {
        self::assertSame('2.5.0.0-dev', VersionConstraint::fromString('^2.5')->lowestVersion());
    }

    /** Alternatives are ordered by the parser, not by how they were written, so the lowest is the lowest whatever the spelling. */
    public function testTheLowestVersionIgnoresTheWrittenOrderOfAlternatives(): void
    {
        self::assertSame('1.0.0.0-dev', VersionConstraint::fromString('^3.0 || ^1.0')->lowestVersion());
    }

    public function testAConstraintNamingOnlyABranchHasNoLowestVersion(): void
    {
        self::assertNull(VersionConstraint::fromString('dev-main')->lowestVersion());
    }

    /** @return iterable<string, array{string, string}> */
    public static function raisedConstraints(): iterable
    {
        yield 'a newer major survives the raise' => ['^1.0 || ^3.0', '^2.5 || ^3.0'];
        yield 'an explicit range below the minimum is replaced' => ['>=1.0 <2.0 || ^3.0', '^2.5 || ^3.0'];
        yield 'alternatives all below the minimum collapse into one' => ['^1.0 || ^2.0', '^2.5'];
        yield 'a single low constraint becomes the minimum' => ['^1.0', '^2.5'];
        yield 'an unbounded constraint becomes the minimum' => ['*', '^2.5'];
        yield 'a branch becomes the minimum' => ['dev-main', '^2.5'];
        yield 'the single-pipe spelling is understood' => ['^1.0|^3.0', '^2.5 || ^3.0'];
        yield 'the raise lands where the failing alternative was written' => ['^3.0 || ^1.0', '^3.0 || ^2.5'];
        yield 'a branch beside a meeting range becomes the minimum' => ['^3.0 || dev-main', '^3.0 || ^2.5'];
        yield 'a branch whose raise duplicates a kept alternative disappears' => ['^2.5 || dev-main', '^2.5'];
        yield 'a raise duplicating a kept alternative collapses into it' => ['^2.5 || ^1.0', '^2.5'];
        yield 'three alternatives keep the two that meet the minimum' => ['^1.0 || ^2.5 || ^4.0', '^2.5 || ^4.0'];
        yield 'padding around the separator does not survive a raise' => ['^1.0   ||   ^3.0', '^2.5 || ^3.0'];
        yield 'a meeting constraint is returned unchanged' => ['^2.5.7', '^2.5.7'];
        yield 'a meeting pair is returned unchanged' => ['^2.5 || ^3.0', '^2.5 || ^3.0'];
        yield 'a meeting pair keeps its own separator spelling' => ['^2.5|^3.0', '^2.5|^3.0'];
        yield 'a meeting explicit range keeps its own spelling' => ['>=2.5 <4.0', '>=2.5 <4.0'];
    }

    #[DataProvider('raisedConstraints')]
    public function testRaisingReplacesOnlyTheAlternativesBelowTheMinimum(string $constraint, string $expected): void
    {
        self::assertSame($expected, VersionConstraint::fromString($constraint)->raisedTo(VersionConstraint::fromString('^2.5'))->value());
    }

    public function testRaisingIsIdempotent(): void
    {
        $minimum = VersionConstraint::fromString('^2.5');
        $once = VersionConstraint::fromString('^1.0 || ^3.0')->raisedTo($minimum);

        self::assertSame($once->value(), $once->raisedTo($minimum)->value());
    }

    /** @return iterable<string, array{string}> */
    public static function unparseableConstraints(): iterable
    {
        yield 'empty' => [''];
        yield 'not a version at all' => ['abc'];
    }

    #[DataProvider('unparseableConstraints')]
    public function testRefusesAConstraintComposerCannotParse(string $constraint): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a version constraint composer can parse');

        VersionConstraint::fromString($constraint);
    }

    public function testRefusesToMeasureAgainstAMinimumThatStatesNoVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('states no lowest version');

        VersionConstraint::fromString('^2.5')->meets(VersionConstraint::fromString('dev-main'));
    }
}
