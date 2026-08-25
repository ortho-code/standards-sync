<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpUnit

Rules: [PhpUnitBaseConfig](PhpUnitBaseConfig.md) · [PhpUnitPinnedAttributes](PhpUnitPinnedAttributes.md)

## Family

Declared as:

```php
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
```

…which report as:

- *Seeds a missing PHPUnit config with the org base config.*
- *Pins the PHPUnit root attributes: failOnWarning, failOnRisky.*

### From scratch the template declared first wins over the skeleton

Fixture: [`tests/Scenario/PhpUnit/Family/fixtures/from-scratch`](../../../tests/Scenario/PhpUnit/Family/fixtures/from-scratch)

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

### An existing config is converged, never reseeded

Fixture: [`tests/Scenario/PhpUnit/Family/fixtures/converges-an-existing-config`](../../../tests/Scenario/PhpUnit/Family/fixtures/converges-an-existing-config)

**Before** — `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- our own notes stay ours -->
<phpunit bootstrap="tests/bootstrap.php" failOnWarning="1">
    <testsuites>
        <testsuite name="app">
            <directory>tests/App</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**After:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- our own notes stay ours -->
<phpunit bootstrap="tests/bootstrap.php" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="app">
            <directory>tests/App</directory>
        </testsuite>
    </testsuites>
</phpunit>
```
