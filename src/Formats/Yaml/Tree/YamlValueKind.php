<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

/** What a mapping value or a sequence item holds. */
enum YamlValueKind
{
    case Mapping;
    case Sequence;
    /** No value at all: a key or a dash with nothing after it. */
    case Empty;
    case Plain;
    case Quoted;
    /** A literal or folded block scalar, opened by `|` or `>`. */
    case Block;
    /** A flow collection, `[…]` or `{…}`. */
    case Flow;
}
