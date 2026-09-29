<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Renovate\ExtendedPreset;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Rule\AppliesAtPath;
use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Core\Text\Lines;
use OrthoCode\StandardsSync\Formats\Json\JsonObjectWriter;
use OrthoCode\StandardsSync\Formats\Json5\Json5ListWriter;
use OrthoCode\StandardsSync\Rules\Renovate\RenovateConfigFile;
use OrthoCode\StandardsSync\Rules\Renovate\RenovateConfigFormat;
use JsonException;
use LogicException;
use RuntimeException;

/**
 * Ensures the renovate config extends a given preset, as a targeted edit that leaves the rest of the file untouched.
 * An existing extends list gains a missing entry in the place of one no standard declares any more, and at its end otherwise; a config without the list gains it; a project without a renovate config gets one created in the org-chosen format.
 * The optional comment is written and enforced on the entry's line where the grammar has comments (json5); a strict-JSON config carries the standard unexplained.
 * Declarations of extended presets combine in declaration order, a preset declared twice counting once with its first declaration's comment, and the first declaration's creation format standing for all; a preset extended at an earlier sync and declared by nobody now is retracted, and every other entry is the project's and stays.
 *
 * The preset itself is not distributed by this engine: renovate fetches it from its repository over the forge API, never from a composer install.
 * It therefore lives where renovate's preset resolution looks — a bare "local><owner>/<repo>" reference resolves that repository's default.json — and not under the package's templates/ directory.
 */
final readonly class RenovateExtendedPreset implements Rule, ContributesToList, ExplainsDrift, AppliesAtPath
{
    private const string SECTION = 'extends';

    /** @var non-empty-list<Preset> */
    private array $presets;

    /** @var list<string> */
    private array $retired;

    public function __construct(
        string $preset,
        private RenovateConfigFormat $createAs = RenovateConfigFormat::Json,
        ?string $comment = null,
    ) {
        $this->presets = [Preset::fromReference($preset, $comment)];
        $this->retired = [];
    }

    #[\Override]
    public function target(): FileTarget
    {
        return RenovateConfigFile::target($this->createAs);
    }

    #[\Override]
    public function listKey(): string
    {
        return self::SECTION;
    }

    #[\Override]
    public function entries(): array
    {
        return array_map(static fn(Preset $preset): string => $preset->reference(), $this->presets);
    }

    #[\Override]
    public function withMerged(ContributesToList $later): static
    {
        if (!$later instanceof self) {
            throw new LogicException('Only declarations of extended renovate presets merge into one.');
        }

        $presets = $this->presets;
        foreach ($later->presets as $preset) {
            if (!array_any($presets, static fn(Preset $declared): bool => $declared->reference() === $preset->reference())) {
                $presets[] = $preset;
            }
        }

        /** @var static $merged psalm types clone-with as a plain object */
        $merged = clone($this, [
            'presets' => $presets,
        ]);

        return $merged;
    }

    #[\Override]
    public function withRetired(array $retired): static
    {
        /** @var static $retiring psalm types clone-with as a plain object */
        $retiring = clone($this, [
            'retired' => $retired,
        ]);

        return $retiring;
    }

    #[\Override]
    public function apply(?string $content): ?string
    {
        // Without a resolved path the creation candidate's grammar applies; the engine calls applyAt() with the real one.
        return $this->applyAt($this->target()->candidates()[0], $content);
    }

    #[\Override]
    public function applyAt(Path $path, ?string $content): ?string
    {
        if (RenovateConfigFile::isJsonc($path)) {
            throw new RuntimeException(sprintf('"%s" is a JSONC config, which this standard does not manage; use strict JSON in "renovate.json" or JSON5 in "renovate.json5".', $path->value()));
        }

        if (RenovateConfigFile::isJson5($path)) {
            $content ??= '';
            foreach ($this->presets as $preset) {
                $content = Json5ListWriter::ensureEntry($content, self::SECTION, $preset->reference(), $preset->comment(), $this->retired);
            }

            return Json5ListWriter::removeEntries($content, self::SECTION, $this->retired);
        }

        // A project without a renovate config gets one: enforcing the standard is the point.
        $created = $content === null;
        if ($content === null) {
            $content = '{}';
        } else {
            $this->assertStrictJson($path, $content);
        }

        foreach ($this->entries() as $reference) {
            $content = JsonObjectWriter::ensureListEntry($content, [self::SECTION], $reference, $this->retired);
        }
        $content = JsonObjectWriter::removeListEntries($content, [self::SECTION], $this->retired);

        return $created ? $content . Lines::LINE_BREAK : $content;
    }

    #[\Override]
    public function description(): string
    {
        return sprintf('Ensures the renovate config extends %s.', self::quoted($this->entries()));
    }

    #[\Override]
    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no renovate config yet; one is created extending %s.', self::quoted($this->entries()));
        }

        // JSON5 reads strict JSON too, so one reader serves both grammars.
        $extended = Json5ListWriter::readList($content, self::SECTION) ?? [];

        $sentences = [];
        $missing = array_values(array_diff($this->entries(), $extended));
        if ($missing !== []) {
            $sentences[] = sprintf('The renovate config does not extend %s.', self::quoted($missing));
        }

        $retracted = array_values(array_intersect($this->retired, $extended));
        if ($retracted !== []) {
            $sentences[] = sprintf('It stops extending %s, which no standard declares any more.', self::quoted($retracted));
        }

        // A comment is enforced only where the grammar has comments, so an annotation is named as the cause only when nothing else explains the drift.
        if ($sentences === []) {
            $unannotated = array_values(array_map(
                static fn(Preset $preset): string => $preset->reference(),
                array_filter($this->presets, static fn(Preset $preset): bool => $preset->comment() !== null && Json5ListWriter::ensureEntry($content, self::SECTION, $preset->reference(), $preset->comment()) !== $content),
            ));
            if ($unannotated !== []) {
                $sentences[] = count($unannotated) === 1
                    ? sprintf('The %s entry is not annotated with the enforced comment.', self::quoted($unannotated))
                    : sprintf('The %s entries are not annotated with their enforced comments.', self::quoted($unannotated));
            }
        }

        return $sentences === [] ? 'The renovate config\'s extends list differs from what the standards declare.' : implode(' ', $sentences);
    }

    /** Renovate itself tolerates comments and trailing commas here, but the standard lands only in strict JSON — json5 is the grammar for an annotated config. */
    private function assertStrictJson(Path $path, string $content): void
    {
        try {
            json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('"%s" is not strict JSON (%s); make it strict JSON, or move the config to a ".json5" file name.', $path->value(), $exception->getMessage()), 0, $exception);
        }
    }

    /** @param list<string> $references */
    private static function quoted(array $references): string
    {
        return '"' . implode('", "', $references) . '"';
    }
}
