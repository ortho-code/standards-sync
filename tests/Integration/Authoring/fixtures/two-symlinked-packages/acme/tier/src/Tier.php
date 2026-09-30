<?php

declare(strict_types=1);

namespace Acme\Tier;

use Acme\Base\Base;
use OrthoCode\StandardsSync\Authoring\Package;
use OrthoCode\StandardsSync\Authoring\Standard;
use OrthoCode\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;

final class Tier extends Standard
{
    protected function enforce(Package $package): void
    {
        $this->include(new Base());
        $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('tier.neon')));
    }
}
