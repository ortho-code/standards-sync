<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\Rector\BaseSet;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** The Rector import as targeted edits: the withSets entry rides along with whatever the config already has. */
#[CoversNothing]
final class RectorBaseSetTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        yield 'a project without a config gets one created' => ['from-scratch', 'standards-sync.php'];
        yield 'an existing withSets array gains the entry' => ['insert-into-sets', 'standards-sync.php'];
        yield 'a chain without withSets gains the call' => ['appends-sets-call', 'standards-sync.php'];
        yield 'an already-registered import stays untouched' => ['already-imported', 'standards-sync.php'];
    }
}
