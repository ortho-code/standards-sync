# standards-sync

Keeps shared config files in sync across repositories. An org package declares *which* files to sync and *what* they contain; this engine understands the formats and writes them — today as managed marker blocks, with drift detection via `sync --check`.

**Status: pre-release.** The rule-based model is built: the engine folds pure `Rule` transforms per file. The rule library ships the managed marker block mechanism (`ManagedBlock`) and per-tool families for PHPStan, Rector, ECS, Psalm, composer.json, Deptrac, Renovate and PHPUnit. Org packages declare their standard against the `Authoring/` layer: a class extending `Standard`, whose rules read and reference the package's distributed `templates/` content through the self-locating `Package`. Every shipped rule is documented with real before/after examples in the generated [rule catalog](docs/rules/README.md); the design record lives in [`docs/`](docs/README.md).

## Usage (current state)

A consumer keeps a `standards-sync.php` that returns a `SyncConfig`, then:

```
vendor/bin/standards-sync sync            # apply the managed blocks
vendor/bin/standards-sync sync --check    # report drift, exit non-zero, write nothing
```

## License

[MIT](LICENSE)
