<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\ComposerRequirement;
use OrthoCode\StandardsSync\Rules\Composer\Requirement\VersionConstraint;
use OrthoCode\StandardsSync\Rules\Composer\Script\ComposerScript;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerRequirement(package: 'phpstan/phpstan', constraint: VersionConstraint::fromString('^2.5')));
        $this->addRule(new ComposerScript(name: 'app-check-standards', commands: ['vendor/bin/standards-sync sync --check']));
    }
});
