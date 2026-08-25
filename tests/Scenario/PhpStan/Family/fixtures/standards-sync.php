<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use StandardsSync\Rules\PhpStan\MinLevel\PhpStanLevel;
use StandardsSync\Rules\PhpStan\MinLevel\PhpStanMinLevel;

// The import rule is declared first, so the floor rule receives the config the import just created.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/standards/phpstan.neon'));
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7)));
    }
});
