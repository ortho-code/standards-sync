# Authoring an org package

An org package is a dev-only dependency that ships an organisation's actual standard on top of this engine. It is named freely — see [distribution.md](distribution.md) for the recommended convention.

It returns a `SyncConfig` from its `standards-sync.php`, declares rule sets extending `ComposableRuleSet` that `addRule()` a rule per enforced concern (e.g. a `ManagedBlockRule` per managed block), and keeps the content it distributes in its own `templates/` directory, pulled in via `TemplateDirectory` (never a loose file the engine walks).

**What it distributes is separate from its own config.** The content synced into consumers lives under `templates/` (often partial); the package's own root config it lints itself with stays at the root. Never point a rule at the package's own config.

## Testing an org package

Use the shipped `Testing/` helpers:

- Extend `ScenarioTestCase` for fixture-based before/after scenarios (fixtures in a `fixtures/` dir beside the test).
- Use `SyncTester` directly for a quick presence check (sync in memory, assert the block and content land) when the synced file is a whole file a fixture would just duplicate — asserting exact bytes there would only re-state the template, and the exact rendering is the engine's responsibility, not the org package's.

The test org package is the worked example.
