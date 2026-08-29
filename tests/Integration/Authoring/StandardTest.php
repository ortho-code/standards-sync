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

    public function testAnIncludedTierSharesTheHandedDownPackage(): void
    {
        // Two tiers of one package, the shape an org uses to ship a library and an application standard on one release line.
        $standard = new class (new Package('/anywhere', 'vendor/acme/standards')) extends Standard {
            protected function enforce(Package $package): void
            {
                $this->include(new class ($package) extends Standard {
                    protected function enforce(Package $package): void
                    {
                        $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('shared/phpstan.neon')));
                    }
                });
                $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('package/phpstan.neon')));
            }
        };

        $rules = $standard->rules();

        self::assertStringContainsString('vendor/acme/standards/templates/shared/phpstan.neon', $rules[0]->description());
        self::assertStringContainsString('vendor/acme/standards/templates/package/phpstan.neon', $rules[1]->description());
    }

    public function testAnIncludedTierSelfLocatesWhenItIsNotHandedThePackage(): void
    {
        $standard = new class (new Package('/anywhere', 'vendor/acme/standards')) extends Standard {
            protected function enforce(Package $package): void
            {
                // Constructed without a package, so it locates its own — here the engine repo, since that is where the class is defined.
                $this->include(new class extends Standard {
                    protected function enforce(Package $package): void
                    {
                        $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('shared/phpstan.neon')));
                    }
                });
                $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('package/phpstan.neon')));
            }
        };

        $rules = $standard->rules();

        // Both tiers read from one templates directory, yet they render against different roots: the reason a tier hands its package down.
        self::assertStringContainsString('includes "templates/shared/phpstan.neon"', $rules[0]->description());
        self::assertStringContainsString('vendor/acme/standards/templates/package/phpstan.neon', $rules[1]->description());
    }
}
