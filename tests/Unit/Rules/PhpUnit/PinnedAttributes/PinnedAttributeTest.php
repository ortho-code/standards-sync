<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Rules\PhpUnit\PinnedAttributes;

use StandardsSync\Rules\PhpUnit\PinnedAttributes\PinnedAttribute;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PinnedAttribute::class)]
final class PinnedAttributeTest extends TestCase
{
    #[DataProvider('renderedValues')]
    public function testRendersTheValueAsItsWrittenText(bool|int|string $value, string $expected): void
    {
        self::assertSame($expected, PinnedAttribute::fromNameAndValue('failOnWarning', $value)->value());
    }

    /** @return iterable<string, array{bool|int|string, string}> */
    public static function renderedValues(): iterable
    {
        yield 'true renders as the canonical spelling' => [true, 'true'];
        yield 'false renders as the canonical spelling' => [false, 'false'];
        yield 'an integer renders as decimal text' => [3, '3'];
        yield 'a string passes through verbatim' => ['depends,defects', 'depends,defects'];
    }

    public function testExposesItsName(): void
    {
        self::assertSame('failOnWarning', PinnedAttribute::fromNameAndValue('failOnWarning', true)->name());
    }

    public function testAllowsANamespacedName(): void
    {
        self::assertSame('xsi:noNamespaceSchemaLocation', PinnedAttribute::fromNameAndValue('xsi:noNamespaceSchemaLocation', 'phpunit.xsd')->name());
    }

    #[DataProvider('invalidNames')]
    public function testRejectsANameThatIsNoXmlAttributeName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an XML attribute name');

        PinnedAttribute::fromNameAndValue($name, true);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace inside' => ['fail On'];
        yield 'an equals sign' => ['fail=On'];
        yield 'a leading digit' => ['1failOnWarning'];
    }

    public function testRejectsAnEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');

        PinnedAttribute::fromNameAndValue('failOnWarning', '');
    }
}
