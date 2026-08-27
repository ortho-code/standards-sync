<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Composer\ConfigSetting;

use OrthoCode\StandardsSync\Rules\Composer\ConfigSetting\ComposerConfigSetting;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerConfigSetting::class)]
final class ComposerConfigSettingTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function unusableSettings(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'a trailing separator' => ['preferred-install.'];
        yield 'a leading separator' => ['.sort-packages'];
        yield 'an empty segment between two keys' => ['preferred-install..dist'];
    }

    #[DataProvider('unusableSettings')]
    public function testRefusesASettingWithAnEmptySegment(string $setting): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('every segment of a dotted name needs a key');

        new ComposerConfigSetting(setting: $setting, value: true);
    }

    public function testAbstainsWithoutAManifest(): void
    {
        self::assertNull(new ComposerConfigSetting(setting: 'sort-packages', value: true)->apply(null));
    }

    /** @return iterable<string, array{string|bool|int, string}> */
    public static function values(): iterable
    {
        yield 'a boolean' => [true, 'Pins the composer config setting "sort-packages" to true.'];
        yield 'a string' => ['vendor', 'Pins the composer config setting "sort-packages" to "vendor".'];
        yield 'an integer' => [900, 'Pins the composer config setting "sort-packages" to 900.'];
    }

    /** The description shows the value as the manifest will carry it, so a boolean never reads as the string "true". */
    #[DataProvider('values')]
    public function testTheDescriptionRendersTheValueAsJson(string|bool|int $value, string $expected): void
    {
        self::assertSame($expected, new ComposerConfigSetting(setting: 'sort-packages', value: $value)->description());
    }
}
