<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpStanPinTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'phpstan-pinned-values/standards-sync.php';

        yield 'deviating values are rewritten and missing ones added' => ['phpstan-pinned-values/pins-existing-config', $config];
        yield 'a project without a config gets one holding the pins' => ['phpstan-pinned-values/creates-the-config', $config];
        yield 'an already-pinned config stays put' => ['phpstan-pinned-values/already-pinned', $config];
    }
}
