<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmErrorLevel;
use AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmLoosestErrorLevel;

// A limit stricter than psalm's implicit default, so an absent errorLevel is made explicit at the limit instead.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(1)));
    }
});
