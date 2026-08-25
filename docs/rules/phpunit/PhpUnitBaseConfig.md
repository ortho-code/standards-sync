<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpUnitBaseConfig

Seeds a project that has no PHPUnit config with the org's template, verbatim, and never edits an existing config. For a tool with no import tier the template is one-shot — only values that also have their own rule stay enforced: the template bootstraps, rules converge. Declared first in a rule set it decides the created base for the whole family — later rules receive the template instead of the engine skeleton.

Declared as:

```php
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
```

…which reports as: *Seeds a missing PHPUnit config with the org base config.*

## A missing config is seeded with the template

Fixture: [`tests/Scenario/PhpUnit/BaseConfig/fixtures/seeds-a-missing-config`](../../../tests/Scenario/PhpUnit/BaseConfig/fixtures/seeds-a-missing-config)

**Creates** `phpunit.xml`:

```xml
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
```

## An existing config is never edited

Fixture: [`tests/Scenario/PhpUnit/BaseConfig/fixtures/never-edits-an-existing-config`](../../../tests/Scenario/PhpUnit/BaseConfig/fixtures/never-edits-an-existing-config)

`phpunit.xml` **stays byte-identical**:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="app">
            <directory>tests/App</directory>
        </testsuite>
    </testsuites>
</phpunit>
```
