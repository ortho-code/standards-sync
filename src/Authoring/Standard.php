<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Authoring;

use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;

/**
 * Base for the rule set an org package ships: the org's standard.
 * A subclass holds nothing but its rules: enforce() receives the package they belong to, located automatically through composer's install record, or injected for tests and deviating layouts.
 */
abstract class Standard extends ComposableRuleSet
{
    public function __construct(?Package $package = null)
    {
        $this->enforce($package ?? Package::fromClass(static::class));
    }

    /** Registers everything this standard enforces — its rules, and any included standard — in declaration order. */
    abstract protected function enforce(Package $package): void;
}
