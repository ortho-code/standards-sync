<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Source;

use OrthoCode\StandardsSync\Infrastructure\Filesystem\DirectoryListing;

/** The class names a PSR-4 directory promises: every .php file under the root, mapped through the namespace prefix. */
final readonly class Psr4Classes
{
    private const string PHP_EXTENSION = '.php';

    /** @param array<string, string> $classFiles class name => file path */
    private function __construct(private array $classFiles) {}

    public static function fromDirectory(string $directory, string $namespacePrefix): self
    {
        $root = rtrim($directory, '/');
        $prefix = rtrim($namespacePrefix, '\\');

        $classFiles = [];
        foreach (DirectoryListing::fromDirectory($root)->relativePaths() as $relativePath) {
            if (!str_ends_with($relativePath, self::PHP_EXTENSION)) {
                continue;
            }
            $withoutExtension = substr($relativePath, 0, -strlen(self::PHP_EXTENSION));
            $class = $prefix . '\\' . strtr($withoutExtension, [
                '/' => '\\',
                DIRECTORY_SEPARATOR => '\\',
            ]);
            $classFiles[$class] = $root . '/' . $relativePath;
        }

        return new self($classFiles);
    }

    /** @return array<string, string> class name => file path */
    public function all(): array
    {
        return $this->classFiles;
    }
}
