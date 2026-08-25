<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# EcsBaseSet

Ensures the ECS config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched. The set is a path relative to the consumer project root; the config entry (`__DIR__ . '/…'`) is rendered from it, so the org author passes data, not PHP. The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent. A config without withSets() gains the call at the end of the chain; a project without an ECS config gets one created, holding just the import.

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
