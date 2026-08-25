<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# Psalm

Rules: [PsalmBaseConfig](PsalmBaseConfig.md) · [PsalmLoosestErrorLevel](PsalmLoosestErrorLevel.md)

## Family

Declared as:

```php
// The base config is declared first: the first rule to meet the absent config decides the created base — the org template, not the engine skeleton.
// The blank line before the closing marker keeps the trailing line break every file ends with.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new PsalmBaseConfig(config: <<<'XML'
            <?xml version="1.0"?>
            <psalm
                errorLevel="2"
                resolveFromConfigFile="true"
                xmlns="https://getpsalm.org/schema/config"
            >
                <projectFiles>
                    <directory name="src" />
                    <ignoreFiles>
                        <directory name="vendor" />
                    </ignoreFiles>
                </projectFiles>
            </psalm>

            XML));
        $this->addRule(new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(4)));
    }
});
```

…which report as:

- *Seeds a missing Psalm config with the org base config.*
- *Keeps the Psalm error level at or below 4 (lower is stricter).*

### No psalm config: the template declared first wins over the skeleton, its compliant level untouched

Fixture: [`tests/Scenario/Psalm/Family/fixtures/from-scratch`](../../../tests/Scenario/Psalm/Family/fixtures/from-scratch)

**Creates** `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm
    errorLevel="2"
    resolveFromConfigFile="true"
    xmlns="https://getpsalm.org/schema/config"
>
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
```

### An existing config is never reseeded, only converged

Fixture: [`tests/Scenario/Psalm/Family/fixtures/lowers-inside-an-existing-config`](../../../tests/Scenario/Psalm/Family/fixtures/lowers-inside-an-existing-config)

**Before** — `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm
    errorLevel="8"
    resolveFromConfigFile="true"
>
    <!-- the project's own note survives the sync -->
    <projectFiles>
        <directory name="app" />
    </projectFiles>
</psalm>
```

**After:**

```xml
<?xml version="1.0"?>
<psalm
    errorLevel="4"
    resolveFromConfigFile="true"
>
    <!-- the project's own note survives the sync -->
    <projectFiles>
        <directory name="app" />
    </projectFiles>
</psalm>
```
