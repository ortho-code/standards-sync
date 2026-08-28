<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Psalm\BaseConfig;

use OrthoCode\StandardsSync\Rules\Psalm\BaseConfig\PsalmBaseConfig;
use OrthoCode\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PsalmBaseConfig::class)]
final class PsalmBaseConfigTest extends TestCase
{
    public function testSeedsAMissingConfigWithTheTemplateVerbatim(): void
    {
        self::assertSame(self::template(), $this->rule()->apply(null));
    }

    public function testNeverEditsAnExistingConfig(): void
    {
        $existing = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm errorLevel="8">
                    <projectFiles>
                        <directory name="app" />
                    </projectFiles>
                </psalm>
                XML,
        );

        self::assertSame($existing, $this->rule()->apply($existing));
    }

    public function testRejectsAnEmptyTemplate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PsalmBaseConfig(config: '');
    }

    private function rule(): PsalmBaseConfig
    {
        return new PsalmBaseConfig(config: self::template());
    }

    private static function template(): string
    {
        return FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm
                    errorLevel="2"
                    xmlns="https://getpsalm.org/schema/config"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML,
        );
    }
}
