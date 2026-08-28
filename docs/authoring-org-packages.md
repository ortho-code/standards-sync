# Authoring an org package

An org package is a dev-only dependency that ships an organisation's actual standard on top of this engine. It is named freely — see [distribution.md](distribution.md) for the recommended convention.

The standard is a class extending `Authoring\Standard` that declares everything it enforces in `enforce()` — a rule per enforced concern, in declaration order. The package's `standards-sync.php` returns it:

```php
return SyncConfig::create()->withRuleSet(new Acme());
```

```php
final class Acme extends Standard
{
    protected function enforce(Package $package): void
    {
        $this->addRule(new ManagedBlock(
            target: FileTarget::fromString('.editorconfig'),
            label: Label::fromString('acme-coding-standards'),
            content: $package->read('.editorconfig'),
        ));
        $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('phpstan.neon')));
    }
}
```

`enforce()` receives the package located through composer's install record (`Package::fromClass(static::class)`), correct in every composer layout including symlinked path-repo installs. `read()` loads distributed content at config-build time, for rules that copy (managed blocks); `path()` renders the consumer-root-relative reference, for rules that import (included rulesets, base sets).

**Everything the package distributes lives in its `templates/` directory** — both copied fragments (often partial files) and the complete rulesets consumers' tools load from `vendor/<name>/templates/…`. `read()` and `path()` resolve inside it, so a rule can never point at the package's own config: what the package lints *itself* with stays at its root, outside the distributed directory by construction. Both accept a relative path, so a package distributing several variants of a file separates them by subdirectory.

A hierarchy composes inside `enforce()`: a second-tier standard starts with `$this->include(new AcmeBase());` and adds or overrides after it — the included standard locates its own package, unless it is handed one.

A consumer whose layout composer does not know (an in-repo psr-4 package) injects the location in its own `standards-sync.php`:

```php
return SyncConfig::create()->withRuleSet(new Acme(
    package: new Package(__DIR__ . '/standards/acme', 'standards/acme'),
));
```

## Declaration order

Rules that target the same file fold in declaration order — each rule receives the previous rule's output. Order never breaks correctness (every rule is idempotent and the fold is deterministic), but it *is* semantics in three places:

1. **Same-label managed blocks: later wins.** A block declared after another with the same label replaces it — that is the override mechanism, e.g. a second tier replacing a base block wholesale.
2. **Hierarchy: the included standard folds first.** `include()` runs the base tier's rules before the including tier's, which is what lets the second tier layer on top: its tool-set entries register after the base's, its same-label blocks override the base's.
3. **Creation: the first rule to meet an absent file decides the created base**; every later rule edits that content. A tool's import rule therefore decides the created file's shape when it is declared before that tool's value rules (level floor, pins).

The same ordering governs a tool with no import tier (psalm, phpunit): its base-config rule (`PsalmBaseConfig`, `PhpUnitBaseConfig`) declared before its value rules makes an absent config grow from the org template instead of the engine skeleton. And because nothing rides `composer update` for such a tool, **the template is one-shot** — it fires only into nothingness and never edits an existing config, so only values that also have their own rule stay enforced.

Order is only meaningful among rules sharing a target: declarations aimed at different files are independent of each other.

## Installing and running the tools

Synced configs enforce nothing on their own: a repo that never installs the tools, or never runs them, passes every day. Three shipped rules close that — `ComposerRequirement` puts the tool in the manifest, `ComposerScript` owns a named entry point that runs the tools plus `standards-sync sync --check`, and `ManagedBlock` carries that call into a CI config, which needs no engine support of its own since `ManagedBlock` works in any comment-bearing format and CI configs are YAML.

Three facts govern how they behave in a consumer:

- Writing a requirement leaves `composer.lock` stale: `composer install` warns, and refuses outright when the package is not in the lock at all, so the gap surfaces rather than passing silently.
- Composer puts its bin-dir on PATH when running scripts, so a script entry names the bare binary with no `vendor/bin/` prefix.
- That bin-dir holds the binaries of a project's *dependencies*, never the root package's own. A package that ships a tool and also adopts a standard that runs it therefore cannot invoke it by bare name — the script fails with `<tool>: not found` while every other script works. Linking the package's own binary into the bin-dir from a `post-install-cmd` resolves it, and is the only case where a consumer needs anything beyond the three rules above.
- Nothing in the engine ties a CI config's call to the name `ComposerScript` declares. The two are matched only by the text of the call, so a renamed script leaves the CI file calling a script that no longer exists.

## What the mechanism does not do

Three limits an org package meets sooner or later. Each is a property of the engine as it stands, and each has a recorded direction in [roadmap.md](roadmap.md).

- **A block cannot be extended, only accepted or replaced.** A consumer writes outside the block, and in a line-oriented file that is enough because concatenation is composition — a `.gitignore` keeps working when a repo adds its own lines. In a single-document format it is not: a workflow's block owns the whole file, since a second top-level mapping after it is invalid YAML. A repository needing a variation adds its own file beside the synced one, or the standard ships something callable — a reusable workflow rather than a fixed one.
- **Rules are disabled by class, not per declaration.** `withoutRule(ComposerScript::class)` drops every script the standard declares, not one of them; the same goes for every block under `ManagedBlock`. A consumer that needs to opt out of exactly one declaration has no way to say so.
- **Sync adds; it never retracts.** A rule recognises its own prior output by matching what the current version renders, and nothing records what an earlier version wrote. So changing a rendered reference — moving a distributed template, renaming a preset, altering marker decoration — does not update consumers: it stops recognising the old text and writes a second copy beside it. Treat any such change as breaking, and migrate the old entries by hand.

## Testing an org package

The engine ships framework-neutral helpers under `Testing/`; they return plain data, so a package asserts with whatever it uses.

- **`SyncTester`** runs a sync in memory, against an optional map of files the target repo already has, and returns the resulting `path => contents` map (or the plan).
- **`SyncFixtureTester`** runs a sync against an on-disk fixture — an input tree, an expected tree, and the fixture's own `standards-sync.php` unless another is supplied — and reports how the result differs from the expected tree.
- **`ScenarioTestCase`** is the phpunit base class over that: fixtures live in a `fixtures/` directory beside the concrete test class, scenarios come from `scenarios()`, and each is synced and asserted to match.
- **`FileContent::fromString()`** builds file content for seeded and expected files, appending the trailing line break.

Fixture runs additionally parse-validate every synced file (`Testing/Validation/`), so a writer cannot produce syntactically broken output unnoticed. A synced file whose parser is not installed **fails loud** rather than skipping: the parsers for the formats a package ships must be present — nette/neon for neon, symfony/yaml for yaml, vimeo/psalm for the psalm schema check, phpunit/phpunit for the phpunit schema check (which any suite running these fixtures has by construction) — or the validator is left out through `SyncFixtureTester`'s `validators:` parameter.

In the package's own repository the org package is composer's *root* package, so references render bare (`templates/…`) rather than as a consumer would see them. A test asserting reference paths injects a fixed package:

```php
new Acme(package: new Package(dirname(__DIR__, 2), 'vendor/acme/acme-coding-standards'))
```

The [rule catalog](rules/README.md) holds worked before/after examples for every shipped rule.
