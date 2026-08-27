<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use OrthoCode\StandardsSync\Rules\PhpStan\MinLevel\PhpStanLevel;
use OrthoCode\StandardsSync\Rules\PhpStan\MinLevel\PhpStanMinLevel;

// The import rule is declared first, so the floor rule receives the config the import just created.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/standards/phpstan.neon'));
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7)));
    }
});
