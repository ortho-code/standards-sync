<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpUnitPinnedAttributes

Pins exact root-attribute values in the PHPUnit config: deviations are rewritten on every sync, so consumers cannot override them. Values a project may override belong in the seeded template instead — pin only what must not be overridden. Comparison is text-exact: phpunit reads only the spellings "true" and "false" (case-insensitively) and silently treats every other spelling as false — "1" included, which the schema accepts — so any other spelling of a pinned value is drift and normalizes. A pin always writes: a project without a PHPUnit config gets one created holding the pinned values.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpUnitPinnedAttributes(attributes: [
            'failOnWarning' => true,
            'failOnRisky' => true,
        ]));
    }
});
```

…which reports as: *Pins the PHPUnit root attributes: failOnWarning, failOnRisky.*

## A deviating value is rewritten in place

Fixture: [`tests/Scenario/PhpUnit/PinnedAttributes/fixtures/rewrites-a-deviating-value`](../../../tests/Scenario/PhpUnit/PinnedAttributes/fixtures/rewrites-a-deviating-value)

**Before** — `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" failOnWarning="false" failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**After:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## A spelling phpunit reads as false is normalized

Fixture: [`tests/Scenario/PhpUnit/PinnedAttributes/fixtures/normalizes-a-boolean-spelling`](../../../tests/Scenario/PhpUnit/PinnedAttributes/fixtures/normalizes-a-boolean-spelling)

**Before** — `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" failOnWarning="1" failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**After:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Missing attributes are appended inline on a single-line tag

Fixture: [`tests/Scenario/PhpUnit/PinnedAttributes/fixtures/adds-missing-attributes-inline`](../../../tests/Scenario/PhpUnit/PinnedAttributes/fixtures/adds-missing-attributes-inline)

**Before** — `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**After:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Missing attributes each get a fresh line in a multiline tag

Fixture: [`tests/Scenario/PhpUnit/PinnedAttributes/fixtures/adds-missing-attributes-on-a-fresh-line`](../../../tests/Scenario/PhpUnit/PinnedAttributes/fixtures/adds-missing-attributes-on-a-fresh-line)

**Before** — `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**After:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache"
         failOnWarning="true"
         failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## A compliant config is left byte-identical

Fixture: [`tests/Scenario/PhpUnit/PinnedAttributes/fixtures/leaves-a-compliant-config`](../../../tests/Scenario/PhpUnit/PinnedAttributes/fixtures/leaves-a-compliant-config)

`phpunit.xml` **stays byte-identical**:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## No phpunit config: one is created holding the pins

Fixture: [`tests/Scenario/PhpUnit/PinnedAttributes/fixtures/creates-the-config`](../../../tests/Scenario/PhpUnit/PinnedAttributes/fixtures/creates-the-config)

**Creates** `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```
