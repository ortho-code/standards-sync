<?php

declare(strict_types=1);

namespace StandardsSync\Core\Config;

use StandardsSync\Core\Filesystem\Path;
use RuntimeException;

/** Loads a standards-sync.php and asserts it returns a SyncConfig. */
final class ConfigLoader
{
    public function loadFrom(Path $path): SyncConfig
    {
        $location = $path->value();

        if (!is_file($location)) {
            throw new RuntimeException(sprintf('Config file not found: "%s".', $location));
        }

        $config = require $location;

        if (!$config instanceof SyncConfig) {
            throw new RuntimeException(sprintf(
                'Config file "%s" must return a %s, got %s.',
                $location,
                SyncConfig::class,
                get_debug_type($config),
            ));
        }

        return $config;
    }
}
