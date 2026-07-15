<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class BlockUpdateTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $editorconfig = 'editorconfig/standards-sync.php';

        yield 'an existing block is replaced in place, preserving its surroundings' => ['editorconfig/preserves-local', $editorconfig];
        yield 'already-synced input stays put' => ['editorconfig/idempotent', $editorconfig];
        yield 'hand edits inside the block are overwritten' => ['editorconfig/overwrites-in-block-edits', $editorconfig];
    }
}
