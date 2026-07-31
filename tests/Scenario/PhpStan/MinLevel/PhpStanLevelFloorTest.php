<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\PhpStan\MinLevel;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpStanLevelFloorTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a lower level is raised to the floor' => ['raises-a-lower-level', $config];
        yield 'a stricter level is never touched' => ['leaves-a-stricter-level', $config];
        yield 'a missing level line is left to the imported ruleset' => ['missing-level-stays', $config];
        yield 'no phpstan config, no opinion' => ['absent-config-abstains', $config];
        yield 'write mode writes a missing level' => ['writes-a-missing-level', 'standards-sync-write.php'];
    }
}
