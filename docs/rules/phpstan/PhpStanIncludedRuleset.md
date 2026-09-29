<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpStanIncludedRuleset

Ensures the PHPStan config includes a given file, as a targeted edit that leaves the rest of the file untouched. An existing block-form includes: section gains a missing entry in the place of one no standard declares any more, and after its last entry otherwise; a config without the section gains it at the top; a project without a PHPStan config gets one created, holding just the imports. Declarations of included rulesets combine in declaration order, a ruleset declared twice counting once; an include declared at an earlier sync and declared by nobody now is retracted, and every other include is the project's and stays.

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

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
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

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
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

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
```

## An already-imported config stays put

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/already-included`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/already-included)

`phpstan.neon` **stays byte-identical**:

```neon
includes:
	- vendor/acme/standards/phpstan.neon
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
```

## A moved ruleset replaces its predecessor in place, keeping the line's comment

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/replaces-a-moved-ruleset-in-place`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/replaces-a-moved-ruleset-in-place)

**Before** — `phpstan.neon`:

```neon
includes:
	- vendor/acme/standards/rules.neon # the org ruleset
	- phpstan-baseline.neon

parameters:
	level: 6
```

**After:**

```neon
includes:
	- vendor/acme/standards/phpstan.neon # the org ruleset
	- phpstan-baseline.neon

parameters:
	level: 6
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/rules.neon"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
```

## A ruleset no standard declares any more is retracted, and the project's include stays

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/retracts-a-ruleset-no-longer-declared`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/retracts-a-ruleset-no-longer-declared)

**Before** — `phpstan.neon`:

```neon
includes:
	- vendor/acme/standards/phpstan.neon
	- vendor/acme/standards/strict.neon
	- phpstan-baseline.neon

parameters:
	level: 6
```

**After:**

```neon
includes:
	- vendor/acme/standards/phpstan.neon
	- phpstan-baseline.neon

parameters:
	level: 6
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon",
                "vendor/acme/standards/strict.neon"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
```

## An includes list at the section's own indentation gains the import at that indentation

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/inserts-into-a-zero-indent-list`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/inserts-into-a-zero-indent-list)

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

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
```

Declared as:

```php
// A framework standard declared beside the tier adds its own ruleset to the tier's includes.
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/standards/phpstan.neon'));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/framework/phpstan.neon'));
        }
    });
```

…which report as:

- *Ensures the PHPStan config includes "vendor/acme/standards/phpstan.neon".*
- *Ensures the PHPStan config includes "vendor/acme/framework/phpstan.neon".*

## A second declaration adds its ruleset after the first

Fixture: [`tests/Scenario/PhpStan/IncludedRuleset/fixtures/merges-a-second-declaration`](../../../tests/Scenario/PhpStan/IncludedRuleset/fixtures/merges-a-second-declaration)

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
	- vendor/acme/framework/phpstan.neon

parameters:
	level: 6
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon",
                "vendor/acme/framework/phpstan.neon"
            ]
        }
    }
}
```
