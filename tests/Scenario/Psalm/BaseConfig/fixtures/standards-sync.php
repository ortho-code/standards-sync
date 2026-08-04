<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\Psalm\BaseConfig\PsalmBaseConfig;

// The template is inlined here; a real org package reads it from templates/ through Package.
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
    }
});
