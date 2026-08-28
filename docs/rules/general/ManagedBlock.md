<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# ManagedBlock

Places a template as a managed marker block. An absent file becomes just the block; an existing same-label block is replaced in place, whichever rule produced it; a file without the block gains it at the end.

## Block Update

Declared as:

```php
// The fixture is its own package: distributed content in templates/, sitting at the (virtual) consumer root.
$package = new Package(__DIR__, '');

return SyncConfig::create()->withRuleSet(new class($package) extends ComposableRuleSet {
    public function __construct(Package $package)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.editorconfig'),
            label: Label::fromString('test'),
            content: $package->read('.editorconfig'),
        ));
    }
});
```

…which reports as: *Places the managed "test" block in .editorconfig.*

### An existing block is replaced in place, preserving its surroundings

Fixture: [`tests/Scenario/General/ManagedBlock/fixtures/editorconfig/preserves-local`](../../../tests/Scenario/General/ManagedBlock/fixtures/editorconfig/preserves-local)

**Before** — `.editorconfig`:

```ini
top
# >>> test - managed >>>
old
# <<< test <<<
bottom
```

**After:**

```ini
top
# >>> test - managed >>>
root = true
# <<< test <<<
bottom
```

### Already-synced input stays put

Fixture: [`tests/Scenario/General/ManagedBlock/fixtures/editorconfig/idempotent`](../../../tests/Scenario/General/ManagedBlock/fixtures/editorconfig/idempotent)

`.editorconfig` **stays byte-identical**:

```ini
# >>> test - managed >>>
root = true
# <<< test <<<
```

### Hand edits inside the block are overwritten

Fixture: [`tests/Scenario/General/ManagedBlock/fixtures/editorconfig/overwrites-in-block-edits`](../../../tests/Scenario/General/ManagedBlock/fixtures/editorconfig/overwrites-in-block-edits)

**Before** — `.editorconfig`:

```ini
# >>> test - managed >>>
root = false
indent_size = 2
# <<< test <<<
```

**After:**

```ini
# >>> test - managed >>>
root = true
# <<< test <<<
```

## Co Management

Declared as:

```php
$package = new Package(__DIR__, '');

// Two packages co-own one .gitignore under distinct labels; each label is its own block, and adopting the second block keeps the first.
return SyncConfig::create()->withRuleSet(new class($package) extends ComposableRuleSet {
    public function __construct(Package $package)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('ci'),
            content: $package->read('ci.gitignore'),
        ));
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('framework'),
            content: $package->read('framework.gitignore'),
        ));
    }
});
```

…which report as:

- *Places the managed "ci" block in .gitignore.*
- *Places the managed "framework" block in .gitignore.*

### Two labels create two separate blocks in one file

Fixture: [`tests/Scenario/General/ManagedBlock/fixtures/co-management/multi-label`](../../../tests/Scenario/General/ManagedBlock/fixtures/co-management/multi-label)

**Creates** `.gitignore`:

```
# >>> ci - managed >>>
/build/
# <<< ci <<<

# >>> framework - managed >>>
/vendor/
# <<< framework <<<
```

## First Sync

Declared as:

```php
// The fixture is its own package: distributed content in templates/, sitting at the (virtual) consumer root.
$package = new Package(__DIR__, '');

return SyncConfig::create()->withRuleSet(new class($package) extends ComposableRuleSet {
    public function __construct(Package $package)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.editorconfig'),
            label: Label::fromString('test'),
            content: $package->read('.editorconfig'),
        ));
    }
});
```

…which reports as: *Places the managed "test" block in .editorconfig.*

### From scratch creates the block

Fixture: [`tests/Scenario/General/ManagedBlock/fixtures/editorconfig/from-scratch`](../../../tests/Scenario/General/ManagedBlock/fixtures/editorconfig/from-scratch)

**Creates** `.editorconfig`:

```ini
# >>> test - managed >>>
root = true
# <<< test <<<
```

Declared as:

```php
$package = new Package(__DIR__, '');

return SyncConfig::create()->withRuleSet(new class($package) extends ComposableRuleSet {
    public function __construct(Package $package)
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.gitignore'),
            label: Label::fromString('test'),
            content: $package->read('.gitignore'),
        ));
    }
});
```

…which reports as: *Places the managed "test" block in .gitignore.*

### Existing content is kept and the block appended

Fixture: [`tests/Scenario/General/ManagedBlock/fixtures/append-keeps`](../../../tests/Scenario/General/ManagedBlock/fixtures/append-keeps)

**Before** — `.gitignore`:

```
existing
```

**After:**

```
existing

# >>> test - managed >>>
ignored/
# <<< test <<<
```
