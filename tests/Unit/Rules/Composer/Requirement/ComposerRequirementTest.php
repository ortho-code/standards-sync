<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Composer\Requirement;

use OrthoCode\StandardsSync\Rules\Composer\Requirement\ComposerRequirement;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\RequirementType;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\VersionConstraint;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ComposerRequirement::class)]
final class ComposerRequirementTest extends TestCase
{
    /** A declared branch states no minimum and branches have no ordering, so the requirement pins rather than floors. */
    public function testTheDescriptionOfABranchConstraintSaysItIsPinned(): void
    {
        self::assertSame(
            'Requires acme/security-advisories in the composer manifest (require-dev), pinned to "dev-latest".',
            self::pinnedRule()->description(),
        );
    }

    public function testLeavesThePinnedBranchByteIdentical(): void
    {
        $manifest = self::manifest('{"require-dev": {"acme/security-advisories": "dev-latest"}}');

        self::assertSame($manifest, self::pinnedRule()->apply($manifest));
    }

    public function testExplainsWhyAnotherBranchIsRewrittenToThePinnedOne(): void
    {
        $explanation = self::pinnedRule()->explain(self::manifest('{"require-dev": {"acme/security-advisories": "dev-master"}}'));

        self::assertSame('The required "dev-master" is not the pinned "dev-latest", and branches name no versions to compare, so the pinned one is written.', $explanation);
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

    private static function pinnedRule(): ComposerRequirement
    {
        return new ComposerRequirement(package: 'acme/security-advisories', constraint: VersionConstraint::fromString('dev-latest'));
    }

    private static function manifest(string $json): string
    {
        return FileContent::fromString($json);
    }
}
