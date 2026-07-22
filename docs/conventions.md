# Code and test conventions

- PHP 8.5+, `declare(strict_types=1)` in every file. Use modern features freely (`readonly class`, enums, `new` in initializers) — including the newest stdlib: `array_find` / `array_any` / `array_find_key` over a stateless `foreach` scan; a scan that tracks running state stays a `foreach`.
- Single quotes unless interpolation or escapes need double.
- Comments in English, one sentence per line; comment the non-obvious *why*, not the *what*.
- CLI (`Presentation/Cli`, `symfony/console`): `bin/standards-sync` keeps only the autoload probe, then builds and runs the console application.
- **Adapters**: encode the backing technology in the class name only where a port has interchangeable implementations (`SymfonyFilesystem`, `InMemoryFilesystem`); the single CLI delivery stays undecorated. Group adapters by capability (`Filesystem/`, `Cli/`), not per-vendor subfolders (one class per vendor → alias noise).
- **Value objects**: single-value wrappers (`Path`, `Label`) expose one neutral accessor `value()`; composites (`Change`, `RuleApplication`, …) expose one getter per field named for the field (`path()`, `before()`); turning a composite into one string is `toString()` or a dedicated formatter, never a field getter. No public properties; no implicit `(string)` cast or `__toString`.
- **Named constructors**: `from<Source>()` when parsing or converting input (`Path::fromString`); `create()` / `create<Variant>()` when constructing with defaults (`SyncConfig::create`). Instance evolution is `with*()` returning a new instance. A value object's constructor stays private behind its named constructors. Semantics that don't fit either prefix belong on the type (class docblock), not in a novel factory name — a reader must be able to tell a factory from a getter at a glance.
- **Tests**: three phpunit suites — `tests/Unit` (pure logic), `tests/Integration` (touches the real filesystem or wires a component), `tests/Scenario` (the end-to-end fixture catalog; classes extend `ScenarioTestCase`). One scenario per test method; parametrize families with a `#[DataProvider]` written as a generator (`yield 'case' => [...]`), not a returned array. Several assertions verifying one scenario are fine; distinct scenarios get their own case.
- **The scenario suite is the behaviour catalog.** `ScenarioTestCase` fixtures under `tests/Scenario/fixtures/` cover every pipeline behaviour (first-sync adoption, block updates, marker syntaxes, hierarchy via `include()`, multi-file and subdirectory paths) and double as the worked examples for consumers and the outline for the usage docs. **Any change to core pipeline behaviour must add, update, or remove the matching fixture.** When you touch the engine fold, a rule type, first-sync handling, or marker handling, ask which fixture demonstrates it and whether it still holds. Prefer too many scenarios over missing one.

## Commands

- Tests: `composer app-run-tests` (all three suites), `composer app-run-tests-unit`, `composer app-run-tests-integration`, `composer app-run-tests-scenario`.
- Test strictness (fail on any warning/deprecation/notice/risky, stop on first defect) lives in `phpunit.xml.dist`, not in the scripts.
- Architecture: `composer deptrac` — fails on any cross-layer violation or uncovered class.
