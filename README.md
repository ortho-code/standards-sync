# standards-sync

Keeps shared config files in sync across repositories. An org package declares *which* files to sync and *what* they contain; this engine understands the formats and writes them — today as managed marker blocks, with drift detection via `sync --check`.

**Status: pre-release.** The rule-based model is built: the engine folds pure `Rule` transforms per file, with managed marker blocks as the first rule type (`ManagedBlockRule`). Next up are native-import rules and value-aware rules ("PHPStan level is a floor: raise it if lower, never touch a stricter project"). The design record lives in [`docs/`](docs/README.md).

## Usage (current state)

A consumer keeps a `standards-sync.php` that returns a `SyncConfig`, then:

```
vendor/bin/standards-sync sync            # apply the managed blocks
vendor/bin/standards-sync sync --check    # report drift, exit non-zero, write nothing
```

## License

[MIT](LICENSE)
