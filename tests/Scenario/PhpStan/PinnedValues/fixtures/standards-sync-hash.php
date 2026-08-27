<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\PhpStan\PinnedValues\PhpStanPinnedValues;
use OrthoCode\StandardsSync\Rules\PhpStan\PinnedValues\PinnedValues;

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
