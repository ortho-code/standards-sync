<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\PhpUnit\PinnedAttributes\PhpUnitPinnedAttributes;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpUnitPinnedAttributes(attributes: [
            'failOnWarning' => true,
            'failOnRisky' => true,
        ]));
    }
});
