# Changelog

What changed in each release, for the projects consuming the standard through an org package.

## Unreleased

**Breaking:** the opening marker of a managed block changed from `# >>> <label> (managed) >>>` to `# >>> <label> - managed >>>`. The parentheses make the line unparseable for tools that read a config file with PHP's INI parser, which is how some tools read `.editorconfig`.

A block written by 0.1.0 is not recognised by this version, so a sync appends a new block beside the old one instead of updating it. Delete the old block before syncing.

## 0.1.0 — 2026-08-27

First public release: managed marker blocks plus per-tool rule families for PHPStan, Rector, ECS, Psalm, composer.json, Deptrac, Renovate and PHPUnit, with drift detection via `sync --check`. Org packages author against the `Authoring` layer — see the [README](README.md) and the [rule catalog](docs/rules/README.md).

Pre-release semantics: v0 minors may break — see [distribution](docs/distribution.md).
