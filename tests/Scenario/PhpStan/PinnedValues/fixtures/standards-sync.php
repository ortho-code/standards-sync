<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\PhpStan\PinnedValues\PhpStanPinnedValues;
use OrthoCode\StandardsSync\Rules\PhpStan\PinnedValues\PinnedValues;

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
