<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpStanIncludedRuleset

Ensures the PHPStan config includes a given file, as a targeted edit that leaves the rest of the file untouched. An existing block-form includes: section gains the entry; a config without the section gains it at the top; a project without a PHPStan config gets one created, holding just the import.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/standards/phpstan.neon'));
    }
});
```

…which reports as: *Ensures the PHPStan config includes "vendor/acme/standards/phpstan.neon".*

## A project without a config gets one created

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/from-scratch`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/from-scratch)

**Creates** `phpstan.neon`:

```neon
includes:
	- vendor/acme/standards/phpstan.neon
```

## An existing includes section gains the import

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/insert-into-section`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/insert-into-section)

**Before** — `phpstan.neon`:

```neon
includes:
	- phpstan-baseline.neon

parameters:
	level: 6
```

**After:**

```neon
includes:
	- phpstan-baseline.neon
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 6
```

## A config without includes gains the section at the top

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/creates-section`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/creates-section)

**Before** — `phpstan.neon`:

```neon
parameters:
	level: 6
```

**After:**

```neon
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 6
```

## An already-imported config stays put

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/already-included`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/already-included)

`phpstan.neon` **stays byte-identical**:

```neon
includes:
	- vendor/acme/standards/phpstan.neon
```
