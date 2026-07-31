<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\PhpStan\Family;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** The phpstan rules composed in one fold: the import creates the config, the floor then judges real content instead of abstaining. */
#[CoversNothing]
final class PhpStanFamilyTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        yield 'a created config satisfies the floor via the import' => ['from-scratch', 'standards-sync.php'];
    }
}
