<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Testing;

use OrthoCode\StandardsSync\Testing\SyncFixtureTester;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * One end-to-end failure proof that the tester runs its validators over synced output; per-validator behaviour is unit-tested in Unit/Testing/Validation.
 * The XSD tier runs wherever psalm's shipped XSD is installed — in this repo's require-dev since the phpunit ^12 pin made room, and in an org package's own suite.
 */
#[CoversClass(SyncFixtureTester::class)]
final class SyncFixtureTesterTest extends TestCase
{
    public function testFailsLoudWhenASyncedXmlFileIsNotWellFormed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The synced ./broken.xml is not well-formed XML');

        (new SyncFixtureTester())->diff(__DIR__ . '/fixtures/broken-xml');
    }
}
