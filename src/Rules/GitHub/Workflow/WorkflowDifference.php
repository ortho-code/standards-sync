<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use Closure;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlEntry;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlItem;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\YamlFragment;
use OrthoCode\StandardsSync\Formats\Yaml\YamlTreeWriter;
use RuntimeException;

/**
 * One way a project's workflow falls short of the declared one: why, in words a maintainer reads, and the edit that resolves it.
 * A difference sync cannot edit away resolves by refusing, with what to write instead.
 */
final readonly class WorkflowDifference
{
    /** @param Closure(string): string $resolve */
    private function __construct(
        private string $explanation,
        private Closure $resolve,
    ) {}

    /** @param list<string|int> $mappingPath */
    public static function missingEntry(array $mappingPath, string $place, YamlTree $declared, YamlEntry $entry): self
    {
        $fragment = YamlFragment::fromEntry($declared, $entry);

        return new self(
            sprintf('It does not have "%s" yet.', self::within($place, $entry->key())),
            static fn(string $content): string => YamlTreeWriter::addEntry($content, $mappingPath, $fragment),
        );
    }

    /**
     * @param list<string|int> $path
     * @param bool $keepComment whether the comment after the project's value stays, which it does unless it belonged to that value
     */
    public static function changedScalar(array $path, string $place, string $actual, string $declared, bool $keepComment = true): self
    {
        return new self(
            sprintf('It has "%s" as %s where the standard declares %s.', $place, $actual, $declared),
            static fn(string $content): string => YamlTreeWriter::replaceScalar($content, $path, $declared, $keepComment),
        );
    }

    /**
     * A version the project holds below the declared minimum, or one that names no version to compare with it.
     *
     * @param list<string|int> $path
     * @param bool $orderable whether the project's value names a version at all
     * @param bool $keepComment whether the comment after the project's value stays, which it does unless it belonged to that value
     */
    public static function belowMinimum(array $path, string $place, string $actual, string $declared, bool $orderable, bool $keepComment): self
    {
        return new self(
            $orderable
                ? sprintf('It has "%s" as %s, below the declared %s.', $place, $actual, $declared)
                : sprintf('It has "%s" as %s, which names no version to compare with the declared %s.', $place, $actual, $declared),
            static fn(string $content): string => YamlTreeWriter::replaceScalar($content, $path, $declared, $keepComment),
        );
    }

    /**
     * @param non-empty-list<string|int> $path
     * @param YamlEntry $entry the declared entry whose value replaces the project's
     */
    public static function changedValue(array $path, string $place, YamlTree $declared, YamlEntry $entry): self
    {
        $fragment = YamlFragment::fromValue($declared, $entry);

        return new self(
            sprintf('Its "%s" differs from the one the standard declares.', $place),
            static fn(string $content): string => YamlTreeWriter::replaceValue($content, $path, $fragment),
        );
    }

    /** @param list<string|int> $path */
    public static function missingListItem(array $path, string $place, string $item): self
    {
        return new self(
            sprintf('Its "%s" does not list %s yet.', $place, $item),
            static fn(string $content): string => YamlTreeWriter::appendScalarItem($content, $path, $item),
        );
    }

    /**
     * @param list<string|int> $stepsPath
     * @param string|null $after the id of the declared step it goes after, null for a first step
     */
    public static function missingStep(array $stepsPath, int $index, string $job, YamlTree $declared, YamlItem $step, string $id, ?string $after): self
    {
        $fragment = YamlFragment::fromItem($declared, $step);

        return new self(
            sprintf('The job "%s" does not run the step "%s" yet; it goes %s.', $job, $id, $after === null ? 'first' : sprintf('after "%s"', $after)),
            static fn(string $content): string => YamlTreeWriter::insertItem($content, $stepsPath, $index, $fragment),
        );
    }

    /**
     * @param list<string|int> $stepPath
     * @param string $idSource the declared id as its source reads
     */
    public static function unidentifiedStep(array $stepPath, string $job, string $id, string $idSource): self
    {
        $fragment = YamlFragment::fromScalarEntry(WorkflowSyntax::KEY_ID, $idSource);

        return new self(
            sprintf('A step of the job "%s" is the declared step "%s" without its id, and gains it.', $job, $id),
            static fn(string $content): string => YamlTreeWriter::addEntry($content, $stepPath, $fragment),
        );
    }

    public static function reorderedStep(string $job, string $id, string $after): self
    {
        return self::refusal(sprintf(
            'The job "%s" runs the step "%s" before "%s", which the standard declares first; move "%s" after "%s" and sync again.',
            $job,
            $id,
            $after,
            $id,
            $after,
        ));
    }

    public static function unwritableShape(string $place, string $actual, string $declared): self
    {
        return self::refusal(sprintf('It has "%s" as %s where the standard declares %s; write it as %s and sync again.', $place, $actual, $declared, $declared));
    }

    /** @param list<string|int> $mappingPath */
    public static function retiredEntry(array $mappingPath, string $place, string $key): self
    {
        return new self(
            sprintf('It still has "%s", which the standard no longer declares.', $place),
            static fn(string $content): string => YamlTreeWriter::removeEntry($content, $mappingPath, $key),
        );
    }

    /** @param list<string|int> $stepsPath */
    public static function retiredStep(array $stepsPath, string $job, string $id, int $index): self
    {
        return new self(
            sprintf('The job "%s" still runs the step "%s", which the standard no longer declares.', $job, $id),
            static fn(string $content): string => YamlTreeWriter::removeItem($content, $stepsPath, $index),
        );
    }

    /**
     * @param list<string|int> $listPath
     * @param mixed $item the item as the project's list decodes it
     */
    public static function retiredItem(array $listPath, string $place, mixed $item, string $spelled): self
    {
        return new self(
            sprintf('Its "%s" still lists %s, which the standard no longer declares.', $place, $spelled),
            static fn(string $content): string => YamlTreeWriter::removeScalarItem($content, $listPath, $item),
        );
    }

    /**
     * A retired node that is the last its holder has: the holder takes the value the standard declares for it now.
     *
     * @param non-empty-list<string|int> $holderPath
     * @param string $retired what is retired, as the explanation names it: a place in quotes, or items and the place holding them
     * @param YamlEntry $entry the holder as the standard declares it now
     */
    public static function retiredLast(array $holderPath, string $holderPlace, string $retired, YamlTree $declared, YamlEntry $entry): self
    {
        $fragment = YamlFragment::fromValue($declared, $entry);

        return new self(
            sprintf('It still has %s, which the standard no longer declares, so "%s" takes the value it declares now.', $retired, $holderPlace),
            static fn(string $content): string => YamlTreeWriter::replaceValue($content, $holderPath, $fragment),
        );
    }

    /**
     * A retired node inside a value the writer cannot take it out of.
     *
     * @param string $retired what is retired, as the explanation names it: a place in quotes, or items and the place holding them
     */
    public static function retiredInline(string $retired, string $holderPlace): self
    {
        return self::refusal(sprintf(
            'It still has %s, which the standard no longer declares, inside "%s", which is written in brackets or braces and cannot be edited by sync; write "%s" as an indented block and sync again.',
            $retired,
            $holderPlace,
            $holderPlace,
        ));
    }

    /** @param string $retired what is retired, as the explanation names it: a place in quotes, or items and the place holding them */
    public static function unretractable(string $retired): self
    {
        return self::refusal(sprintf('It still has %s, which the standard no longer declares, and taking it out would leave its holder with nothing the standard declares; remove it by hand and sync again.', $retired));
    }

    public function explanation(): string
    {
        return $this->explanation;
    }

    public function resolved(string $content): string
    {
        return ($this->resolve)($content);
    }

    private static function refusal(string $explanation): self
    {
        return new self($explanation, static function (string $content) use ($explanation): never {
            throw new RuntimeException($explanation);
        });
    }

    private static function within(string $place, string $key): string
    {
        return $place === '' ? $key : $place . '.' . $key;
    }
}
