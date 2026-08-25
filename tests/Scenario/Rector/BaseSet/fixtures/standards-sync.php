<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\Rector\BaseSet\RectorBaseSet;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new RectorBaseSet(set: 'vendor/acme/standards/config/rector.php'));
    }
});
