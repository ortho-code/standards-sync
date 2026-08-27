<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;

// A rule that deliberately syncs broken XML, so the tester's well-formedness tier has something to catch.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new class implements Rule {
            public function target(): FileTarget
            {
                return FileTarget::fromString('broken.xml');
            }

            public function apply(?string $content): ?string
            {
                return "<foo><bar></foo>\n";
            }

            public function description(): string
            {
                return 'Writes deliberately broken XML.';
            }
        });
    }
});
