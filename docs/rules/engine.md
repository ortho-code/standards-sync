<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# Engine behaviours

## List Contributions

Declared as:

```php
$tier = new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests']));
    }
};

$framework = new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-lint']));
    }
};

return SyncConfig::create()->withRuleSet($tier)->withRuleSet($framework);
```

…which report as:

- *Runs "@app-sync-check", "@app-run-tests" in the composer script "app-checks", beside any commands the project adds.*
- *Runs "@app-lint" in the composer script "app-checks", beside any commands the project adds.*

### A standard declared after the tier adds its commands after the tier's

Fixture: [`tests/Scenario/Engine/ListContributions/fixtures/tier-declared-first`](../../tests/Scenario/Engine/ListContributions/fixtures/tier-declared-first)

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

Declared as:

```php
$tier = new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests']));
    }
};

$framework = new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-lint']));
    }
};

return SyncConfig::create()->withRuleSet($framework)->withRuleSet($tier);
```

…which report as:

- *Runs "@app-lint" in the composer script "app-checks", beside any commands the project adds.*
- *Runs "@app-sync-check", "@app-run-tests" in the composer script "app-checks", beside any commands the project adds.*

### A standard declared before the tier puts its commands first

Fixture: [`tests/Scenario/Engine/ListContributions/fixtures/tier-declared-last`](../../tests/Scenario/Engine/ListContributions/fixtures/tier-declared-last)

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
            "@app-lint",
            "@app-sync-check",
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
                "@app-lint",
                "@app-sync-check",
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
        $this->addRule(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests']));
    }
});
```

…which reports as: *Runs "@app-sync-check", "@app-run-tests" in the composer script "app-checks", beside any commands the project adds.*

### Without a lock nothing is retracted, and the first sync writes one

Fixture: [`tests/Scenario/Engine/ListContributions/fixtures/without-a-lock-retracts-nothing`](../../tests/Scenario/Engine/ListContributions/fixtures/without-a-lock-retracts-nothing)

`composer.json` **stays byte-identical**:

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-phpcs",
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
                "@app-run-tests"
            ]
        }
    }
}
```

### An in-sync manifest beside a stale lock drifts in the lock alone

Fixture: [`tests/Scenario/Engine/ListContributions/fixtures/a-stale-lock-drifts-alone`](../../tests/Scenario/Engine/ListContributions/fixtures/a-stale-lock-drifts-alone)

`composer.json` **stays byte-identical**:

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
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
                "@app-run-tests"
            ]
        }
    }
}
```

### A list no standard declares any more drops out of the lock and stays in the file

Fixture: [`tests/Scenario/Engine/ListContributions/fixtures/a-list-no-standard-declares-stays`](../../tests/Scenario/Engine/ListContributions/fixtures/a-list-no-standard-declares-stays)

`composer.json` **stays byte-identical**:

```json
{
    "scripts": {
        "app-checks": [
            "@app-sync-check",
            "@app-run-tests"
        ],
        "app-phpcs": [
            "phpcs -p -s"
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
                "@app-run-tests"
            ],
            "scripts.app-phpcs": [
                "phpcs -p -s"
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
                "@app-run-tests"
            ]
        }
    }
}
```

### An absent manifest records nothing, so no lock is created

Fixture: [`tests/Scenario/Engine/ListContributions/fixtures/an-absent-manifest-creates-no-lock`](../../tests/Scenario/Engine/ListContributions/fixtures/an-absent-manifest-creates-no-lock)

`README.md` **stays byte-identical**:

```
# acme/project
```

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('acme'),
            content: '/vendor/',
        ));
    }
});
```

…which reports as: *Places the managed "acme" block in .gitignore.*

### A config without list-contributing rules creates no lock

Fixture: [`tests/Scenario/Engine/ListContributions/fixtures/no-contributing-rule-creates-no-lock`](../../tests/Scenario/Engine/ListContributions/fixtures/no-contributing-rule-creates-no-lock)

**Before** — `.gitignore`:

```
/.idea/
```

**After:**

```
/.idea/

# >>> acme - managed >>>
/vendor/
# <<< acme <<<
```

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

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon.dist": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
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

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "phpstan.neon.dist": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
```
