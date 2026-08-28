<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Authoring\Package;
use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;

$package = new Package(__DIR__, '');

return SyncConfig::create()->withRuleSet(new class ($package) extends ComposableRuleSet {
    public function __construct(Package $package)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('test'),
            content: $package->read('.gitignore'),
        ));
    }
});
