<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\Psalm\BaseConfig\PsalmBaseConfig;
use AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmErrorLevel;
use AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmLoosestErrorLevel;

// The base config is declared first: the first rule to meet the absent config decides the created base — the org template, not the engine skeleton.
// The blank line before the closing marker keeps the trailing line break every file ends with.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PsalmBaseConfig(config: <<<'XML'
<?xml version="1.0"?>
<psalm
    errorLevel="2"
    resolveFromConfigFile="true"
    xmlns="https://getpsalm.org/schema/config"
>
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>

XML));
        $this->addRule(new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(4)));
    }
});
