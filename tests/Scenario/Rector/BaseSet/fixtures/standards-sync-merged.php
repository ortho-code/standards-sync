<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Rector\BaseSet\RectorBaseSet;

// A framework standard declared beside the tier adds its own set to the tier's withSets().
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RectorBaseSet(set: 'vendor/acme/standards/config/rector.php'));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RectorBaseSet(set: 'vendor/acme/framework/config/rector.php'));
        }
    });
