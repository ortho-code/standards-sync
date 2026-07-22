<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpStanLevelFloorTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'phpstan-min-level/standards-sync.php';

        yield 'a lower level is raised to the floor' => ['phpstan-min-level/raises-a-lower-level', $config];
        yield 'a stricter level is never touched' => ['phpstan-min-level/leaves-a-stricter-level', $config];
        yield 'a missing level line is left to the imported ruleset' => ['phpstan-min-level/missing-level-stays', $config];
        yield 'no phpstan config, no opinion' => ['phpstan-min-level/absent-config-abstains', $config];
        yield 'write mode writes a missing level' => ['phpstan-min-level/writes-a-missing-level', 'phpstan-min-level/standards-sync-write.php'];
    }
}
