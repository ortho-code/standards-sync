<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmErrorLevel;
use OrthoCode\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmLoosestErrorLevel;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(4)));
    }
});
