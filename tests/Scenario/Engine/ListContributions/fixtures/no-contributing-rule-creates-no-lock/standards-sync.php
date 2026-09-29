<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('acme'),
            content: '/vendor/',
        ));
    }
});
