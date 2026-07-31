<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\PhpStan\PinnedValues\PhpStanPinnedValues;
use AlleKnalle\StandardsSync\Rules\PhpStan\PinnedValues\PinnedValues;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'treatPhpDocTypesAsCertain' => false,
                'reportUnmatchedIgnoredErrors' => true,
            ],
        ])));
    }
});
