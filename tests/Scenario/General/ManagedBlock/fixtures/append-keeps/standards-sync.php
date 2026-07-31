<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Rules\General\ManagedBlock\Label;
use AlleKnalle\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\TemplateDirectory;

$templates = new TemplateDirectory(Path::fromString(__DIR__ . '/templates'));

return SyncConfig::create()->withRuleSet(new class($templates) extends ComposableRuleSet {
    public function __construct(TemplateDirectory $templates)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('test'),
            content: $templates->read('.gitignore'),
        ));
    }
});
