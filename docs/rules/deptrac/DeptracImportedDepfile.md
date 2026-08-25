<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# DeptracImportedDepfile

Ensures the deptrac config imports a given depfile, as a targeted edit that leaves the rest of the file untouched. An existing block-form imports: section gains the entry; a config without the section gains it at the top; a project without a deptrac config gets one created, holding just the import.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/standards/deptrac.yaml'));
    }
});
```

…which reports as: *Ensures the deptrac config imports "vendor/acme/standards/deptrac.yaml".*

## A project without a config gets one created

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/from-scratch`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/from-scratch)

**Creates** `deptrac.yaml`:

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml
```

## An existing imports section gains the depfile

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/insert-into-section`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/insert-into-section)

**Before** — `deptrac.yaml`:

```yaml
imports:
  - local/architecture.yaml

deptrac:
  paths:
    - ./src
```

**After:**

```yaml
imports:
  - local/architecture.yaml
  - vendor/acme/standards/deptrac.yaml

deptrac:
  paths:
    - ./src
```

## A config without imports gains the section at the top

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/creates-section`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/creates-section)

**Before** — `deptrac.yaml`:

```yaml
deptrac:
  paths:
    - ./src
```

**After:**

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml

deptrac:
  paths:
    - ./src
```

## An already-imported config stays put

Fixture: [`tests/Scenario/Deptrac/ImportedDepfile/fixtures/already-imported`](../../../tests/Scenario/Deptrac/ImportedDepfile/fixtures/already-imported)

`deptrac.yaml` **stays byte-identical**:

```yaml
imports:
  - vendor/acme/standards/deptrac.yaml

deptrac:
  paths:
    - ./src
```
