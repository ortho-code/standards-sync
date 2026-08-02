<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
        __DIR__ . '/vendor/acme/platform-standards/config/ecs.php',
    ]);
