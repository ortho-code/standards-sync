<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Rule\OnMissing;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanLevel;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanMinLevel;

// The write variant: nothing imported supplies a level, so a missing one is written.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7), onMissing: OnMissing::Write));
    }
});
