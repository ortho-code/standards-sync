<?php

declare(strict_types=1);

use StandardsSync\Authoring\Package;
use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\General\ManagedBlock\Label;
use StandardsSync\Rules\General\ManagedBlock\ManagedBlock;

// The fixture is its own package: distributed content in templates/, sitting at the (virtual) consumer root.
$package = new Package(__DIR__, '');

return SyncConfig::create()->withRuleSet(new class($package) extends ComposableRuleSet {
    public function __construct(Package $package)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.editorconfig'),
            label: Label::fromString('test'),
            content: $package->read('.editorconfig'),
        ));
    }
});
