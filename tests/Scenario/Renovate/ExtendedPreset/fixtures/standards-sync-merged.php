<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Renovate\ExtendedPreset\RenovateExtendedPreset;

// A framework standard declared beside the tier adds its own preset to the tier's extends list.
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
            $this->addRule(new RenovateExtendedPreset(preset: 'local>acme/framework-config'));
        }
    });
