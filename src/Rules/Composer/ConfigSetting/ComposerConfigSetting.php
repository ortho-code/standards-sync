<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Composer\ConfigSetting;

use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Json\JsonObjectWriter;
use OrthoCode\StandardsSync\Rules\Composer\ComposerManifest;
use InvalidArgumentException;

/**
 * Pins one setting under the composer manifest's config key: a deviating value is rewritten on every sync, so a project cannot override it.
 * Composer's config values have no ordering to floor, so this enforces the declared value outright — what a project may choose belongs outside the standard.
 * A root without a manifest is not a composer project, so the rule abstains rather than creating one.
 */
final readonly class ComposerConfigSetting implements Rule
{
    /** Composer's own spelling for a setting nested under another, as `composer config` takes it. */
    private const string SEPARATOR = '.';

    /** @var non-empty-list<string> */
    private array $segments;

    public function __construct(
        private string $setting,
        private string|bool|int $value,
    ) {
        $segments = explode(self::SEPARATOR, $setting);
        if (array_any($segments, static fn (string $segment): bool => trim($segment) === '')) {
            throw new InvalidArgumentException(sprintf('"%s" is not a composer config setting: every segment of a dotted name needs a key.', $setting));
        }

        $this->segments = $segments;
    }

    public function target(): FileTarget
    {
        return ComposerManifest::target();
    }

    public function apply(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return JsonObjectWriter::write($content, [ComposerManifest::CONFIG_SECTION, ...$this->segments], $this->value);
    }

    public function description(): string
    {
        return sprintf('Pins the composer config setting "%s" to %s.', $this->setting, json_encode($this->value, JSON_THROW_ON_ERROR));
    }
}
