<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanLevel;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanMinLevel;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7)));
    }
});
