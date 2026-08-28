# standards-sync

Keeps shared config files in sync across repositories. An org package declares *which* files to sync and *what* they contain; this engine understands the formats and writes them — managed marker blocks, native imports, and enforced values, with drift detection via `sync --check`.

**Status: pre-release** (v0.x, no compatibility promise yet — see [distribution](docs/distribution.md)).

## Floors, not copies

A shared standard usually travels as a copied config file or an importable default, and both leak: a copy *downgrades* a stricter project the moment it lands, and an import ships a default the consumer can silently drop below. This engine's value rules are **floors** — a project below the minimum is raised on the next sync, a stricter project is never touched. A PHPStan level floor of 6 raises a project at level 4, leaves a project at level 8 alone, and creates a config carrying the floor where none exists. The studied alternatives and their trade-offs are in [prior-art.md](docs/history/prior-art.md).

## Using a standard

A consumer requires its organisation's standards package as a dev dependency (that package depends on this engine), keeps a `standards-sync.php` returning a `SyncConfig` that registers the org's rule set, and runs:

```
vendor/bin/standards-sync sync            # apply the standard
vendor/bin/standards-sync sync --check    # report drift, exit non-zero, write nothing
```

Every sync writes plain files into the working tree, so the result is reviewed like any other diff. `sync --check` in CI turns the standard from advisory into enforced: a repo that drifts fails its own pipeline.

## Authoring a standard

An org package requires `ortho-code/standards-sync`, extends `Standard`, declares rules per file, and ships shared content under `templates/`, which rules reach through the self-locating `Package`. The rule library covers managed marker blocks (any comment-bearing file: `.editorconfig`, `.gitignore`, CI workflows) plus per-tool families for PHPStan, Rector, ECS, Psalm, composer.json, Deptrac, Renovate and PHPUnit. The full guide: [authoring-org-packages.md](docs/authoring-org-packages.md).

Managed blocks are labelled marker regions — the inside is owned by the standard and synced, everything outside belongs to the repo:

```
# >>> acme-standards (managed) >>>
...synced content...
# <<< acme-standards <<<
```

## Documentation

- [Rule catalog](docs/rules/README.md) — every shipped rule, with real before/after examples generated from the test suite.
- [Authoring org packages](docs/authoring-org-packages.md) · [Architecture](docs/architecture.md) · [Design record index](docs/README.md).

## Contributing

`docs/` is the living design record — read [conventions.md](docs/conventions.md) and [architecture.md](docs/architecture.md) before changing the engine. The scenario suite is the behaviour catalog: any change to pipeline behaviour adds, updates, or removes the matching fixture. Validate with `composer app-run-tests` and `composer deptrac`.

## License

[MIT](LICENSE)
