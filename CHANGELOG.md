# Changelog

What changed in each release, for the projects consuming the standard through an org package.

## 0.3.0 — 2026-09-29

**Breaking:** a composer script's declared commands no longer make up the whole script.
Every declared command is kept present — a missing one is inserted after the command declared before it — and every other command in the script is the project's and stays where the project put it.
Standards declaring the same script now add to it in declaration order instead of the later one replacing the earlier, so a standard declared beside another can add a step to that one's aggregate.

**A new file to commit: `standards-sync.lock`.** Where a standard declares composer scripts, the sync writes it beside `standards-sync.php`, recording what the standards declared.
A later sync uses it to remove a command the standards stop declaring, with any arguments the project added to it, while the project's own commands stay.
A missing lock is drift, so the first `sync --check` after upgrading fails until you run `sync` once and commit the lock.

**Adopting over scripts you already have:** your old command stays after the declared one — `["phpstan analyse", "php vendor/bin/phpstan --memory-limit=256M"]` runs the analyser twice — so delete your own line wherever it runs the same tool; the drift report names every line it kept.

**Also new:** `ComposerScript` takes `acceptsArguments: true`, letting a project add arguments after a declared command and still count as running it; a script no standard declares any more is reported once and left in place; and rule authors get the `ContributesToList` seam, through which a rule contributes entries to a list the project shares.

## 0.2.1 — 2026-08-29

A version constraint that names only a branch is now accepted where it was previously refused: branches have no ordering to floor, so the declared one is pinned instead, and a project on another branch is rewritten to it. This is what lets a standard require a package that publishes no releases — a security-advisories package, for instance. Constraints naming versions are unchanged.

## 0.2.0 — 2026-08-28

**Breaking:** the opening marker of a managed block changed from `# >>> <label> (managed) >>>` to `# >>> <label> - managed >>>`. The parentheses make the line unparseable for tools that read a config file with PHP's INI parser, which is how some tools read `.editorconfig`.

A block written by 0.1.0 is not recognised by this version, so a sync appends a new block beside the old one instead of updating it. Delete the old block before syncing.

## 0.1.0 — 2026-08-27

First public release: managed marker blocks plus per-tool rule families for PHPStan, Rector, ECS, Psalm, composer.json, Deptrac, Renovate and PHPUnit, with drift detection via `sync --check`. Org packages author against the `Authoring` layer — see the [README](README.md) and the [rule catalog](docs/rules/README.md).

Pre-release semantics: v0 minors may break — see [distribution](docs/distribution.md).
