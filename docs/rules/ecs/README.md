<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# Ecs

Rules: [EcsBaseSet](EcsBaseSet.md)

## Family

Declared as:

```php
// Two org tiers: the second tier includes the base tier first, so the base entry is created first and the second tier's entry lands after it.
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->include(new class extends ComposableRuleSet {
            public function __construct()
            {
                $this->addRule(new EcsBaseSet(set: 'vendor/acme/standards/config/ecs.php'));
            }
        });
        $this->addRule(new EcsBaseSet(set: 'vendor/acme/platform-standards/config/ecs.php'));
    }
});
```

…which report as:

- *Ensures the ECS config registers vendor/acme/standards/config/ecs.php in withSets().*
- *Ensures the ECS config registers vendor/acme/platform-standards/config/ecs.php in withSets().*

### Two tiers register both sets in declaration order

Fixture: [`tests/Scenario/Ecs/Family/fixtures/from-scratch`](../../../tests/Scenario/Ecs/Family/fixtures/from-scratch)

**Creates** `ecs.php`:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
        __DIR__ . '/vendor/acme/platform-standards/config/ecs.php',
    ]);
```
