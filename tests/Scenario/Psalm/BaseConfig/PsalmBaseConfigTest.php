<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Psalm\BaseConfig;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PsalmBaseConfigTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'no psalm config: the template is seeded verbatim' => ['seeds-a-missing-config', $config];
        yield 'an existing config is never edited, even one drifted from the template' => ['leaves-an-existing-config', $config];
    }
}
