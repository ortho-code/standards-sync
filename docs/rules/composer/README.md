<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# Composer

Rules: [ComposerConfigSetting](ComposerConfigSetting.md) · [ComposerRequirement](ComposerRequirement.md) · [ComposerScript](ComposerScript.md)

## Family

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerRequirement(package: 'phpstan/phpstan', constraint: VersionConstraint::fromString('^2.5')));
        $this->addRule(new ComposerScript(name: 'app-check-standards', commands: ['vendor/bin/standards-sync sync --check']));
    }
});
```

…which report as:

- *Requires phpstan/phpstan in the composer manifest (require-dev), no lower than "^2.5".*
- *Runs "vendor/bin/standards-sync sync --check" as the composer script "app-check-standards".*

### Both rules fold into one manifest

Fixture: [`tests/Scenario/Composer/Family/fixtures/from-scratch`](../../../tests/Scenario/Composer/Family/fixtures/from-scratch)

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
    "require-dev": {
        "phpstan/phpstan": "^2.5"
    },
    "scripts": {
        "app-check-standards": [
            "vendor/bin/standards-sync sync --check"
        ]
    }
}
```
