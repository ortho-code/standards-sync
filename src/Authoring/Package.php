<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Authoring;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use Composer\InstalledVersions;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystemComponent;
use Symfony\Component\Filesystem\Path as SymfonyPath;

/**
 * A standards package as installed in the consumer project.
 * read() loads distributed content at config-build time (for rules that copy, e.g. managed blocks); path() renders the consumer-root-relative reference (for rules that import, e.g. base sets and included rulesets).
 * Both resolve inside the package's templates/ directory — everything a package distributes lives there, so a rule can never point at the package's own config.
 */
final readonly class Package
{
    /** The one directory a package distributes from. */
    private const string TEMPLATES = 'templates';

    private const string MANIFEST = 'composer.json';

    private const string NAME_KEY = 'name';

    private Path $directory;

    /** Null when the package's files sit directly under the consumer project root (e.g. the package is the root project, as in its own test suite). */
    private ?Path $installedAt;

    private SymfonyFilesystemComponent $filesystem;

    /**
     * @param string $directory where the package's files physically are, for reading distributed content
     * @param string $installedAt where the package sits relative to the consumer project root, for rendering references
     */
    public function __construct(string $directory, string $installedAt)
    {
        $this->directory = Path::fromString($directory);
        $this->installedAt = $installedAt === '' ? null : Path::fromRelativeString($installedAt);

        $this->filesystem = new SymfonyFilesystemComponent();
    }

    /**
     * Locates the package declaring the given class: the nearest composer.json above the class file names it, and composer's install record places it.
     * The record is authoritative where the file location cannot be: under a symlinked install the class file resolves outside the consumer project, while the record keeps the portable vendor path.
     */
    public static function fromClass(string $class): self
    {
        $file = new ReflectionClass($class)->getFileName();
        if ($file === false) {
            throw new RuntimeException(sprintf('Class "%s" has no source file to locate a package from.', $class));
        }

        $directory = dirname($file);
        while (!is_file($directory . '/' . self::MANIFEST)) {
            $parent = dirname($directory);
            if ($parent === $directory) {
                throw new RuntimeException(sprintf('No composer.json found above "%s"; construct the Package explicitly.', $file));
            }
            $directory = $parent;
        }

        return new self($directory, self::installLocation(self::packageName($directory)));
    }

    /** Reads a distributed file's content, at config-build time; throws (never warns) when it is missing or unreadable. */
    public function read(string $file): string
    {
        return $this->filesystem->readFile($this->directory->join($this->distributed($file))->value());
    }

    /** The consumer-root-relative path a consumer config must reference for a distributed file. */
    public function path(string $file): string
    {
        $distributed = $this->distributed($file);

        return $this->installedAt === null ? $distributed->value() : $this->installedAt->join($distributed)->value();
    }

    // A distributed file is named relative to the templates directory.
    private function distributed(string $file): Path
    {
        return Path::fromString(self::TEMPLATES)->join(Path::fromRelativeString($file));
    }

    private static function packageName(string $directory): string
    {
        $manifest = $directory . '/' . self::MANIFEST;
        $raw = file_get_contents($manifest);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Cannot read "%s".', $manifest));
        }

        $decoded = json_decode($raw, true);
        $name = is_array($decoded) ? ($decoded[self::NAME_KEY] ?? null) : null;
        if (!is_string($name) || $name === '') {
            throw new RuntimeException(sprintf('"%s" declares no package name; construct the Package explicitly.', $manifest));
        }

        return $name;
    }

    private static function installLocation(string $name): string
    {
        if (!InstalledVersions::isInstalled($name)) {
            throw new RuntimeException(sprintf('Composer has no install record for "%s"; construct the Package explicitly.', $name));
        }

        $installPath = InstalledVersions::getInstallPath($name);
        if ($installPath === null) {
            throw new RuntimeException(sprintf('Composer records no install path for "%s"; construct the Package explicitly.', $name));
        }

        // Canonicalized textually, never through realpath: the vendor path is the portable name for the package, the resolved path is machine-local.
        return SymfonyPath::makeRelative(SymfonyPath::canonicalize($installPath), SymfonyPath::canonicalize((string) getcwd()));
    }
}
