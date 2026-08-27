<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Renovate\ExtendedPreset\RenovateExtendedPreset;
use OrthoCode\StandardsSync\Rules\Renovate\RenovateConfigFormat;

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
