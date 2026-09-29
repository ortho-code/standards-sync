<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Core\Engine;

use OrthoCode\StandardsSync\Core\Engine\TargetResolver;
use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;
use OrthoCode\StandardsSync\Rules\Renovate\RenovateConfigFile;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** The classification edges of the dist convention; the policy itself is exercised through the pipeline in EngineTest. */
#[CoversClass(TargetResolver::class)]
final class TargetResolverTest extends TestCase
{
    public function testPrefersTheDistVariantInASubdirectory(): void
    {
        $resolved = $this->resolver([
            '/a/tools/phpunit.xml' => FileContent::fromString('local'),
            '/a/tools/phpunit.xml.dist' => FileContent::fromString('committed'),
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('tools/phpunit.xml', 'tools/phpunit.xml.dist'));

        self::assertSame('/a/tools/phpunit.xml.dist', $resolved->path()->value());
        self::assertSame('/a/tools/phpunit.xml', $resolved->shadowedBy()?->value());
    }

    public function testPrefersADistVariantWithAMiddleSegment(): void
    {
        $resolved = $this->resolver([
            '/a/psalm.xml' => FileContent::fromString('local'),
            '/a/psalm.dist.xml' => FileContent::fromString('committed'),
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('psalm.xml', 'psalm.dist.xml'));

        self::assertSame('/a/psalm.dist.xml', $resolved->path()->value());
    }

    public function testPrefersTheDistVariantInADotfileName(): void
    {
        $resolved = $this->resolver([
            '/a/.php-cs-fixer.php' => FileContent::fromString('local'),
            '/a/.php-cs-fixer.dist.php' => FileContent::fromString('committed'),
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('.php-cs-fixer.php', '.php-cs-fixer.dist.php'));

        self::assertSame('/a/.php-cs-fixer.dist.php', $resolved->path()->value());
    }

    public function testADistDirectoryDoesNotMakeAFileADistVariant(): void
    {
        // If the directory name counted, this would resolve with a preference instead of refusing two same-side files.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Both "/a/dist/a.conf" and "/a/etc/a.conf" exist for one target; remove all but one.');

        $this->resolver([
            '/a/dist/a.conf' => FileContent::fromString('one'),
            '/a/etc/a.conf' => FileContent::fromString('two'),
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('dist/a.conf', 'etc/a.conf'));
    }

    public function testDistInsideASegmentIsNotADistVariant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Both "/a/distribution.neon" and "/a/phpstan.neon" exist for one target; remove all but one.');

        $this->resolver([
            '/a/distribution.neon' => FileContent::fromString('one'),
            '/a/phpstan.neon' => FileContent::fromString('two'),
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('distribution.neon', 'phpstan.neon'));
    }

    /** Two renovate grammars beside each other are a confused repo — renovate itself would silently read only the precedence winner. */
    public function testTwoRenovateGrammarsRefuse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Both "/a/renovate.json" and "/a/renovate.json5" exist for one target; remove all but one.');

        $this->resolver([
            '/a/renovate.json' => FileContent::fromString('{}'),
            '/a/renovate.json5' => FileContent::fromString('{}'),
        ])->resolve(Path::fromString('/a'), RenovateConfigFile::target());
    }

    public function testALoneRenovateFileWinsWhateverTheCreationPreference(): void
    {
        $resolved = $this->resolver([
            '/a/renovate.json5' => FileContent::fromString('{}'),
        ])
            ->resolve(Path::fromString('/a'), RenovateConfigFile::target());

        self::assertSame('/a/renovate.json5', $resolved->path()->value());
    }

    /** @param array<string, string> $files */
    private function resolver(array $files): TargetResolver
    {
        return new TargetResolver(new InMemoryFilesystem($files));
    }
}
