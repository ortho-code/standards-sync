<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# ComposerScript

Declares commands a named composer script runs: each is enforced present, a missing one inserted after the command declared before it, and every other command in the script is the project's and stays. Declarations of one script combine in declaration order, each adding its commands after the earlier ones'; a command declared twice counts once. A command declared at an earlier sync and declared by nobody now is retracted, with or without arguments after it. A root without a manifest is not a composer project, so the rule abstains rather than creating one.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-check-standards', commands: ['vendor/bin/standards-sync sync --check']));
    }
});
```

…which reports as: *Runs "vendor/bin/standards-sync sync --check" in the composer script "app-check-standards", beside any commands the project adds.*

## A manifest without the script gains it

Fixture: [`tests/Scenario/Composer/Script/fixtures/adds-the-script`](../../../tests/Scenario/Composer/Script/fixtures/adds-the-script)

**Before** — `composer.json`:

```json
{
    "name": "acme/project"
}
```

**After:**

```json
{
    "name": "acme/project",
    "scripts": {
        "app-check-standards": [
            "vendor/bin/standards-sync sync --check"
        ]
    }
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
        "composer.json": {
            "scripts.app-check-standards": [
                "vendor/bin/standards-sync sync --check"
            ]
        }
    }
}
```

## A script running other commands keeps them, the declared one inserted first

Fixture: [`tests/Scenario/Composer/Script/fixtures/keeps-the-projects-commands`](../../../tests/Scenario/Composer/Script/fixtures/keeps-the-projects-commands)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-check-standards": [
            "vendor/bin/standards-sync sync"
        ],
        "app-run-tests": [
            "vendor/bin/phpunit"
        ]
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-check-standards": [
            "vendor/bin/standards-sync sync --check",
            "vendor/bin/standards-sync sync"
        ],
        "app-run-tests": [
            "vendor/bin/phpunit"
        ]
    }
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
        "composer.json": {
            "scripts.app-check-standards": [
                "vendor/bin/standards-sync sync --check"
            ]
        }
    }
}
```

## A script already running the declared commands keeps its bytes; only the lock is written

Fixture: [`tests/Scenario/Composer/Script/fixtures/leaves-a-matching-script`](../../../tests/Scenario/Composer/Script/fixtures/leaves-a-matching-script)

`composer.json` **stays byte-identical**:

```json
{
    "scripts": {
        "app-check-standards": [
            "vendor/bin/standards-sync sync --check"
        ]
    }
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
        "composer.json": {
            "scripts.app-check-standards": [
                "vendor/bin/standards-sync sync --check"
            ]
        }
    }
}
```

## A string script running something else becomes a list holding both

Fixture: [`tests/Scenario/Composer/Script/fixtures/extends-a-string-script`](../../../tests/Scenario/Composer/Script/fixtures/extends-a-string-script)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-check-standards": "vendor/bin/standards-sync sync"
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-check-standards": [
            "vendor/bin/standards-sync sync --check",
            "vendor/bin/standards-sync sync"
        ]
    }
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
        "composer.json": {
            "scripts.app-check-standards": [
                "vendor/bin/standards-sync sync --check"
            ]
        }
    }
}
```

## A string script running the declared command is left as written

Fixture: [`tests/Scenario/Composer/Script/fixtures/leaves-a-matching-string-script`](../../../tests/Scenario/Composer/Script/fixtures/leaves-a-matching-string-script)

`composer.json` **stays byte-identical**:

```json
{
    "scripts": {
        "app-check-standards": "vendor/bin/standards-sync sync --check"
    }
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
        "composer.json": {
            "scripts.app-check-standards": [
                "vendor/bin/standards-sync sync --check"
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
        $this->addRule(new ComposerScript(name: 'app-check-standards', commands: [
            'vendor/bin/standards-sync sync --check',
            'vendor/bin/phpstan',
        ]));
    }
});
```

…which reports as: *Runs "vendor/bin/standards-sync sync --check", "vendor/bin/phpstan" in the composer script "app-check-standards", beside any commands the project adds.*

## Every command of a multi-command script gets its own line

Fixture: [`tests/Scenario/Composer/Script/fixtures/adds-a-multi-command-script`](../../../tests/Scenario/Composer/Script/fixtures/adds-a-multi-command-script)

**Before** — `composer.json`:

```json
{
    "name": "acme/project"
}
```

**After:**

```json
{
    "name": "acme/project",
    "scripts": {
        "app-check-standards": [
            "vendor/bin/standards-sync sync --check",
            "vendor/bin/phpstan"
        ]
    }
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
        "composer.json": {
            "scripts.app-check-standards": [
                "vendor/bin/standards-sync sync --check",
                "vendor/bin/phpstan"
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
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-phpstan', '@app-run-tests']));
    }
});
```

…which reports as: *Runs "@app-sync-check", "@app-phpstan", "@app-run-tests" in the composer script "app-checks", beside any commands the project adds.*

## A command the project added is kept where the project put it

Fixture: [`tests/Scenario/Composer/Script/fixtures/keeps-a-command-the-project-added`](../../../tests/Scenario/Composer/Script/fixtures/keeps-a-command-the-project-added)

`composer.json` **stays byte-identical**:

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpstan",
            "@app-psalm",
            "@app-run-tests"
        ]
    }
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-phpstan",
                "@app-run-tests"
            ]
        }
    }
}
```

## A missing leading command is inserted first

Fixture: [`tests/Scenario/Composer/Script/fixtures/inserts-a-missing-leading-command`](../../../tests/Scenario/Composer/Script/fixtures/inserts-a-missing-leading-command)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-checks": [
            "@app-phpstan",
            "@app-run-tests"
        ]
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpstan",
            "@app-run-tests"
        ]
    }
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-phpstan",
                "@app-run-tests"
            ]
        }
    }
}
```

## A missing command is inserted after the one declared before it

Fixture: [`tests/Scenario/Composer/Script/fixtures/inserts-a-missing-command-after-its-predecessor`](../../../tests/Scenario/Composer/Script/fixtures/inserts-a-missing-command-after-its-predecessor)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-psalm",
            "@app-run-tests"
        ]
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpstan",
            "@app-psalm",
            "@app-run-tests"
        ]
    }
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-phpstan",
                "@app-run-tests"
            ]
        }
    }
}
```

## A command the lock records and nobody declares now is retracted

Fixture: [`tests/Scenario/Composer/Script/fixtures/retracts-a-command-no-longer-declared`](../../../tests/Scenario/Composer/Script/fixtures/retracts-a-command-no-longer-declared)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpcs",
            "@app-phpstan",
            "@app-psalm",
            "@app-run-tests"
        ]
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpstan",
            "@app-psalm",
            "@app-run-tests"
        ]
    }
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-phpcs",
                "@app-phpstan",
                "@app-run-tests"
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-phpstan",
                "@app-run-tests"
            ]
        }
    }
}
```

## A retired command is retracted with the arguments the project added to it

Fixture: [`tests/Scenario/Composer/Script/fixtures/retracts-a-retired-command-with-arguments`](../../../tests/Scenario/Composer/Script/fixtures/retracts-a-retired-command-with-arguments)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpcs --report=summary",
            "@app-phpstan",
            "@app-run-tests"
        ]
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpstan",
            "@app-run-tests"
        ]
    }
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-phpcs",
                "@app-phpstan",
                "@app-run-tests"
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-phpstan",
                "@app-run-tests"
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
        $this->addRule(new ComposerScript(name: 'app-phpstan', commands: ['phpstan analyse --no-progress']));
    }
});
```

…which reports as: *Runs "phpstan analyse --no-progress" in the composer script "app-phpstan", beside any commands the project adds.*

## A changed declaration replaces the command it retires

Fixture: [`tests/Scenario/Composer/Script/fixtures/replaces-a-changed-declaration`](../../../tests/Scenario/Composer/Script/fixtures/replaces-a-changed-declaration)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-phpstan": [
            "phpstan analyse"
        ]
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-phpstan": [
            "phpstan analyse --no-progress"
        ]
    }
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
        "composer.json": {
            "scripts.app-phpstan": [
                "phpstan analyse"
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
        "composer.json": {
            "scripts.app-phpstan": [
                "phpstan analyse --no-progress"
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
        $this->addRule(new ComposerScript(name: 'app-phpstan', commands: ['phpstan analyse'], acceptsArguments: true));
    }
});
```

…which reports as: *Runs "phpstan analyse" in the composer script "app-phpstan", beside any commands the project adds. "phpstan analyse" accepts extra arguments.*

## A declaration accepting arguments keeps the project's arguments

Fixture: [`tests/Scenario/Composer/Script/fixtures/keeps-the-projects-arguments`](../../../tests/Scenario/Composer/Script/fixtures/keeps-the-projects-arguments)

`composer.json` **stays byte-identical**:

```json
{
    "scripts": {
        "app-phpstan": [
            "phpstan analyse --memory-limit=1G"
        ]
    }
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
        "composer.json": {
            "scripts.app-phpstan": [
                "phpstan analyse"
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
        $this->addRule(new ComposerScript(name: 'app-phpstan', commands: ['phpstan analyse']));
    }
});
```

…which reports as: *Runs "phpstan analyse" in the composer script "app-phpstan", beside any commands the project adds.*

## A declaration not accepting arguments is inserted beside the project's variant

Fixture: [`tests/Scenario/Composer/Script/fixtures/inserts-the-declared-command-beside-a-variant`](../../../tests/Scenario/Composer/Script/fixtures/inserts-the-declared-command-beside-a-variant)

**Before** — `composer.json`:

```json
{
    "scripts": {
        "app-phpstan": [
            "phpstan analyse --memory-limit=1G"
        ]
    }
}
```

**After:**

```json
{
    "scripts": {
        "app-phpstan": [
            "phpstan analyse",
            "phpstan analyse --memory-limit=1G"
        ]
    }
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
        "composer.json": {
            "scripts.app-phpstan": [
                "phpstan analyse"
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
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests']));
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-run-tests', '@app-lint']));
    }
});
```

…which report as:

- *Runs "@app-sync-check", "@app-run-tests" in the composer script "app-checks", beside any commands the project adds.*
- *Runs "@app-run-tests", "@app-lint" in the composer script "app-checks", beside any commands the project adds.*

## A second declaration of the script adds its commands after the first's, a shared one counting once

Fixture: [`tests/Scenario/Composer/Script/fixtures/merges-a-second-declaration`](../../../tests/Scenario/Composer/Script/fixtures/merges-a-second-declaration)

**Before** — `composer.json`:

```json
{
    "name": "acme/project"
}
```

**After:**

```json
{
    "name": "acme/project",
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-run-tests",
            "@app-lint"
        ]
    }
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
        "composer.json": {
            "scripts.app-checks": [
                "@app-sync-check",
                "@app-run-tests",
                "@app-lint"
            ]
        }
    }
}
```
