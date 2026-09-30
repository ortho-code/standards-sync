<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Testing\Validation;

use OrthoCode\StandardsSync\Testing\FileContent;
use OrthoCode\StandardsSync\Testing\Validation\Json5Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Json5Validator::class)]
final class Json5ValidatorTest extends TestCase
{
    public function testAcceptsValidJson5(): void
    {
        $this->expectNotToPerformAssertions();

        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  // annotated
                  extends: [
                    'local>acme/renovate-config',
                  ],
                }
                JSON5,
        );

        new Json5Validator()->assertValid('./renovate.json5', $content);
    }

    public function testFailsLoudOnBrokenJson5(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The synced ./renovate.json5 is not valid JSON5');

        new Json5Validator()->assertValid('./renovate.json5', FileContent::fromString('{ extends: [broken'));
    }

    public function testIgnoresOtherExtensions(): void
    {
        $this->expectNotToPerformAssertions();

        new Json5Validator()->assertValid('./renovate.json', FileContent::fromString('{ not json5'));
    }

    public function testFailsLoudWhenTheParserIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('install colinodell/json5 (require-dev) to parse synced json5');

        new Json5Validator(parserInstalled: false)->assertValid('./renovate.json5', FileContent::fromString('{}'));
    }
}
