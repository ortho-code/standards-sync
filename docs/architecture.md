# Architecture — a pure plan pipeline with a single writer

```
standards-sync.php (returns SyncConfig)
  → RuleSet::rules(): Rule[]              declaration order; composition = hierarchy
  → Engine::plan: per root, resolve every rule's FileTarget (first existing candidate),
    group rules by resolved file, fold apply() in declaration order
  → Change (per FILE: kind from the fold's endpoints, plus per-rule attribution)
  → Plan{ Change[] }
  → Engine::apply(Plan)   the ONLY writer      |     --check → report Plan.drift(), exit 1 on drift
```

Hexagonal-light (not full DDD; this is a transform pipeline, not a domain) — one I/O port, `Filesystem`, and the layers `deptrac` enforces:

- **`Core/`** — the pure pipeline above: the `Rule` contract, the engine, ports (the `Filesystem` interface), value objects, and the text primitives (`Core/Text/`: `Lines` as the LF-convention seam, `Indent`). Imports no framework, only itself; `deptrac` forbids any `Core → Vendor` edge.
- **`Formats/`** — format-editing machinery shared across rule families (`Formats/Neon/`: `NeonScalarWriter`). Depends on `Core` only; `deptrac` forbids `Core → Formats`, so the engine stays format-blind. Machinery used by a single family stays with that family (the block markers).
- **`Rules/`** — the shipped rule library (Rector's engine-vs-rules split), grouped per mechanism (`Rules/Block/`: `ManagedBlockRule` plus the marker machinery) or per tool (`Rules/PhpStan/`: import, level floor, pinned values, `PhpStanConfigFile`). Depends on `Core` and `Formats`; `deptrac` forbids `Core → Rules`, so the engine provably never references a concrete rule.
- **`Infrastructure/`** — driven adapters and framework-backed I/O. `SymfonyFilesystem` and `InMemoryFilesystem` implement the `Filesystem` port (the in-memory one backs disk-free syncs — tests, previews); `TemplateDirectory` reads an org package's `templates/` assets at config-build time.
- **`Presentation/Cli/`** — the driving adapter: the `symfony/console` `Application` / `SyncCommand`, plus the `DriftReport` presenter.
- **`Testing/`** — shipped test scaffolding. Framework-neutral: `SyncTester` (sync in memory → result map, for presence checks), `SyncFixtureTester` (sync an on-disk fixture → the `Mismatch`es, for exact before/after checks), and `Mismatch`. Plus `ScenarioTestCase`, a phpunit base over `SyncFixtureTester` — a consumer extends it, returns `scenarios()`, and each fixture (in a `fixtures/` dir beside the test class) is synced and asserted. That phpunit reference is the one `Testing → Vendor` edge; phpunit stays `require-dev` (these classes load only under test).
- **Composition root.** The CLI constructs `SymfonyFilesystem` and hands it to `new Engine($filesystem)`; `Core` receives the port and never instantiates an adapter.

Extension seams, open/closed:

- **Rule** (`target(): FileTarget`, `apply(?string): ?string`, `description(): string`) — the unifying primitive; new rule types extend the set under `Rules/` without touching the pipeline.
  `ManagedBlockRule` (marker blocks) and `PhpStanImportRule` (native import) ship today; value-aware rules follow (see [rule-model.md](rule-model.md)).
- **ExplainsDrift** — opt-in seam for rules whose drift is not self-evident from the diff; the drift report calls it per drifting rule.

## Invariants (easy to violate — hold these)

- **Pure pipeline, single writer.** Everything up to `Plan` is side-effect-free. `Engine::apply` is the only code that writes files. `plan()` / `--check` never write.
- **Rules are pure.** A rule never touches the filesystem: the engine reads each file once and passes content in; `apply()` never sees a path. `target()` is a declaration (ordered candidates), not a location — the resolved path is a property of (rule, root, disk state) and lives on `Change::path()`.
- **The file is the planning unit, not the rule.** One `Change` per file; rules sharing a resolved target fold together in declaration order. This is what lets two packages (or two hierarchy layers) co-manage one file without clobbering.
- **Deletion is refused.** `apply()` may return null (the file should not exist), but a string → null fold endpoint fails at plan time with a clear error until a delete branch (`ChangeKind::Delete`, a delete-capable `Filesystem` port) exists. Null → null is abstention: no `Change` at all.
- **Config returns a value.** `standards-sync.php` returns a `SyncConfig` (Rector/ECS-style) — no singleton, no side effects, re-entrant for multi-root.
- **Do NOT reintroduce a singleton.** The legacy singleton + `require_once`-the-config + write-during-run are gone on purpose; they block multi-root and `--check`.
- **Markers always present** for managed files. First sync of a file with no block yet: an absent file becomes the block, an existing file keeps its content and gains the block at the end (append). Once markers exist, only the region between them is rewritten. There is no whole-file replace — the block is the ownership boundary, so the engine never discards content outside it.
- **Filesystem is the only I/O in the pipeline.** The pure pipeline never reads or writes directly — it goes through the `Filesystem` port; only the adapter touches disk. Config-build-time template reading (`TemplateDirectory`) sits outside the pipeline.
- **Core imports no framework.** Adapters are wired at the composition root (the CLI), never inside `Core`; a `Core → Vendor` edge fails `composer deptrac`.
- **Core never references a concrete rule.** The engine works against the `Rule` contract only; a `Core → Rules` edge fails `composer deptrac` — the open/closed promise, checked.
- **Value objects only where there's an invariant or behaviour** — `Path` (join/normalise), `Label` (restricted charset protects the marker regex) and `FileTarget` (relative-only ordered candidates). Don't wrap invariant-free strings: `content` stays a string.
