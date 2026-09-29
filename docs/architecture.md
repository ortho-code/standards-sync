# Architecture — a pure plan pipeline with a single writer

```
standards-sync.php (returns SyncConfig)
  → RuleSet::rules(): Rule[]              declaration order; composition = hierarchy
  → Engine::plan: per root, read standards-sync.lock, resolve every rule's FileTarget
    (TargetResolver: a lone existing candidate wins; a dist file beats a shadowing non-dist;
    same-side ambiguity refuses), group rules by resolved file, merge each file's contributions
    to one list (ContributesToList) into one rule, hand each contribution its retired entries
    from the lock, fold apply() in declaration order
  → Change (per FILE: kind from the fold's endpoints, plus per-rule attribution)
    or Abstention (the file is absent and no rule wanted it: reported, never written)
    + the root's lock as one more Change, recording what its contributions declared,
      and a ForgottenList per list it recorded that nothing contributes to now
  → Plan{ Change[], Abstention[], ForgottenList[] }
  → Engine::apply(Plan)   the ONLY writer      |     --check → report Plan.drift(), exit 1 on drift
```

Hexagonal-light (not full DDD; this is a transform pipeline, not a domain) — one I/O port, `Filesystem`, and the layers `deptrac` enforces:

- **`Core/`** — the pure pipeline above: the `Rule` contract, the engine, ports (the `Filesystem` interface), value objects, the lock (`Core/Lock/SyncLock`, the one file the engine owns end to end, so it renders it with `json_encode` rather than through `Formats/`), and the text primitives (`Core/Text/`: `Lines` as the LF-convention seam, `Indent`). Imports no framework, only itself; `deptrac` forbids any `Core → Vendor` edge.
- **`Formats/`** — format-editing machinery shared across rule families (`Formats/Neon/`: `NeonScalarWriter`; `Formats/Php/`: `FluentChainWriter`; `Formats/Xml/`: `XmlElementWriter`; `Formats/Json/`: `JsonObjectWriter`; `Formats/Yaml/`: `YamlListWriter`). Depends on `Core` only; `deptrac` forbids `Core → Formats`, so the engine stays format-blind. Machinery used by a single family stays with that family (the block markers). A format's conventions live here too (`FluentChainWriter::INDENT`), and so do its canonical renderings with the validation that protects them (`DirAnchoredEntry` refusing expression text at construction); `Core/Text/` stays character-level.
- **`Rules/`** — the shipped rule library (Rector's engine-vs-rules split): tool directories (`Rules/PhpStan/`, `Rules/Rector/`) plus `Rules/General/` for tool-agnostic mechanisms, each holding one folder per rule with the rule class and its supporting classes (`Rules/PhpStan/MinLevel/`: the rule + `PhpStanLevel`; `Rules/General/ManagedBlock/`: `ManagedBlock` + the marker machinery), and shared per-tool knowledge at the tool root (`PhpStanConfigFile`, `RectorConfigFile`). Depends on `Core` and `Formats`; `deptrac` forbids `Core → Rules`, so the engine provably never references a concrete rule. One scoped exception: `src/Rules/Composer` is its own `ComposerRules` layer, additionally allowed `Vendor`, because composer's own semver library is the authority on version constraints and composer is not an optional tool — every consumer installed this engine with it. The general `Rules` layer excludes that directory, so every other family stays provably vendor-free (see [composer-family.md](history/composer-family.md)).
- **`Authoring/`** — config-build-time support for org packages, outside the pipeline: `Package` (the org package as installed in the consumer — locates itself through composer's install record, reads distributed `templates/` content, renders consumer-root-relative references) and `Standard` (the base rule set whose `enforce()` receives the located package). Depends on `Core` and vendor code (`Composer\InstalledVersions`, symfony path utilities).
- **`Infrastructure/`** — driven adapters and framework-backed I/O. `SymfonyFilesystem` and `InMemoryFilesystem` implement the `Filesystem` port (the in-memory one backs disk-free syncs — tests, previews).
- **`Presentation/Cli/`** — the driving adapter: the `symfony/console` `Application` at its root, commands under `Command/`, and the `DriftReport` presenter under `Output/`.
- **`Testing/`** — shipped test scaffolding. Framework-neutral: `SyncTester` (sync in memory → result map, for presence checks), `SyncFixtureTester` (sync an on-disk fixture → the `Mismatch`es, for exact before/after checks), and `Mismatch`. Plus `ScenarioTestCase`, a phpunit base over `SyncFixtureTester` — a consumer extends it, returns `scenarios()`, and each fixture (in a `fixtures/` dir beside the test class) is synced and asserted. That phpunit reference is the one `Testing → Vendor` edge; phpunit stays `require-dev` (these classes load only under test).
- **Composition root.** The CLI constructs `SymfonyFilesystem` and hands it to `new Engine($filesystem)`; `Core` receives the port and never instantiates an adapter.

Extension seams, open/closed:

- **Rule** (`target(): FileTarget`, `apply(?string): ?string`, `description(): string`) — the unifying primitive; new rule types extend the set under `Rules/` without touching the pipeline.
  `ManagedBlock` (marker blocks), the PHPStan family (included ruleset, level floor, pins), and the Rector and ECS base sets ship today (see [rule-model.md](history/rule-model.md)).
- **ExplainsDrift** — opt-in seam for rules whose drift is not self-evident from the diff; the drift report calls it per drifting rule.
- **ContributesToList** — opt-in seam for rules contributing entries to a list; the engine merges contributions to one list in one resolved file through the rule's own `withMerged()` before the fold, so a standard declared beside another adds to what that one declares, records their `entries()` in the root's lock, and hands each the entries it stopped declaring through `withRetired()`. `ComposerScript`, `PhpStanIncludedRuleset`, `RectorBaseSet` and `EcsBaseSet` use it.

## Invariants (easy to violate — hold these)

- **Pure pipeline, single writer.** Everything up to `Plan` is side-effect-free. `Engine::apply` is the only code that writes files. `plan()` / `--check` never write.
- **Rules are pure.** A rule never touches the filesystem: the engine reads each file once and passes content in; `apply()` never sees a path. `target()` is a declaration (ordered candidates), not a location — the resolved path is a property of (rule, root, disk state) and lives on `Change::path()`.
- **The file is the planning unit, not the rule.** One `Change` per file; rules sharing a resolved target fold together in declaration order. This is what lets two packages (or two hierarchy layers) co-manage one file without clobbering.
- **File deletion is refused; content removal is not.** `apply()` may return null (the file should not exist), but a string → null fold endpoint fails at plan time with a clear error until a delete branch (`ChangeKind::Delete`, a delete-capable `Filesystem` port) exists. Null → null is abstention: no `Change` at all. Removing a key, line or block *within* a file needs none of that machinery — it is an ordinary content transform, enforce-absence being the mirror of enforce-presence. An abstention is reported rather than dropped: it produces no `Change`, no drift and no exit-code effect, but the rules that stood down are named in the plan's notes, so a declared rule never does nothing unseen.
- **Config returns a value.** `standards-sync.php` returns a `SyncConfig` (Rector/ECS-style) — no singleton, no side effects, re-entrant for multi-root.
- **Do NOT reintroduce a singleton.** The legacy singleton + `require_once`-the-config + write-during-run are gone on purpose; they block multi-root and `--check`.
- **Markers always present** for managed files. First sync of a file with no block yet: an absent file becomes the block, an existing file keeps its content and gains the block at the end (append). Once markers exist, only the region between them is rewritten. There is no whole-file replace — the block is the ownership boundary, so the engine never discards content outside it.
- **What the engine recognises is only what it currently writes.** Every rule that edits in place finds its own prior output by matching the rendered form — a marker pair drawn from the current grammar, an import entry matched verbatim. Nothing records what an earlier version wrote, and nothing retracts. So a change to *how* output is rendered does not update existing output, it stops recognising it: the fold reads the file as unmanaged and appends a second copy beside the stale first, silently. That makes rendering a compatibility surface rather than an implementation detail, and it covers more than it looks — marker decoration, the spelling of a verbatim-matched entry, and the referenced path inside one all sit on it, whether the change comes from the engine or from an org package moving a template. Treat any such change as breaking, with a hand migration, until the deferred work in [roadmap.md](roadmap.md) removes the coupling.
  Rules contributing to a list (`ContributesToList`) are the exception: the lock records what they declared, so an entry they change or stop declaring is retracted on the next sync rather than left beside its successor.
- **Filesystem is the only I/O in the pipeline.** The pure pipeline never reads or writes directly — it goes through the `Filesystem` port; only the adapter touches disk. Config-build-time work (`Authoring/`: package self-location, distributed-content reading) sits outside the pipeline.
- **Core imports no framework.** Adapters are wired at the composition root (the CLI), never inside `Core`; a `Core → Vendor` edge fails `composer deptrac`.
- **Core never references a concrete rule.** The engine works against the `Rule` contract only; a `Core → Rules` edge fails `composer deptrac` — the open/closed promise, checked.
- **Value objects only where there's an invariant or behaviour** — `Path` (join/normalise), `Label` (restricted charset protects the marker regex) and `FileTarget` (relative-only ordered candidates). Don't wrap invariant-free strings: `content` stays a string.
