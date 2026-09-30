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

**A template named after a file git reads by name is live in the package's own repository.**
`templates/` sits in the package's working tree, so a `.gitattributes` saved there applies to the templates beside it, and an export-ignore list under that name strips from the package's archive the very templates it names.
Save it as `gitattributes`, without the dot; the rule's target still names the real file:

```php
$this->addRule(new ManagedBlock(
    target: FileTarget::fromString('.gitattributes'),
    label: Label::fromString('acme-coding-standards'),
    content: $package->read('gitattributes'),
));
```

A `.gitignore` template is live the same way — a new template matching one of its lines stays out of `git add` — which is harmless for as long as none does.

A hierarchy composes inside `enforce()`: a second-tier standard starts with `$this->include(new AcmeBase());` and adds or overrides after it. The tiers live in separate packages or side by side in one — see [Several standards in one package](#several-standards-in-one-package).

A consumer whose layout composer does not know (an in-repo psr-4 package) injects the location in its own `standards-sync.php`:

```php
return SyncConfig::create()->withRuleSet(new Acme(
    package: new Package(__DIR__ . '/standards/acme', 'standards/acme'),
));
```

## Several standards in one package

Tiers do not need a package each. Two standards side by side in one package compose exactly as two packages do, which is how an organisation ships a library tier and an application tier — and later a base extracted from them — on one release line and one version constraint:

```php
final class AcmeProject extends Standard
{
    protected function enforce(Package $package): void
    {
        $this->include(new AcmeBase($package));

        $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('project/phpstan.neon')));
    }
}
```

**Hand the package down.** An included standard constructed without one locates its own, which resolves to the same package in a consumer install and makes the omission look harmless — until a test injects a `Package`: there the including tier renders `vendor/acme/standards/templates/…` while the included tier renders bare `templates/…` paths for the very same directory. Passing `$package` makes both tiers speak for the package they are in, in every layout.

The tiers share the one `templates/` directory, so dividing it between them is a matter of relative paths — but divide it before the first release: a path a rule renders into a consumer's config is matched verbatim afterwards, so moving a template later leaves a stale entry beside the new one (see [What the mechanism does not do](#what-the-mechanism-does-not-do)).

## Several standards in one consumer

`withRuleSet()` is additive, and the engine flattens every declared set's rules in declaration order before folding them per file, so a consumer can declare more than one standard:

```php
return SyncConfig::create()
    ->withRuleSet(new Acme())
    ->withRuleSet(new AcmeGitHub());
```

This is the same composition `include()` performs, moved to the consumer's own config, and it is how an org separates a concern the *consumer* chooses between rather than the standard — CI for one forge or another being the case that forces it. Nothing here removes a file, so a standard that ships one forge's CI cannot be adopted on another at all; splitting that concern into a rule set declared beside the tier is the fix, and it needs no engine feature.

Each standard declared this way locates its own package, so the pass-down trap above does not apply — that one belongs to `include()`, where a standard constructs another.

**Two separately declared rule sets that target the same thing do not merge, except in a shared list.**
Folding is by resolved path in declaration order, and a same-label block is replaced by the later one: the later declaration wins and the earlier one's content is gone, with nothing reported.
Within one standard that is the documented override mechanism and one author sees both declarations; across two, neither author can see the other.
Keep separately declared standards on disjoint concerns, and pin anywhere one names something another declares — a CI block calling a script name, say — in the package's own suite, because the engine will not.

A rule that contributes to a list is the exception, and it is how one standard adds to what another declares.
Declarations of one composer script merge, so a framework standard declared beside a tier adds a step to the tier's aggregate by declaring the same script with only that step — see [Installing and running the tools](#installing-and-running-the-tools).
Tool imports merge the same way: a framework standard adds its own PHPStan ruleset, Rector or ECS set, deptrac depfile or renovate preset beside the tier's by declaring it, and the tier's entries stay.

## Declaration order

Rules that target the same file fold in declaration order — each rule receives the previous rule's output. Order never breaks correctness (every rule is idempotent and the fold is deterministic), but it *is* semantics in four places:

1. **Same-label managed blocks: later wins.** A block declared after another with the same label replaces it — that is the override mechanism, e.g. a second tier replacing a base block wholesale.
2. **Hierarchy: the included standard folds first.** `include()` runs the base tier's rules before the including tier's, which is what lets the second tier layer on top: its tool-set entries register after the base's, its same-label blocks override the base's.
3. **Creation: the first rule to meet an absent file decides the created base**; every later rule edits that content. A tool's import rule therefore decides the created file's shape when it is declared before that tool's value rules (level floor, pins).
4. **Contributions to one list: order places, it never replaces.** Declarations of one composer script merge before the fold, each adding its commands after the earlier ones', so a consumer that declares the tier first keeps the tier's commands first. Declarations of one tool import merge the same way: an added import goes after the list's last entry, so the later-declared standard's entry comes later in the tool's own precedence, and an import replacing one the standards stopped declaring takes that entry's place.

The same ordering governs a tool with no import tier (psalm, phpunit): its base-config rule (`PsalmBaseConfig`, `PhpUnitBaseConfig`) declared before its value rules makes an absent config grow from the org template instead of the engine skeleton. And because nothing rides `composer update` for such a tool, **the template is one-shot** — it fires only into nothingness and never edits an existing config, so only values that also have their own rule stay enforced.

Order is only meaningful among rules sharing a target: declarations aimed at different files are independent of each other.

## Installing and running the tools

Synced configs enforce nothing on their own: a repo that never installs the tools, or never runs them, passes every day. Three shipped rules close that — `ComposerRequirement` puts the tool in the manifest, `ComposerScript` declares a named entry point that runs the tools plus `standards-sync sync --check`, and `ManagedBlock` carries that call into a CI config, which needs no engine support of its own since `ManagedBlock` works in any comment-bearing format and CI configs are YAML.

These facts govern how they behave in a consumer:

- A requirement whose declared constraint names only a branch is *pinned* rather than floored: branches have no ordering, so the declared one is written outright and a project on another branch is rewritten to it. That is how a package publishing nothing but branches (a security-advisories package, for instance) becomes part of a standard at all.
- Writing a requirement leaves `composer.lock` stale: `composer install` warns, and refuses outright when the package is not in the lock at all, so the gap surfaces rather than passing silently.
- Composer puts its bin-dir on PATH when running scripts, so a script entry names the bare binary with no `vendor/bin/` prefix.
- That bin-dir holds the binaries of a project's *dependencies*, never the root package's own. A package that ships a tool and also adopts a standard that runs it therefore cannot invoke it by bare name — the script fails with `<tool>: not found` while every other script works. Linking the package's own binary into the bin-dir from a `post-install-cmd` resolves it, and is the only case where a consumer needs anything beyond the three rules above.
- A script's declared commands are the standard's, and every other command in it is the project's and stays where the project put it. A missing declared command is inserted after the command declared before it, and one the standards stop declaring is retracted on the next sync — with any arguments a project added to it.
- A declaration with `acceptsArguments: true` lets a project add arguments after each of its commands — a memory flag on the analyser, say — and composer carries them through the aggregate's `@` reference into CI. Leave it off wherever an argument could weaken what another rule enforces: `phpstan analyse --level=0` overrides the level the config's floor holds.
- Without it, an edited command counts as the project's own and the declared one is inserted back beside it, so a consumer varies such a command by wrapping it: composer passes arguments through an `@name` reference, so `"app-phpstan-local": ["@app-phpstan --memory-limit=1G"]` runs the declared command with the argument added. The standards' own aggregate still calls the declared name, so a wrapper serves a person at a terminal, not CI.
- Retraction needs memory: the engine records the commands each root's standards declared in `standards-sync.lock`, which the consumer commits beside `standards-sync.php`. A missing lock is drift, so the first `sync --check` after upgrading to an engine that writes one fails until a sync writes it; a lock deleted later means that one sync retracts nothing. A script the standards stop declaring altogether is not removed, because nothing is left that declares how, so the report notes it once and it stays as the project's own.
- Adopting a standard over a same-name script the project already had keeps the project's commands beside the declared ones, so review the first sync's diff. A project that ran `php vendor/bin/phpstan --memory-limit=256M` as `app-phpstan` now runs `phpstan analyse` before it, and deletes its own line once it no longer needs it.
- To add a step to another standard's aggregate, declare the same script with only your own commands. `new ComposerScript(name: 'app-checks', commands: ['@app-lint'])` beside a tier's `app-checks` adds `@app-lint` after the tier's commands when it is declared after the tier, and before them when declared before; a command both declare counts once.
- Nothing in the engine ties a CI config's call to the name `ComposerScript` declares. The two are matched only by the text of the call, so a renamed script leaves the CI file calling a script that no longer exists.

## What the mechanism does not do

Three limits an org package meets sooner or later. Each is a property of the engine as it stands, and each has a recorded direction in [roadmap.md](roadmap.md).

- **A block cannot be extended, only accepted or replaced.** A consumer writes outside the block, and in a line-oriented file that is enough because concatenation is composition — a `.gitignore` keeps working when a repo adds its own lines. In a single-document format it is not: a workflow's block owns the whole file, since a second top-level mapping after it is invalid YAML. A repository needing a variation adds its own file beside the synced one, or the standard ships something callable — a reusable workflow rather than a fixed one.
- **A rule cannot be disabled yet, and will be disabled by class, not per declaration.** `withoutRule()` is decided and not built ([roadmap](roadmap.md)), so a consumer's only opt-out today is not declaring the standard. Once it lands, `withoutRule(ComposerScript::class)` drops every script the standard declares, not one of them; the same goes for every block under `ManagedBlock`. A consumer that needs to opt out of exactly one declaration will still have no way to say so.
- **Rules that are not list contributions add and never retract.** A managed block or a value rule recognises its own prior output by matching what the current version renders, and nothing records what an earlier version wrote. So changing a rendered form — altering marker decoration, say — does not update consumers: it stops recognising the old text and writes a second copy beside it. Treat any such change as breaking, and migrate the old entries by hand. List contributions — composer script commands and every tool import — are the exception: the lock records what the standards declared, so an entry they change or stop declaring is retracted by the next sync, which makes moving a distributed template or renaming a preset an ordinary release.

## Testing an org package

The engine ships framework-neutral helpers under `Testing/`; they return plain data, so a package asserts with whatever it uses.

- **`SyncTester`** runs a sync in memory, against an optional map of files the target repo already has, and returns the resulting `path => contents` map (or the plan).
- **`SyncFixtureTester`** runs a sync against an on-disk fixture — an input tree, an expected tree, and the fixture's own `standards-sync.php` unless another is supplied — and reports how the result differs from the expected tree.
- **`ScenarioTestCase`** is the phpunit base class over that: fixtures live in a `fixtures/` directory beside the concrete test class, scenarios come from `scenarios()`, and each is synced and asserted to match.
- **`FileContent::fromString()`** builds file content for seeded and expected files, appending the trailing line break.

A standard declaring a composer script makes every sync write `standards-sync.lock`, so a fixture's expected tree carries the lock beside the manifest, and `SyncTester`'s result map holds it too.

Fixture runs additionally parse-validate every synced file (`Testing/Validation/`), so a writer cannot produce syntactically broken output unnoticed. A synced file whose parser is not installed **fails loud** rather than skipping: the parsers for the formats a package ships must be present — nette/neon for neon, colinodell/json5 for JSON5, vimeo/psalm for the psalm schema check, phpunit/phpunit for the phpunit schema check (which any suite running these fixtures has by construction) — or the validator is left out through `SyncFixtureTester`'s `validators:` parameter. YAML needs nothing extra: its parser, symfony/yaml, installs with the engine.

In the package's own repository the org package is composer's *root* package, so references render bare (`templates/…`) rather than as a consumer would see them. A test asserting reference paths injects a fixed package:

```php
new Acme(package: new Package(dirname(__DIR__, 2), 'vendor/acme/acme-coding-standards'))
```

The [rule catalog](rules/README.md) holds worked before/after examples for every shipped rule.
