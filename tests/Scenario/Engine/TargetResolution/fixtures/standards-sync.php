<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use StandardsSync\Rules\PhpStan\MinLevel\PhpStanLevel;
use StandardsSync\Rules\PhpStan\MinLevel\PhpStanMinLevel;

// The phpstan family stands in for any dist-convention tool; resolution itself is engine behaviour.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/standards/phpstan.neon'));
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7)));
    }
});
