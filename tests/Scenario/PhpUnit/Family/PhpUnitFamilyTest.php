<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\PhpUnit\Family;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpUnitFamilyTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'from scratch the template declared first wins over the skeleton' => ['from-scratch', $config];
        yield 'an existing config is converged, never reseeded' => ['converges-an-existing-config', $config];
    }
}
