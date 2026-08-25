<?php

declare(strict_types=1);

use StandardsSync\Authoring\Package;
use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\General\ManagedBlock\Label;
use StandardsSync\Rules\General\ManagedBlock\ManagedBlock;

$package = new Package(__DIR__, '');

return SyncConfig::create()->withRuleSet(new class($package) extends ComposableRuleSet {
    public function __construct(Package $package)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('test'),
            content: $package->read('.gitignore'),
        ));
    }
});
