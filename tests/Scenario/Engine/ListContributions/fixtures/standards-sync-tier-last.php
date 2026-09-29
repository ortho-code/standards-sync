<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Composer\Script\ComposerScript;

$tier = new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests']));
    }
};

$framework = new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-lint']));
    }
};

return SyncConfig::create()->withRuleSet($framework)->withRuleSet($tier);
