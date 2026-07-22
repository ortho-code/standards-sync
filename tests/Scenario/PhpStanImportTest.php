<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpStanImportTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'phpstan-import/standards-sync.php';

        yield 'a project without a config gets one created' => ['phpstan-import/from-scratch', $config];
        yield 'an existing includes section gains the import' => ['phpstan-import/insert-into-section', $config];
        yield 'a config without includes gains the section at the top' => ['phpstan-import/creates-section', $config];
        yield 'an already-imported config stays put' => ['phpstan-import/already-included', $config];
    }
}
