<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Composer\Requirement;

use OrthoCode\StandardsSync\Rules\Composer\Requirement\RequirementType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequirementType::class)]
final class RequirementTypeTest extends TestCase
{
    public function testTheCasesAreComposersOwnSectionNames(): void
    {
        self::assertSame('require', RequirementType::Runtime->value);
        self::assertSame('require-dev', RequirementType::Development->value);
    }

    public function testEachNeedIsMetWhereItIsDeclared(): void
    {
        self::assertTrue(RequirementType::Runtime->isMetBy(RequirementType::Runtime));
        self::assertTrue(RequirementType::Development->isMetBy(RequirementType::Development));
    }

    public function testARuntimeRequirementCoversADevelopmentOne(): void
    {
        self::assertTrue(RequirementType::Development->isMetBy(RequirementType::Runtime));
    }

    /** The asymmetry the family turns on: a package required only for development is missing when the project runs. */
    public function testADevelopmentRequirementDoesNotCoverARuntimeOne(): void
    {
        self::assertFalse(RequirementType::Runtime->isMetBy(RequirementType::Development));
    }
}
