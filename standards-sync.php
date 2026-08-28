<?php

declare(strict_types=1);

use OrthoCode\CodingStandards\PackageStandard;
use OrthoCode\StandardsSync\Core\Config\SyncConfig;

return SyncConfig::create()->withRuleSet(new PackageStandard());
