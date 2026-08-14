<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\PhpUnit\BaseConfig;

use AlleKnalle\StandardsSync\Rules\PhpUnit\BaseConfig\PhpUnitBaseConfig;
use AlleKnalle\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpUnitBaseConfig::class)]
final class PhpUnitBaseConfigTest extends TestCase
{
    public function testSeedsAMissingConfigWithTheTemplateVerbatim(): void
    {
        self::assertSame(self::template(), $this->rule()->apply(null));
    }

    public function testNeverEditsAnExistingConfig(): void
    {
        $existing = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <phpunit bootstrap="tests/bootstrap.php">
                    <testsuites>
                        <testsuite name="app">
                            <directory>tests/App</directory>
                        </testsuite>
                    </testsuites>
                </phpunit>
                XML
        );

        self::assertSame($existing, $this->rule()->apply($existing));
    }

    public function testRejectsAnEmptyTemplate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhpUnitBaseConfig(config: '');
    }

    private function rule(): PhpUnitBaseConfig
    {
        return new PhpUnitBaseConfig(config: self::template());
    }

    private static function template(): string
    {
        return FileContent::fromString(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <phpunit bootstrap="vendor/autoload.php"
                         failOnWarning="true">
                    <testsuites>
                        <testsuite name="default">
                            <directory>tests</directory>
                        </testsuite>
                    </testsuites>
                </phpunit>
                XML
        );
    }
}
