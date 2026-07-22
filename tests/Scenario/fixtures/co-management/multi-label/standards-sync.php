<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Rules\Block\Label;
use AlleKnalle\StandardsSync\Rules\Block\ManagedBlockRule;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\TemplateDirectory;

$templates = new TemplateDirectory(Path::fromString(__DIR__ . '/templates'));

// Two packages co-own one .gitignore under distinct labels; each label is its own block, and adopting the second block keeps the first.
return SyncConfig::create()->withRuleSet(new class($templates) extends ComposableRuleSet {
    public function __construct(TemplateDirectory $templates)
    {
        $this->addRule(new ManagedBlockRule(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('ci'),
            content: $templates->read('ci.gitignore'),
        ));
        $this->addRule(new ManagedBlockRule(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('framework'),
            content: $templates->read('framework.gitignore'),
        ));
    }
});
