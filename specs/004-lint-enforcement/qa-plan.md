# 004 — QA plan

## Versions

- husky 9.1.7, lint-staged 16.4.0 (needs Node ≥ 20.17; `engines` set). Tested with Node 22.22.2 (macOS) and 24.22.3 (Linux). `npm install` reports 0 vulnerabilities.
- Actions are pinned to commit SHAs resolved with `gh api` / `git ls-remote`:
  - `actions/checkout` v7.0.1 → `3d3c42e5aac5ba805825da76410c181273ba90b1` (lightweight tag → commit)
  - `shivammathur/setup-php` 2.40.0 → `eb7c497e18156a6bbabfef1d3a82760b9eda3962` (annotated tag `4424c9a…` dereferenced to its commit)

## Hook (2026-11-20)

Tested in a throwaway clone in the scratchpad (`git clone` of the repo, new tooling copied in, then `composer install` and `npm install`). The real repo's index and history were not used. The clone was deleted afterwards.

| AC | Case | Result |
| --- | --- | --- |
| AC1 | `npm install` → `git config core.hooksPath` | `.husky/_`. PASS. `.husky/pre-commit` is tracked as `100755` |
| AC2 | Stage `theme_location == $menu` and commit | **BLOCKED**, shows `LooseEqual` warning. PASS |
| AC2 | Stage a clean comment edit, leave `==` unstaged in the **same** file | **COMMITTED**; the committed blob has no `==`, and the working tree still has the unstaged `==` (restored by lint-staged). PASS |
| AC2 | Stage `==`, then fix the working tree without re-staging | **BLOCKED**. PASS |
| AC2 | Stage `phpcs.xml.dist` with the `EscapeOutput` exclusion removed | **BLOCKED**, 8 `EscapeOutput` errors (full `composer lint` ran). PASS |
| AC3 | Stage only `specs/x.md` | **COMMITTED**, PHPCS not run. PASS |
| AC4 | `vendor/` removed, PHP staged | **BLOCKED**: "PHPCS not installed. Run `composer install`, then commit again." PASS |
| AC4 | `node_modules/` removed, PHP staged | **BLOCKED**: "lint-staged not installed. Run `npm install`, then commit again." PASS |
| AC4 | `node_modules/` removed, only `.md` staged | **COMMITTED**. PASS |
| AC5 | Linux: `composer:2` image (Alpine, BusyBox `sh`, PHP 8.6, Node 24) with `==` staged / `.md` only | BLOCKED / COMMITTED. PASS. lint-staged prints a harmless "could not find any staged files matching configured tasks" on the `.md` commit |
| — | `git commit --no-verify` with `==` staged | COMMITTED (documented escape hatch; CI is the backstop) |

**Known limit (documented in approach):** when only lint-config files are staged, the full `composer lint` also sees unstaged edits in files with no staged changes. CI lints the pushed tree.

## CI

| AC | Check | Result |
| --- | --- | --- |
| AC6–AC8 | `rhysd/actionlint` on `.github/workflows/lint.yml` | PASS: no issues |
| AC8 | `permissions: contents: read` at top level; both actions SHA-pinned with version comments; `persist-credentials: false`; no secrets | PASS (review) |
| AC6, AC7, AC9 | Real run on GitHub: PR with `==` → `phpcs` fails and `syntax` 8.1/8.2/8.3 passes; after the fix, all pass | **NOT RUN.** Needs a push to `github.com/bengreeley/multisitesharedmenu`, pending Ben's OK |

## Docs

| AC | Check | Result |
| --- | --- | --- |
| AC10 | CLAUDE.md Commands: hook via `npm install` (Node ≥ 20.17), `core.hooksPath` note, `--no-verify` note, CI line; "CI passing is part of done for PRs" | PASS |

## Repo state note

At build time the real repo had an unrelated staged change: `inc/class-menufromsite.php` with `theme_location == $menu`, Ben's earlier test from `c544d4c`, modified 14:33, before this build. It was left untouched. `composer lint` on the real working tree fails because of it, and with the hook now enabled in Ben's clone, committing it would be blocked.
