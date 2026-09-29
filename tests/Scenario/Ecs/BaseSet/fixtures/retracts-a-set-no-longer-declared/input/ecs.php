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
