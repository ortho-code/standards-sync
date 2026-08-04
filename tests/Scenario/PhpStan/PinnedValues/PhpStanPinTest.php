<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\PhpStan\PinnedValues;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpStanPinTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'deviating values are rewritten and missing ones added' => ['pins-existing-config', $config];
        yield 'a project without a config gets one holding the pins' => ['creates-the-config', $config];
        yield 'an already-pinned config stays put' => ['already-pinned', $config];
        yield 'a hash inside a pinned value is content, not a comment' => ['pins-a-value-holding-a-hash', 'standards-sync-hash.php'];
    }
}
