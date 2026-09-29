<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PhpStan

Rules: [PhpStanIncludedRuleset](PhpStanIncludedRuleset.md) · [PhpStanMinLevel](PhpStanMinLevel.md) · [PhpStanPinnedValues](PhpStanPinnedValues.md)

## Family

Declared as:

```php
// The import rule is declared first, so the floor rule receives the config the import just created.
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

### A created config gains the import and the floor

Fixture: [`tests/Scenario/PhpStan/Family/fixtures/from-scratch`](../../../tests/Scenario/PhpStan/Family/fixtures/from-scratch)

**Creates** `phpstan.neon`:

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
        "phpstan.neon": {
            "includes": [
                "vendor/acme/standards/phpstan.neon"
            ]
        }
    }
}
```
