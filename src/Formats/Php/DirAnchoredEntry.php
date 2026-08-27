<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Php;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use InvalidArgumentException;

/**
 * The canonical config entry referencing a consumer-root-relative path: `__DIR__ . '/<path>'`, resolved by the tool where __DIR__ is the consumer project root.
 * Expression text in the path is refused at construction, beside the rendering it would break: a quote would end the rendered string early, and __DIR__ means the caller passed PHP instead of data.
 */
final readonly class DirAnchoredEntry
{
    private function __construct(private Path $path)
    {
    }

    public static function fromRelativeString(string $path): self
    {
        $relative = Path::fromRelativeString($path);
        if (str_contains($relative->value(), '__DIR__') || str_contains($relative->value(), "'") || str_contains($relative->value(), '"')) {
            throw new InvalidArgumentException('Pass a plain relative path; the entry renders the PHP expression.');
        }

        return new self($relative);
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function value(): string
    {
        return "__DIR__ . '/" . $this->path->value() . "'";
    }
}
