<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/rector.php',
    ]);
