<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Engine\TargetResolution;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Engine-level target resolution over the dist convention: the committed dist file is the standard's home, and a local override file is never written. */
#[CoversNothing]
final class TargetResolutionTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        yield 'a local override beside the dist file is left untouched' => ['dist-shadowed-by-local', 'standards-sync.php'];
        yield 'a lone dist file is synced normally' => ['lone-dist-file', 'standards-sync.php'];
    }
}
