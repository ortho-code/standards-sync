<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\PhpUnit\PinnedAttributes;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpUnitPinnedAttributesTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a deviating value is rewritten in place' => ['rewrites-a-deviating-value', $config];
        yield 'a spelling phpunit reads as false is normalized' => ['normalizes-a-boolean-spelling', $config];
        yield 'missing attributes are appended inline on a single-line tag' => ['adds-missing-attributes-inline', $config];
        yield 'missing attributes each get a fresh line in a multiline tag' => ['adds-missing-attributes-on-a-fresh-line', $config];
        yield 'a compliant config is left byte-identical' => ['leaves-a-compliant-config', $config];
        yield 'no phpunit config: one is created holding the pins' => ['creates-the-config', $config];
    }
}
