<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\Composer\ConfigSetting;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ComposerConfigSettingTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a manifest without a config key gains the section' => ['creates-the-config-section', $config];
        yield 'the setting joins the settings already there' => ['adds-to-an-existing-config', $config];
        yield 'a value the project changed is written back' => ['rewrites-a-deviating-value', $config];
        yield 'a value already matching is never touched' => ['leaves-a-matching-value', $config];
        yield 'a config object written on one line keeps that layout' => ['keeps-a-one-line-config-object', $config];
        yield 'a dotted setting pins a value nested under another' => ['pins-a-nested-setting', 'standards-sync-nested.php'];
    }
}
