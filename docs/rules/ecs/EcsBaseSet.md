<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# EcsBaseSet

Ensures the ECS config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched. The set is a path relative to the consumer project root; the config entry (`__DIR__ . '/…'`) is rendered from it, so the org author passes data, not PHP. The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent. A missing entry takes the place of one no standard declares any more, and goes after the last entry otherwise; a config without withSets() gains the call at the end of the chain; a project without an ECS config gets one created, holding just the imports. Declarations of base sets combine in declaration order, a set declared twice counting once; a set registered at an earlier sync and declared by nobody now is retracted, and every other set is the project's and stays.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new EcsBaseSet(set: 'vendor/acme/standards/config/ecs.php'));
    }
});
```

…which reports as: *Ensures the ECS config registers vendor/acme/standards/config/ecs.php in withSets().*

## A project without a config gets one created

Fixture: [`tests/Scenario/Ecs/BaseSet/fixtures/from-scratch`](../../../tests/Scenario/Ecs/BaseSet/fixtures/from-scratch)

**Creates** `ecs.php`:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/ecs.php'"
            ]
        }
    }
}
```

## An existing withSets array gains the entry

Fixture: [`tests/Scenario/Ecs/BaseSet/fixtures/insert-into-sets`](../../../tests/Scenario/Ecs/BaseSet/fixtures/insert-into-sets)

**Before** — `ecs.php`:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        SetList::PSR_12,
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        SetList::PSR_12,
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/ecs.php'"
            ]
        }
    }
}
```

## A chain without withSets gains the call

Fixture: [`tests/Scenario/Ecs/BaseSet/fixtures/appends-sets-call`](../../../tests/Scenario/Ecs/BaseSet/fixtures/appends-sets-call)

**Before** — `ecs.php`:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPreparedSets(psr12: true);
```

**After:**

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPreparedSets(psr12: true)
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/ecs.php'"
            ]
        }
    }
}
```

## An already-registered import stays untouched

Fixture: [`tests/Scenario/Ecs/BaseSet/fixtures/already-imported`](../../../tests/Scenario/Ecs/BaseSet/fixtures/already-imported)

`ecs.php` **stays byte-identical**:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/ecs.php'"
            ]
        }
    }
}
```

## A moved set replaces its predecessor in place, keeping the line's comment

Fixture: [`tests/Scenario/Ecs/BaseSet/fixtures/replaces-a-moved-set-in-place`](../../../tests/Scenario/Ecs/BaseSet/fixtures/replaces-a-moved-set-in-place)

**Before** — `ecs.php`:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/ecs.php', // the org set
        __DIR__ . '/ecs-local.php',
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php', // the org set
        __DIR__ . '/ecs-local.php',
    ]);
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/ecs.php'"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/ecs.php'"
            ]
        }
    }
}
```

## A set no standard declares any more is retracted, and the project's set stays

Fixture: [`tests/Scenario/Ecs/BaseSet/fixtures/retracts-a-set-no-longer-declared`](../../../tests/Scenario/Ecs/BaseSet/fixtures/retracts-a-set-no-longer-declared)

**Before** — `ecs.php`:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
        __DIR__ . '/vendor/acme/standards/config/strict.php',
        SetList::PSR_12,
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
        SetList::PSR_12,
    ]);
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/ecs.php'",
                "__DIR__ . '/vendor/acme/standards/config/strict.php'"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        "ecs.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/ecs.php'"
            ]
        }
    }
}
```
