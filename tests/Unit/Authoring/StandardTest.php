<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Authoring;

use AlleKnalle\StandardsSync\Authoring\Package;
use AlleKnalle\StandardsSync\Authoring\Standard;
use AlleKnalle\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Standard::class)]
final class StandardTest extends TestCase
{
    public function testEnforceReceivesTheInjectedPackage(): void
    {
        $standard = new class(new Package('/anywhere', 'vendor/acme/standards')) extends Standard {
            protected function enforce(Package $package): void
            {
                $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('phpstan.neon')));
            }
        };

        $rules = $standard->rules();

        self::assertCount(1, $rules);
        self::assertStringContainsString('vendor/acme/standards/templates/phpstan.neon', $rules[0]->description());
    }
}
