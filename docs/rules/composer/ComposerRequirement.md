<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# ComposerRequirement

Requires a package in the composer manifest exactly once, in a section that covers the declared need, at or above a minimum version. A constraint reaching below the minimum is raised to it alternative-wise, so a project allowing a newer major keeps it; a package required where the declared need is not covered moves, carrying a constraint that already meets the minimum. A root without a manifest is not a composer project, so the rule abstains rather than creating one.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerRequirement(package: 'phpstan/phpstan', constraint: VersionConstraint::fromString('^2.5')));
    }
});
```

…which reports as: *Requires phpstan/phpstan in the composer manifest (require-dev), no lower than "^2.5".*

## The requirement is added to an existing require-dev

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/adds-to-require-dev`](../../../tests/Scenario/Composer/Requirement/fixtures/adds-to-require-dev)

**Before** — `composer.json`:

```json
{
    "name": "acme/project",
    "require-dev": {
        "rector/rector": "^2.5"
    }
}
```

**After:**

```json
{
    "name": "acme/project",
    "require-dev": {
        "rector/rector": "^2.5",
        "phpstan/phpstan": "^2.5"
    }
}
```

## A manifest without require-dev gains the section

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/creates-the-require-dev-section`](../../../tests/Scenario/Composer/Requirement/fixtures/creates-the-require-dev-section)

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
    }
}
```

## A constraint that meets the minimum is never touched

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/leaves-a-meeting-constraint`](../../../tests/Scenario/Composer/Requirement/fixtures/leaves-a-meeting-constraint)

`composer.json` **stays byte-identical**:

```json
{
    "name": "acme/project",
    "require-dev": {
        "phpstan/phpstan": "^2.5.7"
    }
}
```

## A constraint below the minimum is raised to it

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/raises-a-constraint-below-the-minimum`](../../../tests/Scenario/Composer/Requirement/fixtures/raises-a-constraint-below-the-minimum)

**Before** — `composer.json`:

```json
{
    "require-dev": {
        "phpstan/phpstan": "^2.0"
    }
}
```

**After:**

```json
{
    "require-dev": {
        "phpstan/phpstan": "^2.5"
    }
}
```

## A branch constraint meets no minimum and is raised

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/raises-a-branch-constraint`](../../../tests/Scenario/Composer/Requirement/fixtures/raises-a-branch-constraint)

**Before** — `composer.json`:

```json
{
    "require-dev": {
        "phpstan/phpstan": "dev-main"
    }
}
```

**After:**

```json
{
    "require-dev": {
        "phpstan/phpstan": "^2.5"
    }
}
```

## Raising keeps an allowed newer major

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/keeps-a-newer-major-when-raising`](../../../tests/Scenario/Composer/Requirement/fixtures/keeps-a-newer-major-when-raising)

**Before** — `composer.json`:

```json
{
    "require-dev": {
        "phpstan/phpstan": "^1.0 || ^3.0"
    }
}
```

**After:**

```json
{
    "require-dev": {
        "phpstan/phpstan": "^2.5 || ^3.0"
    }
}
```

## A runtime requirement already covers a development one

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/leaves-a-runtime-requirement-alone`](../../../tests/Scenario/Composer/Requirement/fixtures/leaves-a-runtime-requirement-alone)

`composer.json` **stays byte-identical**:

```json
{
    "require": {
        "phpstan/phpstan": "^2.5"
    }
}
```

## A package required in both sections loses the redundant one

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/drops-the-redundant-duplicate`](../../../tests/Scenario/Composer/Requirement/fixtures/drops-the-redundant-duplicate)

**Before** — `composer.json`:

```json
{
    "require": {
        "phpstan/phpstan": "^2.5"
    },
    "require-dev": {
        "phpstan/phpstan": "^2.0",
        "rector/rector": "^2.5"
    }
}
```

**After:**

```json
{
    "require": {
        "phpstan/phpstan": "^2.5"
    },
    "require-dev": {
        "rector/rector": "^2.5"
    }
}
```

## The manifest keeps its own indentation

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/keeps-the-manifests-own-indentation`](../../../tests/Scenario/Composer/Requirement/fixtures/keeps-the-manifests-own-indentation)

**Before** — `composer.json`:

```json
{
  "name": "acme/project",
  "require-dev": {
    "rector/rector": "^2.5"
  }
}
```

**After:**

```json
{
  "name": "acme/project",
  "require-dev": {
    "rector/rector": "^2.5",
    "phpstan/phpstan": "^2.5"
  }
}
```

## A package key written with an escaped slash is the same member

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/matches-an-escaped-package-key`](../../../tests/Scenario/Composer/Requirement/fixtures/matches-an-escaped-package-key)

**Before** — `composer.json`:

```json
{
    "require-dev": {
        "phpstan\/phpstan": "^2.0"
    }
}
```

**After:**

```json
{
    "require-dev": {
        "phpstan\/phpstan": "^2.5"
    }
}
```

## A root that is not a composer project gets no manifest

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/no-manifest-creates-nothing`](../../../tests/Scenario/Composer/Requirement/fixtures/no-manifest-creates-nothing)

`README.md` **stays byte-identical**:

```
A root that is not a composer project.
```

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new ComposerRequirement(
            package: 'acme/runtime-support',
            constraint: VersionConstraint::fromString('^1.2'),
            type: RequirementType::Runtime,
        ));
    }
});
```

…which reports as: *Requires acme/runtime-support in the composer manifest (require), no lower than "^1.2".*

## A runtime requirement moves out of require-dev

Fixture: [`tests/Scenario/Composer/Requirement/fixtures/moves-a-requirement-into-require`](../../../tests/Scenario/Composer/Requirement/fixtures/moves-a-requirement-into-require)

**Before** — `composer.json`:

```json
{
    "require-dev": {
        "acme/runtime-support": "^1.4",
        "rector/rector": "^2.5"
    }
}
```

**After:**

```json
{
    "require-dev": {
        "rector/rector": "^2.5"
    },
    "require": {
        "acme/runtime-support": "^1.4"
    }
}
```
