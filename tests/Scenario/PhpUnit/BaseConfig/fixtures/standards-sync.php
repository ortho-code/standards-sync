<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\PhpUnit\BaseConfig\PhpUnitBaseConfig;

// The template is inlined here; a real org package reads it from templates/ through Package.
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
    }
});
