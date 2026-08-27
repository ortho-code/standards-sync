<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Psalm\Family;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PsalmFamilyTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'no psalm config: the template declared first wins over the skeleton, its compliant level untouched' => ['from-scratch', $config];
        yield 'an existing config is never reseeded, only converged' => ['lowers-inside-an-existing-config', $config];
    }
}
