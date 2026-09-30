<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** Decodes a single value from its source text: a plain, quoted or block scalar, or a flow collection. */
final readonly class YamlScalarDecoder
{
    /** The key the source is decoded under, as a document of one entry. */
    private const string KEY = 'value';

    public static function decode(string $source): mixed
    {
        try {
            $decoded = Yaml::parse(self::KEY . ': ' . $source);
        } catch (ParseException $exception) {
            throw new RuntimeException(sprintf('"%s" is not a YAML value: %s', $source, $exception->getMessage()), 0, $exception);
        }

        return is_array($decoded) ? $decoded[self::KEY] ?? null : null;
    }
}
