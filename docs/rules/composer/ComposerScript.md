<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# ComposerScript

Owns a named composer script: the declared commands are what the script runs, and a deviating or missing script is rewritten. Declarations of one script combine in declaration order, each adding its commands after the earlier ones'; a command declared twice counts once. A project needing extra steps declares a script of its own and calls this one through composer's "@name" reference, so the owned entry point stays exactly what the standards say. A root without a manifest is not a composer project, so the rule abstains rather than creating one.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerScript(name: 'app-check-standards', commands: ['vendor/bin/standards-sync sync --check']));
    }
});
```

…which reports as: *Runs "vendor/bin/standards-sync sync --check" as the composer script "app-check-standards".*

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

## A script running something else is rewritten, its neighbours untouched

Fixture: [`tests/Scenario/Composer/Script/fixtures/rewrites-a-drifted-script`](../../../tests/Scenario/Composer/Script/fixtures/rewrites-a-drifted-script)

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
            "vendor/bin/standards-sync sync --check"
        ],
        "app-run-tests": [
            "vendor/bin/phpunit"
        ]
    }
}
```

## A script already running the declared commands is never touched

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

## A script written as a single string becomes the list form

Fixture: [`tests/Scenario/Composer/Script/fixtures/replaces-a-string-script`](../../../tests/Scenario/Composer/Script/fixtures/replaces-a-string-script)

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
            "vendor/bin/standards-sync sync --check"
        ]
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

…which reports as: *Runs "vendor/bin/standards-sync sync --check", "vendor/bin/phpstan" as the composer script "app-check-standards".*

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

- *Runs "@app-sync-check", "@app-run-tests" as the composer script "app-checks".*
- *Runs "@app-run-tests", "@app-lint" as the composer script "app-checks".*

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
