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

- *Runs "@app-sync-check", "@app-run-tests" as the composer script "app-checks".*
- *Runs "@app-lint" as the composer script "app-checks".*

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

- *Runs "@app-lint" as the composer script "app-checks".*
- *Runs "@app-sync-check", "@app-run-tests" as the composer script "app-checks".*

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
