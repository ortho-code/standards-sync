<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\PhpStan\MinLevel\PhpStanLevel;
use OrthoCode\StandardsSync\Rules\PhpStan\MinLevel\PhpStanMinLevel;

// The commented floor: the org explains the managed line in the file itself, and the comment is enforced like the value.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanMinLevel(
            minLevel: PhpStanLevel::fromInt(7),
            comment: 'org minimum: raise freely, lowering is reverted on sync',
        ));
    }
});
