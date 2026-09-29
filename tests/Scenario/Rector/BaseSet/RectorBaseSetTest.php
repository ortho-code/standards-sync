<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Rector\BaseSet;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
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
        yield 'a moved set replaces its predecessor in place, keeping the line\'s comment' => ['replaces-a-moved-set-in-place', 'standards-sync.php'];
        yield 'a set no standard declares any more is retracted, and the project\'s set stays' => ['retracts-a-set-no-longer-declared', 'standards-sync.php'];
        yield 'a second declaration adds its set after the first' => ['merges-a-second-declaration', 'standards-sync-merged.php'];
    }
}
