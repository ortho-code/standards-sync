<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Plan;

enum ChangeKind
{
    case InSync;
    case Create;
    case Update;
}
