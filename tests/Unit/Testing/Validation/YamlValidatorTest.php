<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Testing\Validation;

use OrthoCode\StandardsSync\Testing\FileContent;
use OrthoCode\StandardsSync\Testing\Validation\YamlValidator;
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
                YAML,
        );

        new YamlValidator()->assertValid('./deptrac.yaml', $content);
    }

    public function testFailsLoudOnBrokenYaml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The synced ./deptrac.yaml is not valid yaml');

        new YamlValidator()->assertValid('./deptrac.yaml', FileContent::fromString('imports: [broken'));
    }

    public function testCoversTheYmlSpellingToo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The synced ./pipeline.yml is not valid yaml');

        new YamlValidator()->assertValid('./pipeline.yml', FileContent::fromString('imports: [broken'));
    }

    public function testIgnoresAFileThatIsNotYaml(): void
    {
        $this->expectNotToPerformAssertions();

        new YamlValidator()->assertValid('./notes.txt', FileContent::fromString('imports: [broken'));
    }

    public function testFailsLoudWhenTheParserIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('install symfony/yaml');

        new YamlValidator(parserInstalled: false)->assertValid('./deptrac.yaml', FileContent::fromString('imports: []'));
    }

    public function testIgnoresAFileItDoesNotCoverEvenWithoutTheParser(): void
    {
        $this->expectNotToPerformAssertions();

        new YamlValidator(parserInstalled: false)->assertValid('./notes.txt', 'anything');
    }
}
