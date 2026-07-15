# Architecture — a pure plan pipeline with a single writer

```
standards-sync.php (returns SyncConfig)
  → RuleSet::specs(): FileSpec[]          relative paths; composition = hierarchy
  → FileSpecResolver: specs × roots, group by (root, path), merge same-label via ContentMerger
  → DesiredFile{ path, ManagedBlock[] }   one per (root, path)
  → Differ: pick a FileRenderer by file type, render desired vs current-on-disk → Change (per FILE)
  → Plan{ Change[] }
  → Engine::apply(Plan)   the ONLY writer      |     --check → report Plan.drift(), exit 1 on drift
```

Hexagonal-light (not full DDD; this is a transform pipeline, not a domain) — one I/O port, `Filesystem`, and the layers `deptrac` enforces:

- **`Core/`** — the pure pipeline above: ports (the `Filesystem` interface) and value objects. Imports no framework, only itself; `deptrac` forbids any `Core → Vendor` edge.
- **`Infrastructure/`** — driven adapters and framework-backed I/O. `SymfonyFilesystem` and `InMemoryFilesystem` implement the `Filesystem` port (the in-memory one backs disk-free syncs — tests, previews); `TemplateDirectory` reads an org package's `templates/` assets at config-build time.
- **`Presentation/Cli/`** — the driving adapter: the `symfony/console` `Application` / `SyncCommand`, plus the `DriftReport` presenter.
- **`Testing/`** — shipped test scaffolding. Framework-neutral: `SyncTester` (sync in memory → result map, for presence checks), `SyncFixtureTester` (sync an on-disk fixture → the `Mismatch`es, for exact before/after checks), and `Mismatch`. Plus `ScenarioTestCase`, a phpunit base over `SyncFixtureTester` — a consumer extends it, returns `scenarios()`, and each fixture (in a `fixtures/` dir beside the test class) is synced and asserted. That phpunit reference is the one `Testing → Vendor` edge; phpunit stays `require-dev` (these classes load only under test).
- **Composition root.** The CLI constructs `SymfonyFilesystem` and hands it to `Engine::create($filesystem)`; `Core` receives the port and never instantiates an adapter.

Two extension seams, open/closed:

- **FileRenderer** (`supports(DesiredFile): bool`, `render(DesiredFile, ?string $current): string`) — one renderer per file algorithm.
  Only `BlockRenderer` (marker blocks) ships today; JSON-merge / XML / import-shim renderers slot in later without touching the pipeline.
- **ContentMerger** — folds same-label contributions into one block in declaration order (child overrides parent).
  Only `SingleSpecMerger` + `LineUnionMerger` ship today; a key-aware `.editorconfig` merger arrives with hierarchy layering.

## Invariants (easy to violate — hold these)

- **Pure pipeline, single writer.** Everything up to `Plan` is side-effect-free. `Engine::apply` is the only code that writes files. `plan()` / `--check` never write.
- **The file is the planning unit, not the spec.** One `Change` per file; `label` lives on a `ManagedBlock` inside the file. This is what lets two packages (or two hierarchy layers) co-manage one file without clobbering.
- **Config returns a value.** `standards-sync.php` returns a `SyncConfig` (Rector/ECS-style) — no singleton, no side effects, re-entrant for multi-root.
- **Do NOT reintroduce a singleton.** The legacy singleton + `require_once`-the-config + write-during-run are gone on purpose; they block multi-root and `--check`.
- **Markers always present** for managed files. First sync of a file with no block yet: an absent file becomes the block, an existing file keeps its content and gains the block at the end (append). Once markers exist, only the region between them is rewritten. There is no whole-file replace — the block is the ownership boundary, so the engine never discards content outside it.
- **Filesystem is the only I/O in the pipeline.** The pure pipeline never reads or writes directly — it goes through the `Filesystem` port; only the adapter touches disk. Config-build-time template reading (`TemplateDirectory`) sits outside the pipeline.
- **Core imports no framework.** Adapters are wired at the composition root (the CLI), never inside `Core`; a `Core → Vendor` edge fails `composer deptrac`.
- **Value objects only where there's an invariant or behaviour** — `Path` (join/normalise) and `Label` (restricted charset protects the marker regex). Don't wrap invariant-free strings: `content` stays a string.
