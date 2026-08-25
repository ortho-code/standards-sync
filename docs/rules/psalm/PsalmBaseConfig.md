<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# PsalmBaseConfig

Seeds a project that has no Psalm config with the org's template, verbatim, and never edits an existing config. For a tool with no import tier the template is one-shot — only values that also have their own rule stay enforced: the template bootstraps, rules converge. Declared first in a rule set it decides the created base for the whole family — later rules receive the template instead of the engine skeleton.

Declared as:

```php
// The template is inlined here; a real org package reads it from templates/ through Package.
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
    }
});
```

…which reports as: *Seeds a missing Psalm config with the org base config.*

## No psalm config: the template is seeded verbatim

Fixture: [`tests/Scenario/Psalm/BaseConfig/fixtures/seeds-a-missing-config`](../../../tests/Scenario/Psalm/BaseConfig/fixtures/seeds-a-missing-config)

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

## An existing config is never edited, even one drifted from the template

Fixture: [`tests/Scenario/Psalm/BaseConfig/fixtures/leaves-an-existing-config`](../../../tests/Scenario/Psalm/BaseConfig/fixtures/leaves-an-existing-config)

`psalm.xml` **stays byte-identical**:

```xml
<?xml version="1.0"?>
<psalm errorLevel="8">
    <projectFiles>
        <directory name="app" />
    </projectFiles>
</psalm>
```
