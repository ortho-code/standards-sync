<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\PhpUnit\BaseConfig\PhpUnitBaseConfig;
use AlleKnalle\StandardsSync\Rules\PhpUnit\PinnedAttributes\PhpUnitPinnedAttributes;

// The base config is declared first: the first rule to meet the absent config decides the created base — the org template, not the engine skeleton.
// The blank line before the closing marker keeps the trailing line break every file ends with.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpUnitBaseConfig(config: <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit bootstrap="vendor/autoload.php"
                     cacheDirectory=".phpunit.cache"
                     beStrictAboutOutputDuringTests="true"
                     failOnWarning="true"
                     failOnRisky="true">
                <testsuites>
                    <testsuite name="default">
                        <directory>tests</directory>
                    </testsuite>
                </testsuites>
                <source>
                    <include>
                        <directory>src</directory>
                    </include>
                </source>
            </phpunit>

            XML));
        $this->addRule(new PhpUnitPinnedAttributes(attributes: [
            'failOnWarning' => true,
            'failOnRisky' => true,
        ]));
    }
});
