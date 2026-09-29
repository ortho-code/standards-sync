<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# RenovateExtendedPreset

Ensures the renovate config extends a given preset, as a targeted edit that leaves the rest of the file untouched. An existing extends list gains a missing entry in the place of one no standard declares any more, and at its end otherwise; a config without the list gains it; a project without a renovate config gets one created in the org-chosen format. The optional comment is written and enforced on the entry's line where the grammar has comments (json5); a strict-JSON config carries the standard unexplained. Declarations of extended presets combine in declaration order, a preset declared twice counting once with its first declaration's comment, and the first declaration's creation format standing for all; a preset extended at an earlier sync and declared by nobody now is retracted, and every other entry is the project's and stays.

The preset itself is not distributed by this engine: renovate fetches it from its repository over the forge API, never from a composer install. It therefore lives where renovate's preset resolution looks — a bare "local><owner>/<repo>" reference resolves that repository's default.json — and not under the package's templates/ directory.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new RenovateExtendedPreset(preset: 'local>acme/renovate-config'));
    }
});
```

…which reports as: *Ensures the renovate config extends "local>acme/renovate-config".*

## A project without a config gets one created

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/from-scratch`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/from-scratch)

**Creates** `renovate.json`:

```json
{
    "extends": [
        "local>acme/renovate-config"
    ]
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

## An existing extends list gains the entry

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/insert-into-extends`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/insert-into-extends)

**Before** — `renovate.json`:

```json
{
  "extends": [
    "config:recommended"
  ]
}
```

**After:**

```json
{
  "extends": [
    "config:recommended",
    "local>acme/renovate-config"
  ]
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

## A config without extends gains the list

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/creates-extends-section`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/creates-extends-section)

**Before** — `renovate.json`:

```json
{
  "labels": ["dependencies"]
}
```

**After:**

```json
{
  "labels": ["dependencies"],
  "extends": [
    "local>acme/renovate-config"
  ]
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

## An already-extended config stays put

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/already-extended`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/already-extended)

`renovate.json` **stays byte-identical**:

```json
{
  "extends": [
    "config:recommended",
    "local>acme/renovate-config"
  ]
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

## A json5-only repo is synced in place, nothing is created

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/json5-only-repo`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/json5-only-repo)

**Before** — `renovate.json5`:

```json5
{
  // keep majors quiet
  extends: [
    'config:recommended',
  ],
}
```

**After:**

```json5
{
  // keep majors quiet
  extends: [
    'config:recommended',
    'local>acme/renovate-config',
  ],
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json5": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

## A renamed preset replaces its predecessor in place

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/replaces-a-renamed-preset-in-place`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/replaces-a-renamed-preset-in-place)

**Before** — `renovate.json`:

```json
{
  "extends": [
    "local>acme/old-config",
    "config:recommended"
  ]
}
```

**After:**

```json
{
  "extends": [
    "local>acme/renovate-config",
    "config:recommended"
  ]
}
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json": {
            "extends": [
                "local>acme/old-config"
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
        "renovate.json": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

## A preset no standard declares any more is retracted with its line, and the project's entries stay

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/retracts-a-preset-no-longer-declared`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/retracts-a-preset-no-longer-declared)

**Before** — `renovate.json5`:

```json5
{
  // keep majors quiet
  extends: [
    'local>acme/renovate-config',
    'local>acme/strict-config', // too noisy
    'config:recommended',
  ],
}
```

**After:**

```json5
{
  // keep majors quiet
  extends: [
    'local>acme/renovate-config',
    'config:recommended',
  ],
}
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json5": {
            "extends": [
                "local>acme/renovate-config",
                "local>acme/strict-config"
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
        "renovate.json5": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new RenovateExtendedPreset(
            preset: 'local>acme/renovate-config',
            createAs: RenovateConfigFormat::Json5,
            comment: 'org standard; sync re-adds this entry',
        ));
    }
});
```

…which reports as: *Ensures the renovate config extends "local>acme/renovate-config".*

## An org preferring json5 creates that format, annotated

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/from-scratch-json5`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/from-scratch-json5)

**Creates** `renovate.json5`:

```json5
{
    "extends": [
        "local>acme/renovate-config" // org standard; sync re-adds this entry
    ]
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json5": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new RenovateExtendedPreset(
            preset: 'local>acme/renovate-config',
            comment: 'org standard; sync re-adds this entry',
        ));
    }
});
```

…which reports as: *Ensures the renovate config extends "local>acme/renovate-config".*

## A compliant entry gains the enforced comment

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/json5-comment-enforced`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/json5-comment-enforced)

**Before** — `renovate.json5`:

```json5
{
  extends: [
    'local>acme/renovate-config',
  ],
}
```

**After:**

```json5
{
  extends: [
    'local>acme/renovate-config', // org standard; sync re-adds this entry
  ],
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json5": {
            "extends": [
                "local>acme/renovate-config"
            ]
        }
    }
}
```

Declared as:

```php
// A framework standard declared beside the tier adds its own preset to the tier's extends list.
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RenovateExtendedPreset(preset: 'local>acme/renovate-config'));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RenovateExtendedPreset(preset: 'local>acme/framework-config'));
        }
    });
```

…which report as:

- *Ensures the renovate config extends "local>acme/renovate-config".*
- *Ensures the renovate config extends "local>acme/framework-config".*

## A second declaration adds its preset after the first

Fixture: [`tests/Scenario/Renovate/ExtendedPreset/fixtures/merges-a-second-declaration`](../../../tests/Scenario/Renovate/ExtendedPreset/fixtures/merges-a-second-declaration)

**Before** — `renovate.json`:

```json
{
  "extends": [
    "config:recommended"
  ]
}
```

**After:**

```json
{
  "extends": [
    "config:recommended",
    "local>acme/renovate-config",
    "local>acme/framework-config"
  ]
}
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "renovate.json": {
            "extends": [
                "local>acme/renovate-config",
                "local>acme/framework-config"
            ]
        }
    }
}
```
