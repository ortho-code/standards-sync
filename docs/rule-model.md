# Direction: a rule-based model

**Status:** built through the first R3 members (2026-07-17) — the engine folds rules per [the `Rule` contract](#decided-2026-07-15-r0--the-rule-contract); the rule library covers the block family and the PHPStan family (import, level floor, pins). The Rector family is designed (see [the 2026-07-23 decision](#decided-2026-07-23-r3-second-tool--the-rector-family)) and next to build. The pre-rename history (baseline and earlier takes) is preserved in the private predecessor repository.

## The goal

Share coding-standards config across many repos from one package, keep it updated automatically (no manual copy-paste), and support the full range of config a project accumulates — `.editorconfig`, `.gitignore`, static-analysis configs (PHPStan / Rector / …), framework config, and more — each with its own format and mechanism.

## Three tiers of sharing (by what the format allows)

- **Tier A — native import / extends.** The format can reference shared config (PHPStan `includes`, Rector `require`, ESLint import, tsconfig `extends`, framework config imports, composer-required PHP config). Sharing = ship the real config in the package + a one-line import in the repo; it rides `composer update` with no markers, no sync step, no drift. Biggest surface, cheapest mechanism.
- **Tier B — structured merge.** Parseable files with no import (`composer.json` / `package.json` scripts, XML configs): own specific keys, leave the rest.
- **Tier C — opaque text.** No import, no structure to key on (`.editorconfig`, `.gitignore`): managed marker blocks or line-level rules. Smallest, lowest-velocity tier.

Priority is A → B → C by value. The first build targeted C (managed blocks); the direction below unifies all three under one abstraction.

## The `Rule` abstraction

A `Rule` is the unifying primitive. It targets one file and **applies** itself as a pure content transform — idempotent, a no-op when already satisfied. Drift (`--check`) is **derived**, not hand-written: a rule is satisfied exactly when applying it changes nothing (see the pre-R0 decisions below). Rules compose by folding over a file's content one after another, with a single final write (the existing pure pipeline is kept).

Rule *types* map onto the tiers:

- **`ManagedBlockRule`** — places a template as a managed marker block. This is the current block engine, reframed as one rule type; it handles Tier C today.
- **`ImportRule`** — ensures a one-line import / require / extends is present (Tier A). *(Realized per tool from R2 on — `PhpStanImportRule` first, with "import" as the family's generic term; see the R2 decision.)*
- **`KeyRule` / structured rules** — read / check / set a specific key in a parseable file (Tier B). These are **value-aware**, not blind sets: e.g. a *floor* — "PHPStan level ≥ 6: raise it if lower, leave it if already higher" (never weakens a stricter project) — plus list-unions, ceilings, enforce-presence, enforce-absence. This is where rules beat both a dumb copy (which would *downgrade* a level-9 project) and a plain import (which ships a default the consumer can silently drop below — a floor rule *enforces* the minimum). Each such rule does **narrow, targeted** parsing of its one key with existing format libraries; there is **no universal parse-and-merge framework** (that was the weight that sank the prior attempt — see [prior-approaches.md](prior-approaches.md)).

New rule types — and eventually community-contributed ones, as with the Rector / ECS rule libraries — extend the set without touching the pipeline.

Value-aware rules are the real payoff of Tier B and a strong argument for the whole direction; "raise PHPStan level only if lower" is a good first Tier B target once the abstraction exists.

**Positioning — "floors, not copies" (2026-07-15).** This is the product pitch and the README's opening example once docs land. It also explains in one line why the alternatives fall short: a copy *downgrades* a stricter project, an import ships a default the consumer can silently drop below — a floor rule *enforces* the minimum.

## Prior art (studied)

Three mature PHP tools converge on the same decomposition, deliberately split across separate seams rather than one fat interface:

- **Rector** — `RectorInterface` = `getNodeTypes()` (what to act on) + `refactor()` (act, or return `null` for "no change"). Configurability is a *separate* `ConfigurableRectorInterface::configure(array)`. The description + a mandatory before/after code sample live on yet another interface. Ordering: none — it re-runs to a fixpoint, so rules must be idempotent.
- **PHP-CS-Fixer** — `FixerInterface` = `isCandidate()` / `supports()` (cheap gate) + `fix()` (mutate in place) + `getDefinition()` (mandatory doc + sample) + `getPriority()`. Config via a *separate* `ConfigurableFixerInterface` with a self-describing, validated schema. Ordering: a single global integer priority — its known pain point.
- **PHP_CodeSniffer** — `Sniff` = `register()` (token types) + `process()` (report through the file, don't return findings). Config via plain public properties set by reflection from `ruleset.xml`. Ordering: none — analyzers don't interfere.

Convergent lessons for our `Rule`:

1. **Keep the base contract tiny** — target + `check` + `apply`. Config and docs go on separate seams, not the base.
2. **Separate `check` from `apply`** — `check` gives drift / `--check`; `apply` is idempotent and no-ops when already satisfied. *(Superseded 2026-07-15: we derive check from apply instead of hand-writing it — see the pre-R0 decisions below. The lesson's substance, apply no-oping when satisfied, stands.)*
3. **Config is opt-in** (`ConfigurableRule`), passed whole (replace-not-patch), defaults owned by the rule, and **typed / validated** (a per-rule immutable value object) rather than an untyped array.
4. **Each rule carries a description + a before/after sample**, which doubles as its test fixture.

## Decided 2026-07-15 (pre-R0) — contract, identity, absence

Settled in discussion ahead of R0; the R0 contract below turned these into the actual interface, tested against the three probes.

### No hand-written `check()` — drift derives from a pure `apply`

`Rule` has no `check()` method. The rule is one pure content transform; the engine derives drift as `apply(current) !== current`, so check and apply cannot disagree by construction.

- Why: the prior-art tools have no check method either (Rector's `refactor()` returns "no change"); a hand-written check can disagree with its apply — the rule reports "satisfied" while apply would still change the file, and CI flaps. Deriving removes that bug class.
- Free generic property test: `apply(apply(x)) === apply(x)` — the engine asserts idempotency over every rule's fixtures with no per-rule test code.
- Dry-run stays structural, not a feature: the pipeline computes the whole plan in memory and `Engine::apply` is the only writer, so `--check` runs every rule's transform without touching disk.
- Every rule carries a mandatory static **description** (what it enforces — feeds the drift report, the docs generation, and the prior-art "description + sample" lesson). A dynamic **`explain(current)`** ("level 5 is below the floor of 6") is opt-in for rules whose drift isn't self-evident from the diff; the computed diff is the fallback.
- Rejected: a mandatory `check()` on the base contract (prior-art lesson 2 read literally) — see above.

### Rule identity = the class name

- Config and disable lists use the FQCN (`PhpStanMinLevel::class`) — refactor-safe, IDE-autocompleted, and no parallel registry of string IDs to keep in sync (rejected: a string-ID registry is a second source of truth).
- Human-facing output (drift report, generated docs) uses the short class name, plus the target path when one class has several instances: `PhpStanMinLevel (projects/ai/phpstan.neon)`.
- Trade-offs, accepted with their fixes:
  - Renaming a rule class breaks consumers' disable lists → a rename is a major version bump; optionally keep a deprecated class alias for one release.
  - One class instantiated several times (a key rule for two keys): disabling by class disables all instances → if a real per-instance case appears, add per-instance identity keyed on class + target path. Don't build it now.

### File-level absence: deferred, kept representable

Content-level enforce-absence (remove a key / line / block) is just another transform and needs nothing special. Whole-file removal is out of scope for now: `ChangeKind` has no delete case and the engine only ever writes.

- Candidate mechanism, the symmetric null: `apply(?string $content): ?string`, where null in = file absent (creation needs that anyway) and null out = file should not exist. **Confirmed at R0**, with the primary justification shifted from future deletion to day-one abstention — see the R0 contract below.

## Decided 2026-07-15 (R0) — the `Rule` contract

Designed on paper against the three probes; R1 builds it. The pre-R0 decisions above stand; where a detail is superseded, its entry says so.

### The contract

```php
interface Rule
{
    /** The file this rule transforms, as ordered path candidates. */
    public function target(): FileTarget;

    /**
     * Pure, idempotent transform from current content to desired content.
     * Null in: the file does not exist. Null out: the file should not exist.
     */
    public function apply(?string $content): ?string;

    /** One line stating what this rule enforces; feeds the drift report and the generated docs. */
    public function description(): string;
}

/** Opt-in seam for rules whose drift is not self-evident from the diff. */
interface ExplainsDrift
{
    /** Why the content drifts, e.g. "level 4 is below the minimum of 6". */
    public function explain(?string $content): string;
}
```

How the engine uses it:

- **The fold.** Group rules by resolved target, then fold each file's rules in declaration order over the current content; one `Change` per file, `Engine::apply` stays the only writer, `--check` stops before writing. Rules never touch the filesystem — the engine reads each file once and passes content in, which is what keeps a rule testable as a plain string-to-string function.
- **`ChangeKind` derives from the fold's endpoints**: null → string is Create, changed string → string is Update, unchanged is InSync. String → null is delete — refused at plan time with a clear error until a delete branch (`ChangeKind::Delete`, a delete-capable `Filesystem` port) exists.
- **Per-rule drift attribution is free.** The fold records each rule's before/after; rule *i* drifted exactly when it changed the running content. The report can name the drifting rules per file (short class name + target, `description()`, `explain()` where implemented) — as informative per rule as hand-written checks would have been, which closes the last gap of the derived-check decision. *(Refined 2026-07-16, first dogfood run: indistinguishable instances — same class, description and explanation, e.g. a same-label override where both rules drift — collapse into one report line with a count. Per-instance origin identity, i.e. which rule set contributed the rule, is deferred; candidate shape when a real need appears: a `--verbose` report naming the contributing rule set.)*
- **`target()` lives on the rule, not in external pairing.** How a rule answers it is its own business: file-specific rules hardcode it (an `.editorconfig` rule cannot be pointed at `phpstan.neon` by construction); only genuinely file-generic rules (`ManagedBlockRule`) take the path as constructor input. Config that pairs paths with rules externally was rejected precisely because it allows that mismatch.

### `FileTarget` — ordered path candidates

Real repos vary their config filenames (`phpstan.neon` vs `phpstan.dist.neon` vs `phpstan.neon.dist`), and the first intended consumer uses a `.dist` variant. So this went into the contract now rather than being deferred: changing the base interface later breaks every rule ever written, and today zero rules exist.

```php
/**
 * The file a rule targets, as ordered path candidates (the tool's own precedence).
 * The engine resolves it to the first candidate that exists, or the first if none do.
 */
final readonly class FileTarget
{
    /** @param non-empty-list<Path> $candidates */
    private function __construct(private array $candidates)
    {
    }

    public static function fromString(string $path): self
    {
        return self::fromStrings($path);
    }

    public static function fromStrings(string ...$candidates): self
    {
        // Path::fromString each; require at least one; reject absolute paths.
    }

    /** @return non-empty-list<Path> */
    public function candidates(): array
    {
        return $this->candidates;
    }
}
```

- **`target()` is a declaration, not a location.** The rule cannot return a resolved `Path`: rules are pure (no filesystem access to check what exists), and one rule instance fans across every configured root, where different candidates may exist — so the resolved path is a property of (rule, root, disk state). It lives on the plan's `Change::path()`, as in the baseline; `apply()` never sees a path at all.
- **Candidates are name variants, not format alternatives (2026-07-16).** One rule's candidates name the same file and format under the tool's different filenames (`phpstan.neon` vs `phpstan.dist.neon`); format alternatives (Symfony `foo.php` / `foo.yaml` / `foo.xml`) are separate per-format rules — see the note under open choice 1. Consequence, held loosely: `ManagedBlockRule` requires all candidates to derive one comment syntax and refuses a mixed list at construction, because `apply()` cannot know which candidate resolved, making per-candidate syntax inexpressible — refusing loudly beats guessing the first candidate. Revisit against a real same-format case that needs differing syntaxes (that would be a per-candidate contract seam). Related known gap: `ManagedBlockRule` creates on absence, so "block into whichever name exists, create nothing" would additionally need an abstain-when-absent construction flag — also deferred until a real case.
- Resolution happens at plan time (the engine owns the filesystem port): the first existing candidate wins; when none exist, the first candidate is the target — which only matters for rules that create, since abstaining rules return null anyway. Rules with different candidate lists that resolve to the same file fold together.
- `fromString()` is the one-element case of `fromStrings()`: one resolution code path, no special case.
- The factories accept strings and parse at the boundary (the codebase precedent: `Path::fromString`, `SyncConfig::withRoots`); the object stores and exposes `Path`. `FileTarget` adds an invariant `Path` alone does not carry: targets must be relative, because they fan across roots.
- Each tool's rule family encodes its candidate list once (a phpstan helper returning the list above in phpstan's own precedence order) — an R3 detail, recorded so nobody re-derives per-tool precedence per rule.

### Symmetric null confirmed — abstention first, deletion later

The pre-R0 candidate is confirmed, with the primary justification changed: **null out is needed on day one, and deletion is the minor use.** The real one is abstention on absent files. `PhpStanMinLevel` on a repo with no phpstan config, or an `ImportRule` whose lone import line would not be a valid config file, must be able to say "no file, no opinion": `apply(null)` returns null, the file stays absent and counts as in sync. With a `string` return type that is inexpressible — returning `''` would create an empty file. Only Tier C block rules create from nothing; Tier A/B rules mostly abstain. *(Corrected at R2: the ImportRule half of that example was wrong twice — a phpstan config holding only an `includes:` section is valid, and the import rule now creates it (see the R2 decision). The abstention capability stands unchanged, with `PhpStanMinLevel` as its first real user: a floor on an absent config has nothing to raise. The fold composes the two when the import rule is declared first — the min-level rule then receives the created content, not null.)*

Deletion proper (string in, null out) stays refused until a delete branch exists. And the input `?string` had to be in the contract from day one regardless: widening a parameter type later breaks every implementor, while widening a return type would have been backward-compatible — that asymmetry made this the moment to decide.

### Disabling rules — FQCN, then a predicate; no hash

Consumer-side disabling is not built yet; the mechanism is decided now so the contract needs no retrofit. The ladder:

1. `withoutRule(PhpStanMinLevel::class)` — an FQCN list, the common case: "don't enforce this kind of rule".
2. Class + target path, if instances on different files ever need distinguishing (also the report's identity format).
3. A predicate, for full precision — config is PHP: `withoutRules(fn (Rule $rule): bool => ...)`. It can match on config through the rule's getters, which makes config-sensitivity visible and chosen.

Overriding follows the same seam (recorded 2026-07-17): config is code, so replacing an org rule's config — a custom target location, different import — is `withoutRule(X::class)` + `addRule(new X(...))`; rules stay immutable, nothing mutates an org-owned instance. A rule whose target genuinely varies per project may additionally expose an optional constructor override defaulting to the tool's shared target — build that the first time a real project needs it.

Rejected: matching by a hash of the rule's data (value equality), even as internal machinery behind a readable `withoutRule(class, config)` call. Rules are immutable value objects, so value equality is conceptually sound — but it rots at the wrong moment. A consumer disables a rule because they cannot meet the org minimum yet; that intent is class-level. When the org package raises the minimum from 6 to 7, a value-based match silently stops matching and the rule re-enables itself exactly when the standard got harder — nobody decided that. It also needs a canonical serialization of rule state (reflection over private properties, or an `equals()`/`fingerprint()` on the contract) for a case the predicate covers with zero machinery. Deduplication needs no equality either: folding an identical rule twice is a no-op because apply is idempotent.

### The three probes, and what each proved

- **`ManagedBlockRule`** (Tier C) — the current block engine as one rule: create the file as just the block, replace an existing block in place, or append the block. Proves creation from nothing. Same-label merging (`LineUnionMerger`) moves to rule *construction* inside the block family; the contract is untouched by it. *(Refined at R1: the merger machinery was deleted instead; same-label composition is last-wins via the fold — see the R1 decision below.)*
- **`ImportRule`** (Tier A) — abstains on null, otherwise ensures the import line is present via a targeted insert. Proves abstention and the narrow edit without parse-dump. *(Revised at R2: it creates the config instead of abstaining; abstention's real probe becomes `PhpStanMinLevel`.)*
- **`PhpStanMinLevel`** (Tier B) — abstains on null; reads the configured level; at or above the minimum it returns the content **unchanged**, so the derived check reports in-sync for free; below it, a targeted replace of just that value. Implements `ExplainsDrift`. Proves value-awareness and format preservation. (Renamed from `PhpStanLevelFloor`: the class name says plainly what it does; "floors, not copies" stays as pitch language in prose.)

## Decided 2026-07-16 (R1) — same-label composition is last-wins; the merger machinery is deleted

R1 mapped the baseline onto the contract and hit the one piece that did not carry over: the `ContentMerger` seam (`SingleSpecMerger`, `LineUnionMerger`, `ContentMergerRegistry`), which merged same-label contributions into one block before rendering.

- Half of it is free in the fold: a later same-label `ManagedBlockRule` replaces the earlier rule's block in place, so "child wins wholesale" is emergent behaviour — what `SingleSpecMerger` did, with zero machinery.
- The `.gitignore` line-*union* does not survive the fold (replacement clobbers). Preserving it would have needed a pre-fold coalesce step whose only user was a case with no fixture, no scenario, and no consumer. Deleted with the seam.
- **Union returns opt-in, never as a default flip.** When the block family splits per format (open choice 1), a `GitignoreRule` brings union back as chosen construction behaviour, with an engine-side pre-fold coalesce step as the expected mechanism for cross-set contributions. The same-label default stays last-wins: a consumer relying on last-wins to override a block must not silently start unioning when union arrives.
- Until then, a child set that wants extra lines repeats them or uses its own label. Accepted while pre-release with no consumers: the workaround is config-only and converges on the next sync.
- The planned `ContentMergerRegistry::default()` → `createDefault()` ride-along rename is subsumed by the deletion.

## Decided 2026-07-17 (R2) — the import family is per-tool

"Import" is the family's generic term (the tier is native *import*/extends); the classes are per tool: `PhpStanImportRule` first, later members per tool (`RectorImportRule`, a tsconfig-extends rule, …). The formats proved not format-generic — a neon list entry, a PHP import line and a JSON key are three different edits with three different already-present checks — so the open-choice-1 hedge ("a single rule + small applier where genuinely format-generic") resolves to per-tool classes for this family too.

- Rejected: one generic `ImportRule` + applier registry. The registry's only job would be rediscovering the file type the author already declared in the target, and a free target invites pointing a neon edit at `rector.php` — the mismatch per-tool classes make inexpressible (the same argument that put `target()` on the rule at R0).
- Noted for later, deliberately not forgotten: a generic `ImportRule` convenience on top of the per-tool classes may still come if the tail of tools grows long — build it over several real members, never first.
- `PhpStanImportRule` behaviour: target candidates in phpstan's own precedence (`phpstan.neon`, `phpstan.neon.dist`, `phpstan.dist.neon`), shared via `PhpStanConfigFile::target()` so no phpstan rule re-derives them; absent file → **the config is created**, holding just the import (revised from abstain during the build: enforcing the standard is the point and `withoutRule()` is the opt-out; the created config has no `paths:` — deliberately, the project maintainer owns their own parameters); missing `includes:` section → created at the top of the file (phpstan convention, and prepending disturbs nothing); already-present check = trimmed entry match with surrounding quotes stripped, inside the block-form section only (targeted edit, no parse-dump); an `includes:` in any other form (inline list) is refused at plan time with a convert-to-block-form message; inserted entries copy the section's existing indentation, else the file's detected indent, else a tab (neon's documented default); implements `ExplainsDrift`.
- **Rule library layout (settled with R2):** concrete rules live under a top-level `Rules/` tree — Rector's engine-vs-rules split — grouped per mechanism (`Rules/Block/`, which also hosts the marker machinery) or per tool (`Rules/PhpStan/`). deptrac forbids `Core → Rules`, so the engine provably never references a concrete rule; shared per-tool knowledge sits in the tool's directory (`PhpStanConfigFile`). Text primitives (`Lines::LINE_BREAK` + `split`/`join` as the LF-convention seam, `Indent::TAB`) live in `Core/Text/`; format defaults reference them instead of restating the character.

## Decided 2026-07-17 (R3, first member) — `PhpStanMinLevel` reads only what is written

The first value-aware rule, and the first real abstainer. Its semantics against phpstan's include-merge behaviour (the including file's scalar parameters override included ones):

- A written `level` below the floor is raised by a targeted replace of just the value (indentation and a trailing comment survive); at or above the floor — including `max` — the content returns unchanged, so in-sync falls out of the derived check.
- **A missing `level:` line follows the author's `OnMissing` choice** (`Core/Rule/OnMissing`, deliberately generic from day one — every future value-aware rule faces the same question). `Skip` (the default) leaves it to whatever owns the effective value: the rule is include-blind by design (resolving `includes:` needs filesystem access, breaking purity), and in an import-based setup the shared ruleset ships with the floor as its own level — pinned by a test in the org package, not the engine. `Write` writes the floor (first child of `parameters:`, or a new section at the end) for setups where nothing else supplies a level, which otherwise runs phpstan at its default level 0 with the floor silently unenforced. Skip is the default because writing is the dangerous direction: the project file wins phpstan's merge, so blindly writing the floor could *lower* a stricter imported level.
- Only the level line directly under `parameters:` counts (matched on the section's child indentation) — a nested `level` key from an extension (`type_coverage:`) is never touched.
- Composition with the import rule is fold order: the import rule declared first creates a missing config, and the floor rule then judges real content instead of abstaining (pinned by the `phpstan-family` scenario).
- **`PhpStanLevel` value object**: the level grammar — bounded 0..10, the `max` alias, quoted config values, refusal of unjudgeable values (`%level%`) — lives in one value object (`fromInt()`, `createMax()`, `fromConfigValue()`, `isAtLeast()`), because a bare `int` could not express a floor of `max` and left the grammar scattered as primitives in the rule.
- **`PhpStanPinnedValues` (2026-07-17)** — the third strength between import-defaults and bounds: *pin exact values the project may not override*. The import alone cannot stop overrides (the project file wins phpstan's merge); a pin rewrites deviations on every sync. Configured as a `PinnedValues` value object (nested string keys, scalar leaves — bool/int/string; list pinning is a separate union-semantics discussion, deferred). A pin always writes — no `OnMissing` (writing is its point) — and an absent config is created holding the pins. No `ExplainsDrift`: a pinned-value diff is self-evident. Org-author guidance, worth repeating wherever pins are documented: defaults belong in the imported ruleset; pin only what must not be overridden.
- **`Formats/` — format machinery is its own layer (2026-07-17).** The pin rule's editing mechanics (find a section, walk a key path, replace or insert a scalar line, create missing keys as a section's first child, missing top-level sections appended at the end) are facts about neon, not about phpstan, and they serve multiple rule families — unlike the block family's marker machinery, which has one family and lives with it. They live in `Formats/Neon/NeonScalarWriter`, a new deptrac layer: `Rules → Formats → Core`, and `Core ↛ Formats` keeps the engine format-blind. Still targeted line edits — no parse, no reserialization (the recorded anti-goal). Fail-loud where a scalar pin meets a section or a section path meets an inline value. `PhpStanMinLevel` migrates onto it when next touched.

## Decided 2026-07-23 (R3, second tool) — the Rector family

The PHPStan trio implemented a second time, deliberately, as an abstraction stress test: a second tool family shows what is tool-shaped versus general. Designed against the verified config surface of Rector 2.5.7 (read from the installed source, not recall):

- Config discovery: `--config`/`-c`, else `rector.php`, else `rector.dist.php` — two candidates, that order (`RectorConfigsResolver`), shared as `RectorConfigFile::target()`.
- The config file returns a callable. The fluent `return RectorConfig::configure()->…;` form is the documented canon (`RectorConfigBuilder` implements `__invoke(RectorConfig)`); the older `return static function (RectorConfig $rectorConfig): void {…}` remains valid — Rector's own set files use it.
- `->withSets([...])` takes file-path strings (the official `SetList` constants are path constants) — the native Tier A import: an org ships a set file, the consumer references it by path as one array entry.
- Five list-based level methods (`withTypeCoverageLevel`, `withDeadCodeLevel`, `withCodeQualityLevel`, `withCodingStyleLevel`, `withTypeCoverageDocblockLevel`): a natural int with no upper bound and no `max` alias, purely additive (a level registers the first N rules of a fixed per-category list), overshooting the list is a console warning, never an error. `withPhpLevel` is excluded from the family: it resolves the consumer's composer.json at runtime — not a statically-floorable surface.

The decisions:

- **Fluent-only scope.** A callable-style root config is refused at plan time with a convert-to-`RectorConfig::configure()` message — the inline-`includes:` precedent. Rejected: managing both styles (editing `$rectorConfig->sets([...])` too) — double machinery before a real repo demonstrates it cannot convert.
- **`RectorImportRule`** mirrors the R2 doctrine: an absent file is created as the canonical fluent skeleton holding just the import, deliberately without `withPaths()` (the maintainer owns their parameters, as with the created phpstan config); a chain without `withSets` gains the call appended at the end of the chain; a block-form array gains the entry, matched on trimmed entry text with no quote-stripping (entries are PHP expressions like `__DIR__ . '/vendor/…'`, not bare strings — a deliberate difference from the neon rule, recorded so nobody "fixes" it); an inline single-line array is refused with a convert-to-block-form message.
- **`RectorMinLevel`** is one class over a `RectorLevelCategory` enum (the five list-based categories, mapping to the method names); the floor is a plain validated natural int. Rejected: five near-identical per-category classes, and a `RectorLevel` value object for symmetry — `PhpStanLevel` earns its existence through grammar (the `max` alias, quoted values, 0–10 bounds); Rector's level grammar is a bare natural int, so a VO would wrap no invariant. **Finding: level value objects are tool-shaped; there is no generic level to extract.**
- **`OnMissing` default is `Write` — the phpstan default inverted, by the tools' semantics.** Phpstan defaulted to `Skip` because writing was the dangerous direction (the project scalar wins the include-merge, so a written floor could lower a stricter imported level). Rector registration is additive: a written level call can only add rules, and a missing call means the category's rules are silently off — exactly what a floor exists to prevent. **Finding: `OnMissing` generalizes as a seam; its default is per-tool judgment.**
- **`RectorPinnedCall`** pins an exact fluent call by method name: a deviating call is rewritten to the pinned text, a missing call is appended. The pin concept shifts shape per tool (phpstan: key path + scalar leaf; rector: whole-call text); matching is textual, so formatting deviations count as drift and are normalized. Known limit, accepted: a pin cannot prevent a consumer's `withSkip()` of imported rules — enforcing the absence of specific `withSkip` entries would be a separate enforce-absence rule when a real need appears.
- **`Formats/Php/FluentChainWriter`** carries the shared mechanics — locate a call in a possibly multiline chain, replace a scalar argument, insert a block-form array entry, append a call before the terminating `;`, fail loud on anything unrecognizable. The second `Formats/` member, confirming the layer (`Rules → Formats → Core` unchanged). Still targeted line edits; no parse, no reserialization.

Two predictions confirmed at the fact level before any code: `FileTarget`'s ordered candidates fit Rector's two-name discovery exactly (R0), and the import family's per-tool split holds — a PHP array-entry edit shares nothing with a neon list edit (R2).

## Open choices — settled at R0 (2026-07-15)

1. **Split "what" from "how", or self-contained per-format rules?** The cleaner answer: instead of one generic `ManagedBlockRule` plus a separate apply-strategy, have **per-format rule classes** — `EditorConfigRule`, `GitignoreRule`, … — each self-contained, baking its format knowledge in (sharing a common `AbstractBlockRule` for the marker mechanics). The format-specific class *is* the "how", so no separate strategy layer or `applicable()` pairing is needed, and it grows well — a contributor adds a `FooRule` for a new format. Caveat: for format-*generic* families (an `ImportRule` that varies only by a small syntax detail across `phpstan.neon` / `rector.php`), N per-format classes are overkill — there a single rule with a tiny format-applier is lighter. **Decided:** per rule *family* — per-format classes for the block family (resolves the split cleanly); a single rule + small applier only where a family is genuinely format-generic. Note `.editorconfig` and `.gitignore` currently do identical block-placement, so they can start on one shared base and split when real format-specifics appear (editorconfig key-merge, gitignore line-union).
   **Extended 2026-07-16 — multi-format config, rule families, and the block-format guard.** One concern across config formats (Symfony `foo.php` / `foo.yaml` / `foo.xml`) = one rule per format, each abstaining while its file is absent (the symmetric null), so the repo's chosen format is acted on and the others stay silent; the org package decides which format's rule creates when none exist. Declaring the same values per format would drift and a format could be forgotten, so a *family factory* declares the config once and returns the per-format rule set. A first-class rule-family concept between `RuleSet` and `Rule` is deferred until factories prove insufficient (family-level disabling or reporting would be the trigger). The what/how split stays rejected; the guard against invalid pairings is that the block family refuses formats it cannot mark: `json` (no comments at all) and `xml`/`html` (only enclosed `<!-- -->` comments — the marker grammar draws comment-*lead* lines, and for xml the generic block mechanics are structurally wrong anyway: create-from-nothing yields a rootless fragment, append-at-end lands after the root close tag, so structured rules are the right tool there). Unknown extensions keep the hash default, because opaque-text co-management is an open set (`.editorconfig`, `.gitignore`). Expected trigger for enclosed-comment support: managed README sections — markdown uses `<!-- -->` markers and append-at-end is valid there; that adds a comment *tail* to the marker grammar, and formats then leave the refusal list case by case.
2. **Ordering.** Rules can share a file. A global integer priority is the known pain point. **Decided:** the fold runs in declaration order (rule-set composition order, matching the block engine's later-wins precedence). Idempotent rules on disjoint concerns make order irrelevant; where rules genuinely overlap, declaration order is the deterministic tiebreak. No priority integer, no dependency graph.
3. **Config shape.** **Decided:** typed constructor parameters (`new PhpStanMinLevel(minLevel: 6)`) — the rule *is* immutable config + behaviour, which satisfies the prior-art "typed / validated" lesson without a separate seam. Adopt a `ConfigurableRule` seam or self-describing schema only if a rule's config gets rich.
4. **Removal vs disabling vs un-applying a dropped rule** — three distinct things, worth keeping apart:
   - **Disable a rule** = stop enforcing it: do nothing, and leave whatever is already in the file untouched (matches every prior-art tool — non-registration / `severity=0`). No provenance, no undo.
   - **A "remove" rule** = a rule whose `apply()` enforces *absence* (delete a key / line / block if present, no-op if not). This is a first-class, idempotent operation — enforce-absence is just the mirror of enforce-presence — and needs no provenance.
   - **Un-applying a *dropped* rule** = undoing the past effect of a rule no longer configured at all. *This* is the hard one that needs provenance (a state file), because nothing in the config still describes what to undo.
   Note: disabling a parent rule stops it *re-adding* its content but does **not** clean up what it already added — deleting that is a separate "remove" rule (or dropped-rule undo). **Decided:** enforce-presence + enforce-absence rules are plain transforms (nothing special needed); consumer-side disabling follows the ladder in the R0 contract (FQCN list, then a predicate — no hash); dropped-rule undo (state file) stays deferred until manual cleanup is a burden. Whole-file removal is the refused null output — see the R0 contract.

## Testing rules

Carry the fixture discipline forward from the block engine: each rule ships a **before/after sample**, which doubles as its test fixture (Rector refuses to define a rule without one). Concretely, the existing scenario suite (`input/` → `expected/` fixtures synced and asserted) becomes **per-rule fixtures** — one small before/after case per behaviour the rule has (from-scratch, already-satisfied/idempotent, and value-aware edges like "already higher, leave it"). The rule is the unit; its fixtures are the behaviour catalog and the worked examples for consumers. Any change to a rule's behaviour adds / updates / removes its fixture.

**The rule catalog docs are generated from the fixtures (decided 2026-07-15).** The unit of documentation is the *scenario*, not the rule: the generator renders each rule's description plus **all** its scenarios as before/after pairs — unlike Rector's single code sample, which often can't show a rule's whole point. Required shape: each scenario's kebab-case directory name becomes its example heading; an optional one-line caption file covers cases where the name isn't enough. One source feeds tests and docs, so neither can rot alone. The generator itself is part of the docs phase, but the fixture shape is binding now so nothing needs restructuring later.

## Sequence

- **R0 — done (2026-07-15): the `Rule` contract above**, designed against three probes at once — `ManagedBlockRule` (Tier C), `ImportRule` (Tier A), and `PhpStanMinLevel` (Tier B, the only tier that must *read* current values; the floor is its hardest representative, a blind key-set being a degenerate case) — so the contract isn't block-shaped or write-only. The open choices are settled and the symmetric null is confirmed.
- **R1 — done (2026-07-16): the baseline refactored onto `Rule`**, with the block work as `ManagedBlockRule` and the engine owning resolve-group-fold. The scenario suite and the pipeline tests carried over. `RuleSetInterface` became `RuleSet` (the other ports are suffix-less); the `ContentMergerRegistry::default()` rename was subsumed by the merger deletion (see the R1 decision above).
- **R2 — done (2026-07-17): `PhpStanImportRule` (Tier A)** — the import family went per-tool (see the R2 decision), the rule library moved under `Rules/` with a deptrac-checked `Core ↛ Rules` edge, and create-on-absent replaced abstention for imports. Stress-tested the abstraction on a targeted single-line edit; the contract needed no change.
- **R3 — structured / key rules (Tier B)**, one format at a time. **Started 2026-07-17: `PhpStanMinLevel` + the `PhpStanLevel` value object are done** (see the R3 decision); further members follow as real needs appear.
  - **Next (agreed 2026-07-22): the Rector rule family** — the PHPStan trio (import / level floor / pin) implemented a second time against `rector.php`, deliberately: a second tool family shows what is tool-shaped versus general, and any split/move/rewrite it forces in existing machinery is part of the phase, not scope creep. **Designed 2026-07-23** against Rector 2.5.7's verified config surface — behaviours, findings, and rejected alternatives in [the Rector-family decision](#decided-2026-07-23-r3-second-tool--the-rector-family); the build is the next step.
- **R4 (later) — a state file** for clean removal, only if manual cleanup proves a burden. Scope note (2026-07-23, from the Flex study in [prior-art.md](prior-art.md)): marker blocks carry their own provenance — the label suffices to un-apply Tier C — so the state file is only needed for unmarked edits (Tier A/B); `symfony.lock` (per-package content ref + touched-files list, with cross-package reference counting before any delete) is the proven shape to copy.
