<?php

declare(strict_types=1);

namespace StandardsSync\Rules\PhpStan\PinnedValues;

use InvalidArgumentException;

/**
 * The values a rule pins in a config, declared as a nested array of string keys with scalar leaves.
 * Validation happens here once, so rules work with typed PinnedValue leaves instead of walking a raw array.
 */
final readonly class PinnedValues
{
    /** @param non-empty-list<PinnedValue> $leaves */
    private function __construct(private array $leaves)
    {
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        if ($values === []) {
            throw new InvalidArgumentException('Pinned values cannot be empty.');
        }

        return new self(self::flatten($values, []));
    }

    /** @return non-empty-list<PinnedValue> */
    public function leaves(): array
    {
        return $this->leaves;
    }

    /**
     * @param non-empty-array<mixed, mixed> $values
     * @param list<string> $path
     * @return non-empty-list<PinnedValue>
     */
    private static function flatten(array $values, array $path): array
    {
        $leaves = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException(sprintf('Pinned keys must be non-empty strings; got "%s" under "%s".', (string) $key, implode('.', $path)));
            }
            $keyPath = [...$path, $key];
            if (is_array($value)) {
                if ($value === []) {
                    throw new InvalidArgumentException(sprintf('The pinned section "%s" is empty.', implode('.', $keyPath)));
                }
                array_push($leaves, ...self::flatten($value, $keyPath));
                continue;
            }
            if (!is_bool($value) && !is_int($value) && !is_string($value)) {
                throw new InvalidArgumentException(sprintf('Pinned values must be bool, int or string; "%s" is %s.', implode('.', $keyPath), get_debug_type($value)));
            }
            $leaves[] = new PinnedValue($keyPath, $value);
        }

        return $leaves;
    }
}
