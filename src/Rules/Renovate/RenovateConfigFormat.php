<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Renovate;

/** The config grammars the renovate family writes; an org picks one as the shape a repo without any config gains. */
enum RenovateConfigFormat
{
    case Json;
    case Json5;
}
