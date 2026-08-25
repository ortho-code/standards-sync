<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\PhpStan\PinnedValues\PhpStanPinnedValues;
use StandardsSync\Rules\PhpStan\PinnedValues\PinnedValues;

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
