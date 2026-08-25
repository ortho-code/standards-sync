<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\Ecs\BaseSet\EcsBaseSet;

// Two org tiers: the second tier includes the base tier first, so the base entry is created first and the second tier's entry lands after it.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->include(new class extends ComposableRuleSet {
            public function __construct()
            {
                $this->addRule(new EcsBaseSet(set: 'vendor/acme/standards/config/ecs.php'));
            }
        });
        $this->addRule(new EcsBaseSet(set: 'vendor/acme/platform-standards/config/ecs.php'));
    }
});
