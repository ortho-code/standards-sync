<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Lock;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Plan\ForgottenList;
use OrthoCode\StandardsSync\Core\Text\Lines;
use JsonException;
use RuntimeException;
use stdClass;

/**
 * The entries declared at the last sync, per file and list, as a root's standards-sync.lock records them.
 * The engine owns the whole file, so it is rendered afresh rather than edited in place, and anything this class did not render is refused rather than guessed at.
 */
final readonly class SyncLock
{
    public const string FILE = 'standards-sync.lock';

    private const string KEY_README = '_readme';
    private const string KEY_FILES = 'files';

    private const int RENDER_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /** @param array<string, array<string, non-empty-list<string>>> $files the entries by list key, by file path relative to the root */
    private function __construct(private array $files) {}

    public static function create(): self
    {
        return new self([]);
    }

    public static function fromJson(string $json, Path $path): self
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('"%s" is not valid JSON (%s); delete it and sync again to rewrite it.', $path->value(), $exception->getMessage()), $exception->getCode(), previous: $exception);
        }

        $files = is_array($decoded) ? $decoded[self::KEY_FILES] ?? null : null;
        if (!is_array($files) || !self::isFileMap($files)) {
            throw new RuntimeException(sprintf('"%s" is not a lock standards-sync wrote; delete it and sync again to rewrite it.', $path->value()));
        }

        /** @var array<string, array<string, non-empty-list<string>>> $files */
        return new self($files);
    }

    /**
     * The entries recorded for a list, or null when the list is not recorded.
     *
     * @return non-empty-list<string>|null
     */
    public function entries(Path $file, string $listKey): ?array
    {
        return $this->files[$file->value()][$listKey] ?? null;
    }

    /**
     * @param list<string> $declared
     * @return list<string> the entries recorded for the list and not declared now
     */
    public function retired(Path $file, string $listKey, array $declared): array
    {
        return array_values(array_diff($this->entries($file, $listKey) ?? [], $declared));
    }

    /** @param non-empty-list<string> $entries */
    public function withEntries(Path $file, string $listKey, array $entries): self
    {
        $files = $this->files;
        $files[$file->value()][$listKey] = $entries;

        return new self($files);
    }

    /**
     * The lists this lock records that the next one does not, located under the root the lock belongs to.
     *
     * @return list<ForgottenList>
     */
    public function forgottenBy(self $next, Path $root): array
    {
        $forgotten = [];
        foreach ($this->files as $file => $lists) {
            foreach (array_keys($lists) as $listKey) {
                if (!isset($next->files[$file][$listKey])) {
                    $forgotten[] = new ForgottenList($root->join(Path::fromString($file)), $listKey);
                }
            }
        }

        return $forgotten;
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /** Files and list keys sort, so the rendering depends on what was declared and never on declaration order; entries keep the order they were declared in. */
    public function toJson(): string
    {
        $files = $this->files;
        ksort($files);
        $files = array_map(static function (array $lists): array {
            ksort($lists);

            return $lists;
        }, $files);

        return json_encode([
            self::KEY_README => [
                'Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.',
                'Commit this file; do not edit it.',
            ],
            self::KEY_FILES => $files === [] ? new stdClass() : $files,
        ], self::RENDER_FLAGS) . Lines::LINE_BREAK;
    }

    /** @param array<mixed> $files */
    private static function isFileMap(array $files): bool
    {
        return array_all($files, static fn(mixed $lists, int|string $file): bool => is_string($file)
            && is_array($lists)
            && array_all($lists, static fn(mixed $entries, int|string $listKey): bool => is_string($listKey)
                && is_array($entries)
                && $entries !== []
                && array_is_list($entries)
                && array_all($entries, static fn(mixed $entry): bool => is_string($entry))));
    }
}
