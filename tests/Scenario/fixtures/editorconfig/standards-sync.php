<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Core\Spec\FileSpec;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\TemplateDirectory;

$templates = new TemplateDirectory(Path::fromString(__DIR__ . '/templates'));

return SyncConfig::create()->withRuleSet(new class($templates) extends ComposableRuleSet {
    public function __construct(TemplateDirectory $templates)
    {
        $this->addSpec(new FileSpec(
            relativePath: Path::fromString('.editorconfig'),
            label: Label::fromString('test'),
            content: $templates->read('.editorconfig'),
        ));
    }
});
