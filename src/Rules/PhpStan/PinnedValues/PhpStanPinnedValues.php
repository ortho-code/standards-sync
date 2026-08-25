<?php

declare(strict_types=1);

namespace StandardsSync\Rules\PhpStan\PinnedValues;

use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\Rule\Rule;
use StandardsSync\Formats\Neon\NeonScalarWriter;
use StandardsSync\Rules\PhpStan\PhpStanConfigFile;

/**
 * Pins exact values in the PHPStan config: deviations are rewritten on every sync, so consumers cannot override them.
 * Defaults a project may override belong in the imported shared ruleset instead — pin only what must not be overridden.
 * A pin always writes: a project without a PHPStan config gets one created holding the pinned values.
 */
final readonly class PhpStanPinnedValues implements Rule
{
    public function __construct(private PinnedValues $values)
    {
    }

    public function pinnedValues(): PinnedValues
    {
        return $this->values;
    }

    public function target(): FileTarget
    {
        return PhpStanConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        $result = $content ?? '';
        foreach ($this->values->leaves() as $pin) {
            $result = NeonScalarWriter::write($result, $pin->path(), $pin->value());
        }

        return $result;
    }

    public function description(): string
    {
        $paths = array_map(static fn (PinnedValue $pin): string => $pin->dottedPath(), $this->values->leaves());

        return sprintf('Pins the PHPStan config values: %s.', implode(', ', $paths));
    }
}
