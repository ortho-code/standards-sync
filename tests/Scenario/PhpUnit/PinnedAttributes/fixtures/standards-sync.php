<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\PhpUnit\PinnedAttributes\PhpUnitPinnedAttributes;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpUnitPinnedAttributes(attributes: [
            'failOnWarning' => true,
            'failOnRisky' => true,
        ]));
    }
});
