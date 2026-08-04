<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Testing;

use AlleKnalle\StandardsSync\Testing\SyncFixtureTester;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * One end-to-end failure proof that the tester runs its validators over synced output; per-validator behaviour is unit-tested in Unit/Testing/Validation.
 * The XSD tier stays inert in this suite (psalm is not installable beside phpunit 13) and is exercised by org-package suites that install psalm.
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
