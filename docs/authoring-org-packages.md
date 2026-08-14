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

The standard sits at the package's namespace root — it is the package's one public class, and `extends Standard` already says what kind it is; supporting classes earn folders when they appear.

`enforce()` receives the package located through composer's install record (`Package::fromClass(static::class)`), correct in every composer layout including symlinked path-repo installs. `read()` loads distributed content at config-build time, for rules that copy (managed blocks); `path()` renders the consumer-root-relative reference, for rules that import (included rulesets, base sets).

**Everything the package distributes lives in its `templates/` directory** — both copied fragments (often partial files) and the complete rulesets consumers' tools load from `vendor/<name>/templates/…`. `read()` and `path()` resolve inside it, so a rule can never point at the package's own config: what the package lints *itself* with stays at its root, outside the distributed directory by construction.

A hierarchy composes inside `enforce()`: a second-tier standard starts with `$this->include(new AcmeBase());` and adds or overrides after it — the included standard locates its own package.

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
3. **Creation: the first rule to meet an absent file decides the created base**; every later rule edits that content. The discipline that follows: **declare a tool's import rule before its value rules** (level floor, pins) — the created file then grows in the tool's conventional shape, and later declarations read as they behave.

The same discipline covers a tool with no import tier (psalm, phpunit): declare its base-config rule (`PsalmBaseConfig`, `PhpUnitBaseConfig`) before its value rules, so an absent config grows from the org template instead of the engine skeleton. And because nothing rides `composer update` for such a tool, **the template is one-shot** — it fires only into nothingness and never edits an existing config, so only values that also have their own rule stay enforced. The template bootstraps; rules converge.

## Making the standard enforceable

Synced configs enforce nothing on their own: a repo that never installs the tools, or never runs them, passes every day. Closing that takes three declarations that belong together, and only the first two are engine rules:

1. **`ComposerRequirement`** per tool, so the manifest actually requires it. Writing a requirement leaves `composer.lock` stale, which is deliberate — `composer install` warns, and refuses outright when the package is not in the lock at all, so the gap surfaces loudly rather than silently.
2. **`ComposerScript`**, one named entry point that runs the tools plus `standards-sync sync --check`, so the configs and the check itself are drift-guarded.
3. **A `ManagedBlock` in the CI config** calling that script. This needs no engine support — `ManagedBlock` works in any comment-bearing format, and CI configs are YAML.

The third one calls the second one *by name*, and nothing in the engine ties them together, so pin them in the package's own test: read the script name back out of the synced manifest and assert the workflow calls it. A renamed script would otherwise leave CI running nothing.

## Testing an org package

Use the shipped `Testing/` helpers:

- Extend `ScenarioTestCase` for fixture-based before/after scenarios (fixtures in a `fixtures/` dir beside the test). This is the fit when a package ships **custom rules**: each rule's fixtures are its behaviour catalog, exactly as the engine tests its own rule library. A package that only composes shipped rules usually needs no fixtures — the engine's own catalog already pins how each rule behaves.
- Fixture runs parse-validate every synced file (`Testing/Validation/`); a synced file whose parser is not installed fails loud, so install the parsers for the formats your package ships (nette/neon for neon, symfony/yaml for yaml, vimeo/psalm for the psalm schema check; the phpunit schema check needs phpunit/phpunit, which any suite running these fixtures has by construction) — or leave that validator out via `SyncFixtureTester`'s `validators:` parameter.
- Use `SyncTester` directly for a quick presence check (sync in memory, assert the block and content land) when the synced file is a whole file a fixture would just duplicate — asserting exact bytes there would only re-state the template, and the exact rendering is the engine's responsibility, not the org package's.
- Tests that assert reference paths inject a fixed package, because in the package's own repo the org is composer's *root* package and references would render bare (`templates/…`) instead of consumer-realistic:

```php
new Acme(package: new Package(dirname(__DIR__, 2), 'vendor/acme/acme-coding-standards'))
```

The test org package is the worked example.
