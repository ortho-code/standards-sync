<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\PhpUnit\BaseConfig;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpUnitBaseConfigTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a missing config is seeded with the template' => ['seeds-a-missing-config', $config];
        yield 'an existing config is never edited' => ['never-edits-an-existing-config', $config];
    }
}
