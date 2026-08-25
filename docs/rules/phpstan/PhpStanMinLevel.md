<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpStanMinLevel

Keeps the PHPStan level at or above a minimum: a lower written level is raised, a stricter or equal one is never touched, and a missing level line — or the whole config — is written carrying the floor. The rule reads only what the config itself writes, and a written line wins phpstan's include-merge, so the guarantee holds whatever an imported ruleset carries. An optional org comment is enforced on the level line like the value itself — the line explains why it is managed; without one, a project's own trailing comment survives.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7)));
    }
});
```

…which reports as: *Keeps the PHPStan level at or above 7.*

## A lower level is raised to the floor

Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/raises-a-lower-level`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/raises-a-lower-level)

**Before** — `phpstan.neon`:

```neon
parameters:
	level: 4
	paths:
		- src
```

**After:**

```neon
parameters:
	level: 7
	paths:
		- src
```

## A stricter level is never touched

Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/leaves-a-stricter-level`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/leaves-a-stricter-level)

`phpstan.neon` **stays byte-identical**:

```neon
parameters:
	level: 8
```

## A missing level is written

Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/writes-a-missing-level`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/writes-a-missing-level)

**Before** — `phpstan.neon`:

```neon
includes:
	- vendor/acme/standards/phpstan.neon
```

**After:**

```neon
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 7
```

## No phpstan config: one is created carrying the floor

Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/creates-the-config`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/creates-the-config)

**Creates** `phpstan.neon`:

```neon
parameters:
	level: 7
```

Declared as:

```php
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
```

…which reports as: *Keeps the PHPStan level at or above 7.*

## The org comment is written with a raised level

Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/writes-the-comment-on-a-raise`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/writes-the-comment-on-a-raise)

**Before** — `phpstan.neon`:

```neon
parameters:
	level: 4
```

**After:**

```neon
parameters:
	level: 7 # org minimum: raise freely, lowering is reverted on sync
```

## A deviating value and comment revert together

Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/rewrites-value-and-comment-together`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/rewrites-value-and-comment-together)

**Before** — `phpstan.neon`:

```neon
parameters:
	level: 4 # we lowered this deliberately
```

**After:**

```neon
parameters:
	level: 7 # org minimum: raise freely, lowering is reverted on sync
```

## A compliant level gains the org comment untouched

Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/comments-a-compliant-level`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/comments-a-compliant-level)

**Before** — `phpstan.neon`:

```neon
parameters:
	level: 8
```

**After:**

```neon
parameters:
	level: 8 # org minimum: raise freely, lowering is reverted on sync
```
