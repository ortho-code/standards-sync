<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\Composer\Family;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ComposerFamilyTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        yield 'both rules fold into one manifest' => ['from-scratch', 'standards-sync.php'];
    }
}
