<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# RectorBaseSet

Ensures the Rector config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched. The set is a path relative to the consumer project root; the config entry (`__DIR__ . '/…'`) is rendered from it, so the org author passes data, not PHP. The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent. A config without withSets() gains the call at the end of the chain; a project without a Rector config gets one created, holding just the import.

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
