<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\PhpStan\PinnedValues\PhpStanPinnedValues;
use StandardsSync\Rules\PhpStan\PinnedValues\PinnedValues;

// A pinned value holding a '#': the hash is content, not a comment boundary — the quote-aware regression case.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'editorUrl' => 'https://editor.example/#open?file=%file%',
            ],
        ])));
    }
});
