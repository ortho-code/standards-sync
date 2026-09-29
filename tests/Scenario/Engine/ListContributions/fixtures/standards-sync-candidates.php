<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Renovate\ExtendedPreset\RenovateExtendedPreset;
use OrthoCode\StandardsSync\Rules\Renovate\RenovateConfigFormat;

// Renovate stands in for any rule whose declared candidates vary with its configuration: the two creation formats order the candidates differently, yet both resolve to the one config the repository has.
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RenovateExtendedPreset(preset: 'local>acme/renovate-config'));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RenovateExtendedPreset(preset: 'local>acme/framework-config', createAs: RenovateConfigFormat::Json5));
        }
    });
