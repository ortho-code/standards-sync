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
