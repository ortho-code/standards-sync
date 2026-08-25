<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpStanPinnedValues

Pins exact values in the PHPStan config: deviations are rewritten on every sync, so consumers cannot override them. Defaults a project may override belong in the imported shared ruleset instead — pin only what must not be overridden. A pin always writes: a project without a PHPStan config gets one created holding the pinned values.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'treatPhpDocTypesAsCertain' => false,
                'reportUnmatchedIgnoredErrors' => true,
            ],
        ])));
    }
});
```

…which reports as: *Pins the PHPStan config values: parameters.treatPhpDocTypesAsCertain, parameters.reportUnmatchedIgnoredErrors.*

## Deviating values are rewritten and missing ones added

Fixture: [`tests/Scenario/PhpStan/PinnedValues/fixtures/pins-existing-config`](../../../tests/Scenario/PhpStan/PinnedValues/fixtures/pins-existing-config)

**Before** — `phpstan.neon`:

```neon
parameters:
	level: 6
	treatPhpDocTypesAsCertain: true
```

**After:**

```neon
parameters:
	reportUnmatchedIgnoredErrors: true
	level: 6
	treatPhpDocTypesAsCertain: false
```

## A project without a config gets one holding the pins

Fixture: [`tests/Scenario/PhpStan/PinnedValues/fixtures/creates-the-config`](../../../tests/Scenario/PhpStan/PinnedValues/fixtures/creates-the-config)

**Creates** `phpstan.neon`:

```neon
parameters:
	reportUnmatchedIgnoredErrors: true
	treatPhpDocTypesAsCertain: false
```

## An already-pinned config stays put

Fixture: [`tests/Scenario/PhpStan/PinnedValues/fixtures/already-pinned`](../../../tests/Scenario/PhpStan/PinnedValues/fixtures/already-pinned)

`phpstan.neon` **stays byte-identical**:

```neon
parameters:
	treatPhpDocTypesAsCertain: false
	reportUnmatchedIgnoredErrors: true
```

Declared as:

```php
// A pinned value holding a '#': the hash is content, not a comment boundary — the quote-aware regression case.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'editorUrl' => 'https://editor.example/#open?file=%file%',
            ],
        ])));
    }
});
```

…which reports as: *Pins the PHPStan config values: parameters.editorUrl.*

## A hash inside a pinned value is content, not a comment

Fixture: [`tests/Scenario/PhpStan/PinnedValues/fixtures/pins-a-value-holding-a-hash`](../../../tests/Scenario/PhpStan/PinnedValues/fixtures/pins-a-value-holding-a-hash)

**Before** — `phpstan.neon`:

```neon
parameters:
	editorUrl: 'https://old.example/#x' # where errors open
```

**After:**

```neon
parameters:
	editorUrl: 'https://editor.example/#open?file=%file%' # where errors open
```
