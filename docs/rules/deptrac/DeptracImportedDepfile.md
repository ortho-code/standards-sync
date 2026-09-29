<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# DeptracImportedDepfile

Ensures the deptrac config imports a given depfile, as a targeted edit that leaves the rest of the file untouched. An existing block-form imports: section gains a missing entry in the place of one no standard declares any more, and after its last entry otherwise; a config without the section gains it at the top; a project without a deptrac config gets one created, holding just the imports. Declarations of imported depfiles combine in declaration order, a depfile declared twice counting once; an import declared at an earlier sync and declared by nobody now is retracted, and every other import is the project's and stays.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/standards/deptrac.yaml'));
    }
});
```

…which reports as: *Ensures the deptrac config imports "vendor/acme/standards/deptrac.yaml".*

## A project without a config gets one created

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/from-scratch`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/from-scratch)

**Creates** `deptrac.yaml`:

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml"
            ]
        }
    }
}
```

## An existing imports section gains the depfile

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/insert-into-section`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/insert-into-section)

**Before** — `deptrac.yaml`:

```yaml
imports:
  - local/architecture.yaml

deptrac:
  paths:
    - ./src
```

**After:**

```yaml
imports:
  - local/architecture.yaml
  - vendor/acme/standards/deptrac.yaml

deptrac:
  paths:
    - ./src
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml"
            ]
        }
    }
}
```

## A config without imports gains the section at the top

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/creates-section`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/creates-section)

**Before** — `deptrac.yaml`:

```yaml
deptrac:
  paths:
    - ./src
```

**After:**

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml

deptrac:
  paths:
    - ./src
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml"
            ]
        }
    }
}
```

## An already-imported config stays put

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/already-imported`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/already-imported)

`deptrac.yaml` **stays byte-identical**:

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml

deptrac:
  paths:
    - ./src
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml"
            ]
        }
    }
}
```

## A moved depfile replaces its predecessor in place, keeping the line's comment

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/replaces-a-moved-depfile-in-place`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/replaces-a-moved-depfile-in-place)

**Before** — `deptrac.yaml`:

```yaml
imports:
  - vendor/acme/standards/layers.yaml # the org layers
  - local/architecture.yaml

deptrac:
  paths:
    - ./src
```

**After:**

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml # the org layers
  - local/architecture.yaml

deptrac:
  paths:
    - ./src
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/layers.yaml"
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
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml"
            ]
        }
    }
}
```

## A depfile no standard declares any more is retracted, and the project's import stays

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/retracts-a-depfile-no-longer-declared`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/retracts-a-depfile-no-longer-declared)

**Before** — `deptrac.yaml`:

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml
  - vendor/acme/standards/strict.yaml
  - local/architecture.yaml

deptrac:
  paths:
    - ./src
```

**After:**

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml
  - local/architecture.yaml

deptrac:
  paths:
    - ./src
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml",
                "vendor/acme/standards/strict.yaml"
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
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml"
            ]
        }
    }
}
```

Declared as:

```php
// A framework standard declared beside the tier adds its own depfile to the tier's imports.
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/standards/deptrac.yaml'));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/framework/deptrac.yaml'));
        }
    });
```

…which report as:

- *Ensures the deptrac config imports "vendor/acme/standards/deptrac.yaml".*
- *Ensures the deptrac config imports "vendor/acme/framework/deptrac.yaml".*

## A second declaration adds its depfile after the first

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/merges-a-second-declaration`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/merges-a-second-declaration)

**Before** — `deptrac.yaml`:

```yaml
imports:
  - local/architecture.yaml

deptrac:
  paths:
    - ./src
```

**After:**

```yaml
imports:
  - local/architecture.yaml
  - vendor/acme/standards/deptrac.yaml
  - vendor/acme/framework/deptrac.yaml

deptrac:
  paths:
    - ./src
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "deptrac.yaml": {
            "imports": [
                "vendor/acme/standards/deptrac.yaml",
                "vendor/acme/framework/deptrac.yaml"
            ]
        }
    }
}
```
