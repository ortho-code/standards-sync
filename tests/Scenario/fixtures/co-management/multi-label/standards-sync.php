<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Core\Spec\FileSpec;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\TemplateDirectory;

$templates = new TemplateDirectory(Path::fromString(__DIR__ . '/templates'));

// Two packages co-own one .gitignore under distinct labels; each label is its own block, and adopting the second block keeps the first.
return SyncConfig::create()->withRuleSet(new class($templates) extends ComposableRuleSet {
    public function __construct(TemplateDirectory $templates)
    {
        $this->addSpec(new FileSpec(
            relativePath: Path::fromString('.gitignore'),
            label: Label::fromString('ci'),
            content: $templates->read('ci.gitignore'),
        ));
        $this->addSpec(new FileSpec(
            relativePath: Path::fromString('.gitignore'),
            label: Label::fromString('framework'),
            content: $templates->read('framework.gitignore'),
        ));
    }
});
