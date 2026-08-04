# The deptrac family — decision record

Part of the [rule-model design record](rule-model.md), split out per family; the contract, the doctrine, the cross-family decisions, and the roadmap live there. Entries are chronological under their original headings.

## Agreed 2026-08-02 (R3) — the deptrac family: shared layers via YAML imports

Agreed as an upcoming phase — initially next, superseded the same date by the psalm family (see the entry below): psalm stresses more new abstraction surface. The design proper (behaviour table, rule naming, fixtures) opens the deptrac phase; what is settled now are the verified facts and the direction, so the design does not re-derive them.

**Why deptrac, against the June inventory.** The first intended consumer org's config inventory (June 2026) rated deptrac "low sharing value — leave configs standalone", but that verdict was shaped by that org's heterogeneous repo architectures. For an org that standardizes its namespace architecture across repos, shared layer definitions plus the ruleset *are* an org standard — architecture rules as the org-level floor. The consuming org/repos are named when the design phase opens.

**Verified config surface (deptrac 4.6.2, read from the installed source):**

- Config discovery: `--config-file`/`-c`, else `deptrac.yaml` in the working directory — a single default candidate on `^4` (`ConfigFileResolver`).
- A PHP config exists (`DeptracConfig` + `DeptracPhpConfigLoader`; the current docs present it as the canonical form) but is **set aside**, three strikes: it is not auto-discovered on `^4` (every consumer invocation would need `--config-file deptrac.php`); it is the closure style (`return static function (DeptracConfig $config): void`) — the very form the fluent-only doctrine refuses to manage for Rector/ECS, and `FluentChainWriter` does not apply to statement calls inside a closure body; and the PHP form has no native sharing mechanism (the only YAML use on `DeptracConfig` is `baseline()`). Revisit if a later major both discovers `deptrac.php` by default and grows an import mechanism.
- YAML sharing is symfony-DI `imports:` (top level, `- { resource: <path> }`), resolving relative to the importing file — so a root config reaches the org package with a plain `vendor/<name>/templates/deptrac.yaml` path.

**Verified merge semantics (empirical, deptrac 4.6.2 — the inheritance behaviour across an import):**

1. **Cross-key split merges.** Imported `layers:` + `ruleset:` with only `paths:` in the importing file analyses correctly — the intended org/consumer split works as-is.
2. **Ruleset allowances union per layer key.** A consumer adding `Domain: [Infra]` on top of the org's `Domain: ~` legalizes the dependency — a child can loosen with one line.
3. **Append-only, no removal.** Re-declaring a layer's allowances with an empty (or shorter) list removes nothing — a child cannot tighten by re-declaration; tightening is done by *adding* layers and rules, which is pure addition and works.
4. **Same-name layer re-declaration unions the collectors** — no error, no replacement. This is the consumer's extension mechanism: the org defines what a layer means and forbids; the consumer folds its own namespaces into that layer and inherits its constraints.

**Direction recorded for the design phase:** the org ships `templates/deptrac.yaml` carrying layers + ruleset; the consumer keeps `paths:` and its extensions; the first tranche is one import rule — a sibling of `PhpStanIncludedRuleset` ensuring the `imports:` entry — over a new `Formats/Yaml` writer (fourth `Formats/` member, targeted section/list edit, no parse-dump). The YAML entry is the same edit shape as phpstan's `includes:` entry — its writer mirrors `Formats/Neon/NeonListWriter` (extracted 2026-08-04; see the note under the R2 decision in [rule-model.md](rule-model.md)) as a per-format sibling. Semantics 2 is the phpstan include-merge story replayed: the import ships the standard but the merge lets a consumer silently allow what the org forbade; the counter, when a real org needs it, is an enforce-absence member over the consumer's `ruleset:` section (the `PhpStanPinnedValues` analog) — not first tranche. Deptrac's own `skip_violations` remains the reviewable escape hatch for legitimate exceptions. Open design questions noted now: what the created-on-absent config holds (the phpstan precedent says just the import; a `paths:`-less deptrac run should then fail loudly at the maintainer — verify), and the rule's artifact-noun name in deptrac's own vocabulary.

