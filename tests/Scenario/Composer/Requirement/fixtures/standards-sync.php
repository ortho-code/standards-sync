<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\ComposerRequirement;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\VersionConstraint;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerRequirement(package: 'phpstan/phpstan', constraint: VersionConstraint::fromString('^2.5')));
    }
});
