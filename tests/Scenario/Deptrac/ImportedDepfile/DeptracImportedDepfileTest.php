<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Deptrac\ImportedDepfile;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class DeptracImportedDepfileTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a project without a config gets one created' => ['from-scratch', $config];
        yield 'an existing imports section gains the depfile' => ['insert-into-section', $config];
        yield 'a config without imports gains the section at the top' => ['creates-section', $config];
        yield 'an already-imported config stays put' => ['already-imported', $config];
        yield 'a moved depfile replaces its predecessor in place, keeping the line\'s comment' => ['replaces-a-moved-depfile-in-place', $config];
        yield 'a depfile no standard declares any more is retracted, and the project\'s import stays' => ['retracts-a-depfile-no-longer-declared', $config];
        yield 'a second declaration adds its depfile after the first' => ['merges-a-second-declaration', 'standards-sync-merged.php'];
    }
}
