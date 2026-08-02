<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\Ecs\Family;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Two org tiers layering their base sets in one fold: the base tier's rule creates the config, the second tier's rule adds its entry after it — additive, with the tool's registration order letting the later set win conflicts. */
#[CoversNothing]
final class EcsFamilyTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        yield 'two tiers register both sets in declaration order' => ['from-scratch', 'standards-sync.php'];
    }
}
