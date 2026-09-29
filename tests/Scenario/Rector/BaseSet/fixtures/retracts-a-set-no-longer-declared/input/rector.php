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
