<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Scenario\Deptrac\ImportedDepfile;

use StandardsSync\Testing\ScenarioTestCase;
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
    }
}
