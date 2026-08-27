<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Composer\Requirement;

use OrthoCode\StandardsSync\Rules\Composer\Requirement\ComposerRequirement;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\RequirementType;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\VersionConstraint;
use OrthoCode\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ComposerRequirement::class)]
final class ComposerRequirementTest extends TestCase
{
    /** An org declaring a branch as its standard states no minimum, so it is refused where it is written rather than at sync time. */
    public function testRefusesAConstraintThatStatesNoMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('names a branch, so it states no minimum version to enforce');

        new ComposerRequirement(package: 'phpstan/phpstan', constraint: VersionConstraint::fromString('dev-main'));
    }

    public function testAbstainsWithoutAManifest(): void
    {
        self::assertNull(self::rule()->apply(null));
    }

    public function testTheDescriptionNamesThePackageTheSectionAndTheMinimum(): void
    {
        self::assertSame('Requires phpstan/phpstan in the composer manifest (require-dev), no lower than "^2.5".', self::rule()->description());
    }

    public function testExplainsAnAbsentRequirementWithTheLockFollowUp(): void
    {
        $explanation = self::rule()->explain(self::manifest('{}'));

        self::assertSame('phpstan/phpstan is not required; it is added to require-dev. Run "composer update phpstan/phpstan" afterwards, or composer install will refuse the stale lock.', $explanation);
    }

    public function testExplainsAConstraintReachingBelowTheMinimum(): void
    {
        $explanation = self::rule()->explain(self::manifest('{"require-dev": {"phpstan/phpstan": "^2.0"}}'));

        self::assertSame('The required "^2.0" reaches below the "^2.5" minimum.', $explanation);
    }

    /** The case that overwrites a deliberate choice, so the diff has to carry its own justification. */
    public function testExplainsWhyABranchConstraintIsRewritten(): void
    {
        $explanation = self::rule()->explain(self::manifest('{"require-dev": {"phpstan/phpstan": "dev-main"}}'));

        self::assertSame('The required "dev-main" names a branch, which can resolve to any version and so meets no minimum; it is raised to "^2.5".', $explanation);
    }

    /** The duplicate drifts because one entry is dropped, so the explanation must say that rather than blaming the surviving constraint. */
    public function testExplainsADuplicateAcrossBothSections(): void
    {
        $explanation = self::rule()->explain(self::manifest('{"require": {"phpstan/phpstan": "^2.5"}, "require-dev": {"phpstan/phpstan": "^2.0"}}'));

        self::assertSame('phpstan/phpstan is required in both require and require-dev, where both constraints apply at once; the require-dev entry is dropped.', $explanation);
    }

    public function testRefusesAConstraintTheManifestWritesThatComposerCannotParse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires phpstan/phpstan at "not a constraint" in require-dev, which is not a version constraint composer can parse');

        self::rule()->apply(self::manifest('{"require-dev": {"phpstan/phpstan": "not a constraint"}}'));
    }

    public function testExplainsAMoveOutOfTheWrongSection(): void
    {
        $rule = new ComposerRequirement(
            package: 'phpstan/phpstan',
            constraint: VersionConstraint::fromString('^2.5'),
            type: RequirementType::Runtime,
        );

        $explanation = $rule->explain(self::manifest('{"require-dev": {"phpstan/phpstan": "^2.5"}}'));

        self::assertSame('phpstan/phpstan is required in require-dev, so it is not installed in production; it moves to require.', $explanation);
    }

    private static function rule(): ComposerRequirement
    {
        return new ComposerRequirement(package: 'phpstan/phpstan', constraint: VersionConstraint::fromString('^2.5'));
    }

    private static function manifest(string $json): string
    {
        return FileContent::fromString($json);
    }
}
