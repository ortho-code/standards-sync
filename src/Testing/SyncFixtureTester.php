<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Testing;

use OrthoCode\StandardsSync\Core\Config\ConfigLoader;
use OrthoCode\StandardsSync\Core\Filesystem\Filesystem;
use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Infrastructure\Filesystem\DirectoryListing;
use OrthoCode\StandardsSync\Infrastructure\Filesystem\SymfonyFilesystem;
use OrthoCode\StandardsSync\Testing\Validation\Json5Validator;
use OrthoCode\StandardsSync\Testing\Validation\JsonValidator;
use OrthoCode\StandardsSync\Testing\Validation\NeonValidator;
use OrthoCode\StandardsSync\Testing\Validation\PhpUnitConfigValidator;
use OrthoCode\StandardsSync\Testing\Validation\PsalmConfigValidator;
use OrthoCode\StandardsSync\Testing\Validation\SyncedFileValidator;
use OrthoCode\StandardsSync\Testing\Validation\XmlValidator;
use OrthoCode\StandardsSync\Testing\Validation\YamlValidator;

/**
 * Runs a sync against an on-disk fixture and reports how the result differs from the expected tree.
 * A fixture holds an input tree (a target repo's files before the sync) and an expected tree (its files after); the config is the fixture's own standards-sync.php by default, or one you supply, so the maintainer reads real files to see what a config does.
 * Framework-neutral: it returns the differences, so consumers assert with whatever they use.
 * Synced files are additionally asserted to parse (nette/neon; ext-dom for XML, plus psalm's config.xsd via vimeo/psalm and phpunit's phpunit.xsd via phpunit/phpunit; ext-json for JSON; colinodell/json5 for JSON5; symfony/yaml for YAML), so a writer can never produce syntactically broken output unnoticed; a synced file whose parser is not installed fails loud rather than skipping silently.
 */
final class SyncFixtureTester
{
    /** The current-directory root every fixture repo is synced against. */
    private const string ROOT = '.';

    /** The config file loaded from a fixture by default, and the subdirectories holding its before and after trees. */
    public const string CONFIG = 'standards-sync.php';
    public const string INPUT = 'input';
    public const string EXPECTED = 'expected';

    /** @var list<SyncedFileValidator> */
    private readonly array $validators;

    /** @param list<SyncedFileValidator>|null $validators the checks run over synced output; null means all shipped validators */
    public function __construct(
        private readonly Filesystem $filesystem = new SymfonyFilesystem(),
        private readonly SyncTester $syncTester = new SyncTester(),
        ?array $validators = null,
    ) {
        $this->validators = $validators ?? [new NeonValidator(), new XmlValidator(), new PsalmConfigValidator(), new PhpUnitConfigValidator(), new JsonValidator(), new Json5Validator(), new YamlValidator()];
    }

    /**
     * Loads the config (the fixture's own standards-sync.php by default, or $configFile if given), syncs the input tree in memory, then compares the result to the expected tree.
     *
     * @return list<Mismatch> empty when the sync matches the expected tree
     */
    public function diff(string $fixtureDirectory, ?string $configFile = null): array
    {
        $fixture = Path::fromString($fixtureDirectory);
        $root = Path::fromString(self::ROOT);

        $configPath = $configFile !== null
            ? Path::fromString($configFile)
            : $fixture->join(Path::fromString(self::CONFIG));
        $config = (new ConfigLoader())->loadFrom($configPath);

        $result = $this->syncTester->sync(
            $config->withRoots([$root->value()]),
            $this->readTree($fixture->join(Path::fromString(self::INPUT)), $root),
        );
        $this->assertParseable($result);

        return $this->compare($result, $this->readTree($fixture->join(Path::fromString(self::EXPECTED)), $root));
    }

    /**
     * Every synced file passes the validator list, so a writer can never produce syntactically broken output unnoticed.
     * Validators self-guard on optional parsers, so org-package suites run without them — and gain checks by installing the parsers.
     *
     * @param array<string, string> $result
     */
    private function assertParseable(array $result): void
    {
        foreach ($result as $path => $content) {
            foreach ($this->validators as $validator) {
                $validator->assertValid($path, $content);
            }
        }
    }

    /**
     * Reads a fixture tree into the path => contents map the engine works with, keying each file the way the resolver does.
     *
     * @return array<string, string>
     */
    private function readTree(Path $directory, Path $root): array
    {
        $tree = [];
        foreach (DirectoryListing::fromDirectory($directory->value())->relativePaths() as $relativePath) {
            $content = $this->filesystem->read($directory->join(Path::fromString($relativePath)));
            if ($content !== null) {
                $tree[$root->join(Path::fromString($relativePath))->value()] = $content;
            }
        }

        return $tree;
    }

    /**
     * @param array<string, string> $actual
     * @param array<string, string> $expected
     * @return list<Mismatch>
     */
    private function compare(array $actual, array $expected): array
    {
        $paths = array_unique([...array_keys($actual), ...array_keys($expected)]);
        sort($paths);

        $mismatches = [];
        foreach ($paths as $path) {
            if (($actual[$path] ?? null) !== ($expected[$path] ?? null)) {
                $mismatches[] = new Mismatch(Path::fromString($path), $expected[$path] ?? null, $actual[$path] ?? null);
            }
        }

        return $mismatches;
    }
}
