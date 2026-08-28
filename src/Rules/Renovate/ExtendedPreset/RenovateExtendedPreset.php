<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Renovate\ExtendedPreset;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Rule\AppliesAtPath;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Core\Text\Lines;
use OrthoCode\StandardsSync\Formats\Json\JsonObjectWriter;
use OrthoCode\StandardsSync\Formats\Json5\Json5ListWriter;
use OrthoCode\StandardsSync\Rules\Renovate\RenovateConfigFile;
use OrthoCode\StandardsSync\Rules\Renovate\RenovateConfigFormat;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Ensures the renovate config extends a given preset, as a targeted edit that leaves the rest of the file untouched.
 * An existing extends list gains the entry; a config without the list gains it; a project without a renovate config gets one created in the org-chosen format.
 * The optional comment is written and enforced on the entry's line where the grammar has comments (json5); a strict-JSON config carries the standard unexplained.
 *
 * The preset itself is not distributed by this engine: renovate fetches it from its repository over the forge API, never from a composer install.
 * It therefore lives where renovate's preset resolution looks — a bare "local><owner>/<repo>" reference resolves that repository's default.json — and not under the package's templates/ directory.
 */
final readonly class RenovateExtendedPreset implements Rule, ExplainsDrift, AppliesAtPath
{
    private const string SECTION = 'extends';

    public function __construct(
        private string $preset,
        private RenovateConfigFormat $createAs = RenovateConfigFormat::Json,
        private ?string $comment = null,
    ) {
        if (trim($this->preset) === '' || str_contains($this->preset, Lines::LINE_BREAK)) {
            throw new InvalidArgumentException('A preset reference is one non-empty line.');
        }

        if ($this->comment !== null && (trim($this->comment) === '' || str_contains($this->comment, Lines::LINE_BREAK))) {
            throw new InvalidArgumentException('A rule comment is one non-empty line.');
        }
    }

    public function target(): FileTarget
    {
        return RenovateConfigFile::target($this->createAs);
    }

    public function apply(?string $content): ?string
    {
        // Without a resolved path the creation candidate's grammar applies; the engine calls applyAt() with the real one.
        return $this->applyAt($this->target()->candidates()[0], $content);
    }

    public function applyAt(Path $path, ?string $content): ?string
    {
        if (RenovateConfigFile::isJsonc($path)) {
            throw new RuntimeException(sprintf('"%s" is a JSONC config, which this standard does not manage; use strict JSON in "renovate.json" or JSON5 in "renovate.json5".', $path->value()));
        }

        if (RenovateConfigFile::isJson5($path)) {
            return Json5ListWriter::ensureEntry($content ?? '', self::SECTION, $this->preset, $this->comment);
        }

        // A project without a renovate config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        if ($content === null) {
            return JsonObjectWriter::ensureListEntry('{}', [self::SECTION], $this->preset) . Lines::LINE_BREAK;
        }

        $this->assertStrictJson($path, $content);

        return JsonObjectWriter::ensureListEntry($content, [self::SECTION], $this->preset);
    }

    public function description(): string
    {
        return sprintf('Ensures the renovate config extends "%s".', $this->preset);
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no renovate config yet; one is created extending "%s".', $this->preset);
        }

        if ($this->comment !== null && str_contains($content, $this->preset)) {
            return sprintf('The "%s" entry is not annotated with the enforced comment.', $this->preset);
        }

        return sprintf('The renovate config does not extend "%s".', $this->preset);
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
}
