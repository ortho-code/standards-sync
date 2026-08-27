<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\PhpStan\MinLevel\PhpStanLevel;
use OrthoCode\StandardsSync\Rules\PhpStan\MinLevel\PhpStanMinLevel;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7)));
    }
});
