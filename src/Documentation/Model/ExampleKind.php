<?php

declare(strict_types=1);

namespace StandardsSync\Documentation\Model;

/** How a scenario's expected tree relates to its input tree for one file. */
enum ExampleKind
{
    case Changed;
    case Created;
    case Unchanged;
    case Removed;
}
