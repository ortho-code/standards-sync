<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# RectorBaseSet

Ensures the Rector config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched. The set is a path relative to the consumer project root; the config entry (`__DIR__ . '/…'`) is rendered from it, so the org author passes data, not PHP. The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent. A missing entry takes the place of one no standard declares any more, and goes after the last entry otherwise; a config without withSets() gains the call at the end of the chain; a project without a Rector config gets one created, holding just the imports. Declarations of base sets combine in declaration order, a set declared twice counting once; a set registered at an earlier sync and declared by nobody now is retracted, and every other set is the project's and stays.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new RectorBaseSet(set: 'vendor/acme/standards/config/rector.php'));
    }
});
```

…which reports as: *Ensures the Rector config registers vendor/acme/standards/config/rector.php in withSets().*

## A project without a config gets one created

Fixture: [`tests/Scenario/Rector/BaseSet/fixtures/from-scratch`](../../../tests/Scenario/Rector/BaseSet/fixtures/from-scratch)

**Creates** `rector.php`:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/rector.php',
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'"
            ]
        }
    }
}
```

## An existing withSets array gains the entry

Fixture: [`tests/Scenario/Rector/BaseSet/fixtures/insert-into-sets`](../../../tests/Scenario/Rector/BaseSet/fixtures/insert-into-sets)

**Before** — `rector.php`:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        SetList::DEAD_CODE,
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        SetList::DEAD_CODE,
        __DIR__ . '/vendor/acme/standards/config/rector.php',
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'"
            ]
        }
    }
}
```

## A chain without withSets gains the call

Fixture: [`tests/Scenario/Rector/BaseSet/fixtures/appends-sets-call`](../../../tests/Scenario/Rector/BaseSet/fixtures/appends-sets-call)

**Before** — `rector.php`:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/rector.php',
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'"
            ]
        }
    }
}
```

## An already-registered import stays untouched

Fixture: [`tests/Scenario/Rector/BaseSet/fixtures/already-imported`](../../../tests/Scenario/Rector/BaseSet/fixtures/already-imported)

`rector.php` **stays byte-identical**:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/rector.php',
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'"
            ]
        }
    }
}
```

## A moved set replaces its predecessor in place, keeping the line's comment

Fixture: [`tests/Scenario/Rector/BaseSet/fixtures/replaces-a-moved-set-in-place`](../../../tests/Scenario/Rector/BaseSet/fixtures/replaces-a-moved-set-in-place)

**Before** — `rector.php`:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/rector.php', // the org set
        __DIR__ . '/rector-local.php',
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/rector.php', // the org set
        __DIR__ . '/rector-local.php',
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/rector.php'"
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'"
            ]
        }
    }
}
```

## A set no standard declares any more is retracted, and the project's set stays

Fixture: [`tests/Scenario/Rector/BaseSet/fixtures/retracts-a-set-no-longer-declared`](../../../tests/Scenario/Rector/BaseSet/fixtures/retracts-a-set-no-longer-declared)

**Before** — `rector.php`:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/rector.php',
        __DIR__ . '/vendor/acme/standards/config/strict.php',
        SetList::DEAD_CODE,
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/rector.php',
        SetList::DEAD_CODE,
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'",
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'"
            ]
        }
    }
}
```

Declared as:

```php
// A framework standard declared beside the tier adds its own set to the tier's withSets().
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RectorBaseSet(set: 'vendor/acme/standards/config/rector.php'));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new RectorBaseSet(set: 'vendor/acme/framework/config/rector.php'));
        }
    });
```

…which report as:

- *Ensures the Rector config registers vendor/acme/standards/config/rector.php in withSets().*
- *Ensures the Rector config registers vendor/acme/framework/config/rector.php in withSets().*

## A second declaration adds its set after the first

Fixture: [`tests/Scenario/Rector/BaseSet/fixtures/merges-a-second-declaration`](../../../tests/Scenario/Rector/BaseSet/fixtures/merges-a-second-declaration)

**Before** — `rector.php`:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withSets([
        SetList::DEAD_CODE,
    ]);
```

**After:**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withSets([
        SetList::DEAD_CODE,
        __DIR__ . '/vendor/acme/standards/config/rector.php',
        __DIR__ . '/vendor/acme/framework/config/rector.php',
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
        "rector.php": {
            "withSets": [
                "__DIR__ . '/vendor/acme/standards/config/rector.php'",
                "__DIR__ . '/vendor/acme/framework/config/rector.php'"
            ]
        }
    }
}
```
