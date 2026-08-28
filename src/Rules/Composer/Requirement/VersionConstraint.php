<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Composer\Requirement;

use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * A version constraint as written in a manifest's requirement sections.
 * A constraint meets a minimum when nothing it allows can resolve below that minimum's own lowest version, so a project is free to move ahead to a newer major.
 * A constraint naming a branch resolves to a version outside every numeric range, and so meets no minimum at all.
 */
final readonly class VersionConstraint
{
    /** Composer accepts both spellings of the alternative separator. */
    private const string ALTERNATIVES = '/\s*\|\|?\s*/';

    private const string JOIN = ' || ';

    /** The operator synthesizing "at least this version" when measuring against a minimum. */
    private const string AT_LEAST = '>=';

    /** The Intervals::get() bucket holding the numeric ranges (the other bucket holds branch constraints). */
    private const string NUMERIC_INTERVALS = 'numeric';

    private function __construct(
        private string $value,
        private ConstraintInterface $parsed,
    ) {}

    public static function fromString(string $value): self
    {
        try {
            return new self($value, new VersionParser()->parseConstraints($value));
        } catch (UnexpectedValueException $exception) {
            throw new InvalidArgumentException(sprintf('"%s" is not a version constraint composer can parse.', $value), 0, $exception);
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    /** The lowest version this constraint can resolve to, or null when it names only a branch. */
    public function lowestVersion(): ?string
    {
        $numeric = Intervals::get($this->parsed)[self::NUMERIC_INTERVALS];

        return $numeric === [] ? null : $numeric[0]->getStart()->getVersion();
    }

    public function meets(self $minimum): bool
    {
        $lowest = $minimum->lowestVersion() ?? throw new InvalidArgumentException(sprintf('"%s" states no lowest version, so nothing can be measured against it.', $minimum->value));

        return Intervals::isSubsetOf($this->parsed, new VersionParser()->parseConstraints(self::AT_LEAST . $lowest));
    }

    /**
     * This constraint with every alternative that reaches below $minimum replaced by it, the rest kept verbatim.
     * Nothing already meeting the minimum is dropped, so a project allowing a newer major keeps it and only the parts below the standard are raised.
     * A constraint that already meets the minimum comes back as it was written, down to its separator spelling: normalizing is only ever collateral of a raise.
     */
    public function raisedTo(self $minimum): self
    {
        if ($this->meets($minimum)) {
            return $this;
        }

        $alternatives = array_map(
            fn(string $alternative): string => self::fromString($alternative)->meets($minimum) ? $alternative : $minimum->value,
            preg_split(self::ALTERNATIVES, $this->value) ?: [$this->value],
        );

        return self::fromString(implode(self::JOIN, array_unique($alternatives)));
    }
}
