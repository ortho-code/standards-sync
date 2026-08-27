<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Authoring;

use OrthoCode\StandardsSync\Authoring\Package;
use OrthoCode\StandardsSync\Authoring\Standard;
use OrthoCode\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Standard::class)]
final class StandardTest extends TestCase
{
    public function testLocatesItsOwnPackageWhenNoneIsInjected(): void
    {
        // Defined in the engine repo, so self-location finds the root package: references render bare, directly under the project root.
        $standard = new class extends Standard {
            protected function enforce(Package $package): void
            {
                $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('phpstan.neon')));
            }
        };

        self::assertStringContainsString('includes "templates/phpstan.neon"', $standard->rules()[0]->description());
    }
}
