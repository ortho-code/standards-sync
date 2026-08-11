<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\Composer\Requirement\ComposerRequirement;
use AlleKnalle\StandardsSync\Rules\Composer\Requirement\RequirementType;
use AlleKnalle\StandardsSync\Rules\Composer\Requirement\VersionConstraint;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerRequirement(
            package: 'acme/runtime-support',
            constraint: VersionConstraint::fromString('^1.2'),
            type: RequirementType::Runtime,
        ));
    }
});
