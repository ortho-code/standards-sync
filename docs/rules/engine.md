<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# Engine behaviours

## Target Resolution

Declared as:

```php
// The phpstan family stands in for any dist-convention tool; resolution itself is engine behaviour.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/standards/phpstan.neon'));
        $this->addRule(new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7)));
    }
});
```

…which report as:

- *Ensures the PHPStan config includes "vendor/acme/standards/phpstan.neon".*
- *Keeps the PHPStan level at or above 7.*

### A local override beside the dist file is left untouched

Fixture: [`tests/Scenario/Engine/TargetResolution/fixtures/dist-shadowed-by-local`](../../tests/Scenario/Engine/TargetResolution/fixtures/dist-shadowed-by-local)

`phpstan.neon` **stays byte-identical**:

```neon
includes:
	- phpstan.neon.dist

parameters:
	level: 3
```

**Before** — `phpstan.neon.dist`:

```neon
parameters:
	level: 5
```

**After:**

```neon
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 7
```

### A lone dist file is synced normally

Fixture: [`tests/Scenario/Engine/TargetResolution/fixtures/lone-dist-file`](../../tests/Scenario/Engine/TargetResolution/fixtures/lone-dist-file)

**Before** — `phpstan.neon.dist`:

```neon
parameters:
	level: 5
```

**After:**

```neon
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 7
```
