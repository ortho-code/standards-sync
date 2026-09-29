<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php', // the org set
        __DIR__ . '/ecs-local.php',
    ]);
