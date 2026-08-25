<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# ComposerConfigSetting

Pins one setting under the composer manifest's config key: a deviating value is rewritten on every sync, so a project cannot override it. Composer's config values have no ordering to floor, so this enforces the declared value outright — what a project may choose belongs outside the standard. A root without a manifest is not a composer project, so the rule abstains rather than creating one.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerConfigSetting(setting: 'sort-packages', value: true));
    }
});
```

…which reports as: *Pins the composer config setting "sort-packages" to true.*

## A manifest without a config key gains the section

Fixture: [`tests/Scenario/Composer/ConfigSetting/fixtures/creates-the-config-section`](../../../tests/Scenario/Composer/ConfigSetting/fixtures/creates-the-config-section)

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
    "config": {
        "sort-packages": true
    }
}
```

## The setting joins the settings already there

Fixture: [`tests/Scenario/Composer/ConfigSetting/fixtures/adds-to-an-existing-config`](../../../tests/Scenario/Composer/ConfigSetting/fixtures/adds-to-an-existing-config)

**Before** — `composer.json`:

```json
{
    "config": {
        "optimize-autoloader": true
    }
}
```

**After:**

```json
{
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
```

## A value the project changed is written back

Fixture: [`tests/Scenario/Composer/ConfigSetting/fixtures/rewrites-a-deviating-value`](../../../tests/Scenario/Composer/ConfigSetting/fixtures/rewrites-a-deviating-value)

**Before** — `composer.json`:

```json
{
    "config": {
        "sort-packages": false
    }
}
```

**After:**

```json
{
    "config": {
        "sort-packages": true
    }
}
```

## A value already matching is never touched

Fixture: [`tests/Scenario/Composer/ConfigSetting/fixtures/leaves-a-matching-value`](../../../tests/Scenario/Composer/ConfigSetting/fixtures/leaves-a-matching-value)

`composer.json` **stays byte-identical**:

```json
{
    "config": {
        "sort-packages": true
    }
}
```

## A config object written on one line keeps that layout

Fixture: [`tests/Scenario/Composer/ConfigSetting/fixtures/keeps-a-one-line-config-object`](../../../tests/Scenario/Composer/ConfigSetting/fixtures/keeps-a-one-line-config-object)

**Before** — `composer.json`:

```json
{
    "config": {"optimize-autoloader": true}
}
```

**After:**

```json
{
    "config": {"optimize-autoloader": true, "sort-packages": true}
}
```

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerConfigSetting(setting: 'preferred-install.*', value: 'dist'));
    }
});
```

…which reports as: *Pins the composer config setting "preferred-install.*" to "dist".*

## A dotted setting pins a value nested under another

Fixture: [`tests/Scenario/Composer/ConfigSetting/fixtures/pins-a-nested-setting`](../../../tests/Scenario/Composer/ConfigSetting/fixtures/pins-a-nested-setting)

**Before** — `composer.json`:

```json
{
    "config": {
        "sort-packages": true
    }
}
```

**After:**

```json
{
    "config": {
        "sort-packages": true,
        "preferred-install": {
            "*": "dist"
        }
    }
}
```
