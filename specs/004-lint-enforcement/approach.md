# 004 — Approach

Spec: `specs/004-lint-enforcement/spec.md`
Security flag: no (CI supply chain handled by AC8), so there's no threat model.

## Summary

Add a Husky-managed pre-commit hook that runs lint-staged → PHPCS on staged PHP files, and a GitHub Actions workflow with two jobs: PHPCS, and `php -l` on PHP 8.1–8.3. No plugin code changes.

Note: `c544d4c` has since been dropped from `maintenance` (HEAD is `12f3280`). Spec AC9 is verified by recreating its one-line `===` → `==` change on a throwaway branch.

## Changes

### 1. Husky and lint-staged (AC1–AC5) — revised at Gate 2

- **`package.json`** (new, `"private": true`):
  - `devDependencies`: `husky` `^9.1.7`, `lint-staged` `^16.4.0`. lint-staged 17 needs Node ≥ 22.22.1; 16 needs ≥ 20.17, a current LTS line.
  - `"engines": { "node": ">=20.17" }`.
  - `"scripts": { "prepare": "husky" }`. Husky 9's `prepare` sets `core.hooksPath` to `.husky/_` on `npm install` (AC1).
- **`package-lock.json`**: committed. **`.gitignore`**: add `/node_modules/`.
- **`.husky/pre-commit`** (POSIX `sh`, executable):
  - If `node_modules/.bin/lint-staged` is missing, check `git diff --cached --name-only` for PHP or lint-config paths. If there are none, exit 0 (AC3). Otherwise print "lint-staged not installed. Run `npm install`, then commit again." and exit 1 (AC4).
  - Otherwise `exec node_modules/.bin/lint-staged`. This uses the local binary rather than `npx`, so nothing is downloaded at commit time.
- **`.lintstagedrc.mjs`** (config as code so it can check `vendor/`):
  - `'*.php'` → `vendor/bin/phpcs` with the staged file paths. PHPCS still reads `phpcs.xml.dist` from the repo root, so exclusions and properties apply. Explicit paths skip the ruleset's `<file>` list, but its exclude patterns (`/vendor/*`, `/specs/*`) still apply.
  - `'{phpcs.xml.dist,composer.json,composer.lock}'` → `composer lint` (a full run, so a ruleset change re-checks unchanged files).
  - If `vendor/bin/phpcs` doesn't exist, both entries return a `node -e` command that prints "PHPCS not installed. Run `composer install`, then commit again." and exits 1 (AC4).
- **Staged versus working tree (AC2):** lint-staged hides unstaged changes in partially staged files while the tasks run, then restores them, so PHPCS sees the staged content. **Known limit:** a full `composer lint` (lint-config changes only) also sees unstaged edits in files that have no staged changes. That's rare, and CI checks the pushed tree anyway. Recorded in `qa-plan.md`.
- **Release packaging:** nothing under `node_modules/`, `.husky/`, `package*.json` or `.lintstagedrc.mjs` is used at runtime. There's no build or zip step yet; a future `.distignore` belongs with the Release stage.

### 2. Composer

No change. `composer.json` and `composer.lock` stay as they are; the Composer `core.hooksPath` script from the earlier draft is dropped.

### 3. CI: `.github/workflows/lint.yml` (AC6–AC8)

- **Triggers:** `pull_request` with `branches: [master]`, and `push` with `branches: [master]`.
- **Token:** top-level `permissions: contents: read`. No secrets used.
- **Concurrency:** `concurrency: { group: lint-${{ github.ref }}, cancel-in-progress: true }`.
- **Job `phpcs`:**
  - `runs-on: ubuntu-latest`
  - `actions/checkout` (`persist-credentials: false`)
  - `shivammathur/setup-php` with `php-version: '8.3'`, `tools: composer:v2`, `coverage: none`
  - `composer install --no-interaction --no-progress --prefer-dist`
  - `composer lint`
- **Job `syntax`:**
  - Matrix `php: ['8.1', '8.2', '8.3']`, with `fail-fast: false` so every version reports.
  - Checkout and setup-php as above, with no Composer step.
  - `find multisitesharedmenu.php inc admin views -name '*.php' -print0 | xargs -0 -n1 php -l`. `xargs` exits non-zero if any `php -l` fails.
  - Also run `php -v` so the log shows the exact version tested.
- **Pinning (AC8):** pin `actions/checkout` and `shivammathur/setup-php` to full commit SHAs with `# vX.Y.Z` comments. Look up the SHAs at build time with `gh api repos/<owner>/<repo>/git/ref/tags/<tag>`, dereferencing annotated tags. Never invent them.

### 4. CLAUDE.md (AC10)

Under Commands, add:

- **Pre-commit hook:** Husky plus lint-staged, enabled by `npm install` (Node ≥ 20.17). It lints staged PHP files, or runs full `composer lint` for lint-config changes. It can be bypassed with `--no-verify`, but CI still catches it.
- **CI:** `.github/workflows/lint.yml` runs PHPCS, plus `php -l` on 8.1 / 8.2 / 8.3, on PRs to `master`.

Add one line to the "Run lint and tests" paragraph: a CI pass is now part of done for PRs.

## Dev notes

### May touch

- New: `package.json`, `package-lock.json`, `.husky/pre-commit`, `.lintstagedrc.mjs`, `.github/workflows/lint.yml`.
- `.gitignore`: add `/node_modules/`.
- `CLAUDE.md`: Commands section, plus one line about CI being part of done.
- `specs/004-lint-enforcement/`.

### Off-limits (stop and ask first)

- Plugin PHP files.
- `phpcs.xml.dist` rules and exclusions.
- Composer files and Composer dependency versions.
- GitHub repo settings (branch protection, required checks). List them in the PR for Ben.
- Pushing branches or opening PRs on GitHub. That's outward-facing, so ask Ben before the AC9 check and say exactly what will be pushed.
- History rewriting.

### Patterns to follow

- Shell: POSIX `sh`, quoted variables. No `bash`-only syntax, `sed -i` or `readlink -f`.
- YAML: the smallest workflow that meets the ACs. No caching, matrix extras or `composer validate` unless Ben asks.
- Messages tell the user what to do next.

### Tests

The hook is tested in a **throwaway clone in the scratchpad** (`git clone` of the local repo, then `composer install` and `npm install`), so the real repo's index and history are never touched.

| AC | Case | Expected |
| --- | --- | --- |
| AC1 | `npm install` in the clone | `git config core.hooksPath` = `.husky/_` |
| AC2 | Stage a `==` regression in `inc/class-menufromsite.php` and commit | Blocked, PHPCS output shown, no commit created |
| AC2 | Stage a clean change, but leave a violation unstaged in the **same** file | Commit succeeds; the unstaged edit is restored afterwards |
| AC2 | Stage a violation, then fix the working tree but don't re-stage | Blocked |
| AC2 | Stage a `phpcs.xml.dist` change that removes an exclusion | Blocked (`EscapeOutput` errors appear) |
| AC3 | Stage only `specs/x.md` | Commit succeeds, PHPCS not run |
| AC4 | Remove `vendor/`, stage PHP, commit | Blocked with the "run composer install" message |
| AC4 | Remove `node_modules/`, stage PHP / stage only `.md` | Blocked with the "run npm install" message / commit succeeds |
| AC5 | Repeat the `==` case on Linux in Docker (`node:22` with PHP and Composer added, or `composer:2` with Node added) | Same result |
| — | `git commit --no-verify` with a violation | Succeeds (documented escape hatch; CI is the backstop) |

**Workflow:**
- Check the YAML locally (e.g. `docker run --rm -v "$PWD":/repo rhysd/actionlint`, or `npx` equivalent if available) before pushing.
- **With Ben's OK:** push a throwaway branch with the `==` regression, open a draft PR into `master`, and confirm `phpcs` fails while `syntax` passes on 8.1 / 8.2 / 8.3. Push the fix and confirm all jobs pass. Then close the PR and delete the branch (AC9).
- Also run `composer lint` in the real repo after the change. It must still pass, since no plugin code changed.

## Risks

| Risk | Mitigation |
| --- | --- |
| Contributor without Node gets no hook | CI is the enforcement; the hook is convenience. Node ≥ 20.17 is documented |
| Husky's `core.hooksPath` silently disables someone's local `.git/hooks` | Documented in CLAUDE.md |
| npm dev packages run code on install | `package-lock.json` committed; Husky and lint-staged only; `npm install` runs them on developer machines, not in CI |
| Third-party action compromised | SHA pinning, read-only token, no secrets in the workflow |
| CI PHP versions drift (setup-php resolves `8.1` to the latest 8.1.x) | Acceptable. The log prints `php -v` |

## Size

About 1–2 hours, within the spec's estimate. The PR round-trip for AC9 depends on CI queue time.
