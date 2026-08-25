<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Scenario\General\ManagedBlock;

use StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class FirstSyncTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $editorconfig = 'editorconfig/standards-sync.php';

        yield 'from scratch creates the block' => ['editorconfig/from-scratch', $editorconfig];
        yield 'existing content is kept and the block appended' => ['append-keeps', null];
    }
}
