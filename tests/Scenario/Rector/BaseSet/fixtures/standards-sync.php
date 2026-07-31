<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\Rector\BaseSet\RectorBaseSet;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new RectorBaseSet(set: "__DIR__ . '/vendor/acme/standards/config/rector.php'"));
    }
});
