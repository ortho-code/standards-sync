<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PsalmLoosestErrorLevel

Keeps the Psalm error level at or below a loosest allowed value — on psalm's inverted scale (1 strictest, 8 loosest) the numeric ceiling is the semantic strictness floor. A looser written level is lowered to the limit, a stricter or equal one is never touched; an absent errorLevel attribute means psalm's default and is made explicit (capped at the limit); a missing config is created at the limit, so bootstrapped configs start at the org standard.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(4)));
    }
});
```

…which reports as: *Keeps the Psalm error level at or below 4 (lower is stricter).*

## A looser level is lowered to the limit

Fixture: [`tests/Scenario/Psalm/LoosestErrorLevel/fixtures/lowers-a-looser-level`](../../../tests/Scenario/Psalm/LoosestErrorLevel/fixtures/lowers-a-looser-level)

**Before** — `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm
    errorLevel="8"
    resolveFromConfigFile="true"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
>
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
```

**After:**

```xml
<?xml version="1.0"?>
<psalm
    errorLevel="4"
    resolveFromConfigFile="true"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
>
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
```

## A stricter level is never touched

Fixture: [`tests/Scenario/Psalm/LoosestErrorLevel/fixtures/leaves-a-stricter-level`](../../../tests/Scenario/Psalm/LoosestErrorLevel/fixtures/leaves-a-stricter-level)

`psalm.xml` **stays byte-identical**:

```xml
<?xml version="1.0"?>
<psalm errorLevel="2" resolveFromConfigFile="true">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
```

## An equal level is never touched

Fixture: [`tests/Scenario/Psalm/LoosestErrorLevel/fixtures/leaves-an-equal-level`](../../../tests/Scenario/Psalm/LoosestErrorLevel/fixtures/leaves-an-equal-level)

`psalm.xml` **stays byte-identical**:

```xml
<?xml version="1.0"?>
<psalm errorLevel="4" resolveFromConfigFile="true">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
```

## An absent errorLevel is made explicit as the default

Fixture: [`tests/Scenario/Psalm/LoosestErrorLevel/fixtures/makes-the-default-explicit`](../../../tests/Scenario/Psalm/LoosestErrorLevel/fixtures/makes-the-default-explicit)

**Before** — `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm
    resolveFromConfigFile="true"
    xmlns="https://getpsalm.org/schema/config"
>
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
```

**After:**

```xml
<?xml version="1.0"?>
<psalm
    resolveFromConfigFile="true"
    xmlns="https://getpsalm.org/schema/config"
    errorLevel="2"
>
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
```

## No psalm config: one is created at the limit

Fixture: [`tests/Scenario/Psalm/LoosestErrorLevel/fixtures/creates-the-config`](../../../tests/Scenario/Psalm/LoosestErrorLevel/fixtures/creates-the-config)

**Creates** `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm errorLevel="4" xmlns="https://getpsalm.org/schema/config">
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
```

Declared as:

```php
// A limit stricter than psalm's implicit default, so an absent errorLevel is made explicit at the limit instead.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(1)));
    }
});
```

…which reports as: *Keeps the Psalm error level at or below 1 (lower is stricter).*

## An absent errorLevel meets a limit stricter than the default

Fixture: [`tests/Scenario/Psalm/LoosestErrorLevel/fixtures/makes-the-default-explicit-at-a-stricter-limit`](../../../tests/Scenario/Psalm/LoosestErrorLevel/fixtures/makes-the-default-explicit-at-a-stricter-limit)

**Before** — `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm resolveFromConfigFile="true">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
```

**After:**

```xml
<?xml version="1.0"?>
<psalm resolveFromConfigFile="true" errorLevel="1">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
```
