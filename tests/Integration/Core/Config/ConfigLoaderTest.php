<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Core\Config;

use OrthoCode\StandardsSync\Core\Config\ConfigLoader;
use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Filesystem\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\OrthoCode\StandardsSync\Integration\IntegrationTestCase;

#[CoversClass(ConfigLoader::class)]
final class ConfigLoaderTest extends IntegrationTestCase
{
    public function testLoadsAFileThatReturnsSyncConfig(): void
    {
        $path = $this->writeConfig('<?php return \OrthoCode\StandardsSync\Core\Config\SyncConfig::create();');

        self::assertInstanceOf(SyncConfig::class, (new ConfigLoader())->loadFrom($path));
    }

    #[DataProvider('nonConfigReturns')]
    public function testRejectsAFileThatDoesNotReturnSyncConfig(string $returnExpression): void
    {
        $path = $this->writeConfig('<?php return ' . $returnExpression . ';');

        $this->expectException(RuntimeException::class);
        (new ConfigLoader())->loadFrom($path);
    }

    /** @return iterable<string, array{string}> */
    public static function nonConfigReturns(): iterable
    {
        yield 'integer' => ['42'];
        yield 'string' => ['"nope"'];
        yield 'array' => ['[]'];
        yield 'null' => ['null'];
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        (new ConfigLoader())->loadFrom(Path::fromString($this->workspace() . '/does-not-exist.php'));
    }

    private function writeConfig(string $php): Path
    {
        return Path::fromString($this->writeToWorkspace('standards-sync.php', $php));
    }
}
