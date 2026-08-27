<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Authoring;

use OrthoCode\StandardsSync\Authoring\Package;
use OrthoCode\StandardsSync\Authoring\Standard;
use OrthoCode\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
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
