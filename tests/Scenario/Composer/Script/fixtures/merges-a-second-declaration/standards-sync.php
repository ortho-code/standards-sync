<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Composer\Script\ComposerScript;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests']));
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-run-tests', '@app-lint']));
    }
});
