<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Testing\Validation;

use AlleKnalle\StandardsSync\Testing\FileContent;
use AlleKnalle\StandardsSync\Testing\Validation\YamlValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(YamlValidator::class)]
final class YamlValidatorTest extends TestCase
{
    public function testAcceptsValidYaml(): void
    {
        $this->expectNotToPerformAssertions();

        $content = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/deptrac.yaml
                YAML
        );

        new YamlValidator()->assertValid('./deptrac.yaml', $content);
    }

    public function testFailsLoudOnBrokenYaml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The synced ./deptrac.yaml is not valid yaml');

        new YamlValidator()->assertValid('./deptrac.yaml', "imports: [broken\n");
    }

    public function testCoversTheYmlSpellingToo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The synced ./pipeline.yml is not valid yaml');

        new YamlValidator()->assertValid('./pipeline.yml', "imports: [broken\n");
    }

    public function testIgnoresAFileThatIsNotYaml(): void
    {
        $this->expectNotToPerformAssertions();

        new YamlValidator()->assertValid('./notes.txt', "imports: [broken\n");
    }
}
