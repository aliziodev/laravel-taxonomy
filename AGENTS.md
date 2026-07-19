# AGENTS.md

Notes for AI coding agents working **on this package**. If you are using the
package in an application, read [README.md](README.md) instead — this file
covers contributing to the repo itself.

## What this is

A Laravel package for categories, tags and hierarchical terms. Terms live in
one `taxonomies` table, attach to any model through a polymorphic
`taxonomables` pivot, and hierarchies are kept as a nested set (`lft`, `rgt`,
`depth`) alongside `parent_id`.

```
src/Models/Taxonomy.php      the model, the boot hooks, the nested set
src/Traits/HasTaxonomy.php   what user models get: relation, scopes, attach/sync
src/TaxonomyManager.php      what the facade proxies; caching lives here
src/Facades/Taxonomy.php     facade over the manager, NOT over the model
```

## Commands

```bash
composer test      # Pest
composer analyse   # PHPStan (larastan)
composer format    # Pint
composer check     # all three
```

## Hard rules

**Coverage must stay at 100%.** Codecov tracks it and `src/` is fully covered.
If you add a branch you cannot reach from a test, prefer deleting the branch
over leaving it uncovered — unreachable defensive code has been removed here
before for exactly this reason.

**Commit messages decide the release.** Pushing to `master` runs
semantic-release. `feat:` cuts a minor, `fix:`/`refactor:` cut a patch,
`docs:`/`test:`/`chore:`/`ci:`/`style:` cut nothing. A stray `feat:` ships a
version bump. Commitlint runs in CI, so the format is enforced.

**PHPStan and Pint must pass.** Both gate CI.

**Backward compatibility matters.** This package has a large installed base.
Do not change public signatures or config defaults without a deliberate
decision and an UPGRADE.md entry.

## Things that will trip you up

**The facade is not an Eloquent builder.** `Taxonomy::where(...)` does not
exist on `Facades\Taxonomy` — it proxies `TaxonomyManager`. Import
`Models\Taxonomy` for query building. The old README got this wrong
throughout.

**Nested set values come from model hooks.** `DB::table('taxonomies')->insert()`
bypasses them, leaving `lft`/`rgt`/`depth` null and the tree unusable. The
performance test did this for a long time and consequently measured an empty
structure. Use `Taxonomy::bulkCreate()`, or raw-insert and then call
`rebuildNestedSet()`.

**Attach methods take ids or models, never slugs.** Passing a slug string
attaches nothing and raises no error.

**`getDescendants()`/`getAncestors()` read `lft`/`rgt` off the instance.**
Adding a child widens the parent's `rgt` in the database, so a parent loaded
beforehand returns an empty collection. Call `refresh()` first.
`descendants()` is keyed on the id and is immune.

**Cache invalidation is version-key based**, not key deletion, so it works on
stores without tag support. Never wrap `tree()`/`flatTree()`/`getNestedTree()`
in another `Cache::remember()`: the second layer will not see the
invalidation.

**Multi-tenant installs need `TaxonomyManager::resolveCacheScopeUsing()`.**
Without it, cache keys are global and one tenant can be served another's tree.

**The `$name` relationship parameter is deprecated** (removal in 3.0). It
selects the pivot morph columns, which the shipped migration does not create,
and Eloquent resolves relationships statically so query scopes cannot honour a
runtime argument at all.

## Testing conventions

**Do not use `$this->` properties in Pest closures.** PHPStan does not model
them and reports "undefined property on TestCall". Use a fixture helper that
returns an object instead — see `tests/Unit/ReadmeExamplesTest.php`.

**Do not use `$this->artisan()`** for the same reason. Use
`Artisan::call()`, and subclass the command if you need to drive a prompt —
see `tests/Unit/CoverageEdgeCasesTest.php`.

**Performance budgets are skipped while a coverage driver is active.** Xdebug
instruments every line and inflates wall-clock roughly twentyfold, so those
assertions mean nothing under coverage. The six uninstrumented matrix jobs
still enforce them.

**`tests/Unit/ReadmeExamplesTest.php` and `DocsExamplesTest.php` pin every
documented example.** If you change a signature, they fail — that is
deliberate, so the docs cannot rot silently again.

## Verifying a change

Run the full suite, PHPStan and Pint before committing. Coverage needs Xdebug
or PCOV; if neither is installed, CI will measure it. When touching the nested
set or the write path, benchmark before and after — several claims about this
package's performance turned out to be wrong until measured.
