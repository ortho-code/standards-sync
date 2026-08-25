<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\Renovate\ExtendedPreset\RenovateExtendedPreset;
use StandardsSync\Rules\Renovate\RenovateConfigFormat;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new RenovateExtendedPreset(
            preset: 'local>acme/renovate-config',
            createAs: RenovateConfigFormat::Json5,
            comment: 'org standard; sync re-adds this entry',
        ));
    }
});
