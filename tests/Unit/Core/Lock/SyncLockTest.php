<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Core\Lock;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Lock\SyncLock;
use OrthoCode\StandardsSync\Core\Plan\ForgottenList;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SyncLock::class)]
final class SyncLockTest extends TestCase
{
    public function testRendersFilesAndListsSortedAndEntriesInDeclaredOrder(): void
    {
        $lock = SyncLock::create()
            ->withEntries(Path::fromString('composer.json'), 'scripts.app-phpstan', ['phpstan analyse'])
            ->withEntries(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check', '@app-phpstan'])
            ->withEntries(Path::fromString('.config/tool.json'), 'extends', ['acme']);

        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "_readme": [
                        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
                        "Commit this file; do not edit it."
                    ],
                    "files": {
                        ".config/tool.json": {
                            "extends": [
                                "acme"
                            ]
                        },
                        "composer.json": {
                            "scripts.app-checks": [
                                "@app-sync-check",
                                "@app-phpstan"
                            ],
                            "scripts.app-phpstan": [
                                "phpstan analyse"
                            ]
                        }
                    }
                }
                JSON,
        );

        self::assertSame($expected, $lock->toJson());
    }

    public function testAnEmptyLockRendersAnEmptyFileMap(): void
    {
        self::assertStringContainsString('"files": {}', SyncLock::create()->toJson());
    }

    public function testReadsBackWhatItRendered(): void
    {
        $lock = SyncLock::create()->withEntries(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check']);

        $read = SyncLock::fromJson($lock->toJson(), Path::fromString(SyncLock::FILE));

        self::assertSame(['@app-sync-check'], $read->entries(Path::fromString('composer.json'), 'scripts.app-checks'));
        self::assertSame($lock->toJson(), $read->toJson());
    }

    public function testAnUnrecordedListHasNoEntries(): void
    {
        self::assertNull(SyncLock::create()->entries(Path::fromString('composer.json'), 'scripts.app-checks'));
    }

    public function testRetiredEntriesAreTheRecordedOnesNoLongerDeclared(): void
    {
        $lock = SyncLock::create()->withEntries(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check', '@app-phpcs', '@app-run-tests']);

        self::assertSame(['@app-phpcs'], $lock->retired(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check', '@app-run-tests', '@app-lint']));
    }

    public function testAnUnrecordedListRetiresNothing(): void
    {
        self::assertSame([], SyncLock::create()->retired(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check']));
    }

    public function testAListTheNextLockDoesNotRecordIsForgotten(): void
    {
        $file = Path::fromString('composer.json');
        $current = SyncLock::create()
            ->withEntries($file, 'scripts.app-checks', ['@app-sync-check'])
            ->withEntries($file, 'scripts.app-phpcs', ['phpcs']);
        $next = SyncLock::create()->withEntries($file, 'scripts.app-checks', ['@app-sync-check']);

        self::assertEquals(
            [new ForgottenList(Path::fromString('./composer.json'), 'scripts.app-phpcs')],
            $current->forgottenBy($next, Path::fromString('.')),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function foreignLocks(): iterable
    {
        yield 'no file map' => ['{"_readme": []}'];
        yield 'a list that is not a list' => ['{"files": {"composer.json": {"scripts.app-checks": "@app-sync-check"}}}'];
        yield 'an empty list' => ['{"files": {"composer.json": {"scripts.app-checks": []}}}'];
        yield 'an entry that is not a string' => ['{"files": {"composer.json": {"scripts.app-checks": [1]}}}'];
        yield 'not an object' => ['[]'];
    }

    #[DataProvider('foreignLocks')]
    public function testRefusesALockItDidNotWrite(string $json): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"./standards-sync.lock" is not a lock standards-sync wrote; delete it and sync again to rewrite it.');

        SyncLock::fromJson($json, Path::fromString('./standards-sync.lock'));
    }

    public function testRefusesInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"./standards-sync.lock" is not valid JSON');

        SyncLock::fromJson('{', Path::fromString('./standards-sync.lock'));
    }
}
