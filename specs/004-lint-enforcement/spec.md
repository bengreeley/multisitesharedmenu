# 004 — Enforce lint on commit and in CI

## Problem

Spec 003 added `composer lint` (PHPCS: WPCS + PHPCompatibilityWP), but nothing runs it automatically. A violation can be committed and pushed without anyone noticing.

Example: commit `c544d4c` on `maintenance` changed `===` to `==` in `inc/class-menufromsite.php`. `composer lint` reports it (`Universal.Operators.StrictComparisons.LooseEqual`, exit code 2), but there is no git hook and no CI, so it went in unchecked.

A second gap from specs 001–003: the code has never been syntax-checked on PHP 8.1, the stated minimum. The only local PHP versions are 8.3 and 8.4, and PHPCompatibility 9.3.5 can't check 8.1+ rules.

## Repro steps

1. On `maintenance` at `c544d4c`, run `composer lint`. It exits 2 with 1 warning.
2. Make any PHPCS violation, `git add` it, then `git commit`. The commit succeeds.
3. Push to GitHub and open a PR into `master`. No check runs.

## Acceptance criteria

### Local pre-commit hook

- [ ] AC1: The pre-commit hook is managed by **Husky**, which is a dev dependency in a new `package.json` with a committed `package-lock.json`. Running `npm install` enables it for that clone with no extra manual step. `node_modules/` is git-ignored. (Revised at Gate 2: Husky instead of a Composer-installed hook.)
- [ ] AC2: When a commit includes staged PHP files, the hook runs PHPCS with the project's `phpcs.xml.dist` against the **staged** content, not unstaged working-tree edits. It blocks the commit if PHPCS reports any error or warning, and prints the PHPCS output. A staged change to `phpcs.xml.dist`, `composer.json` or `composer.lock` triggers a full `composer lint`.
- [ ] AC3: When a commit has no staged PHP or lint-config files (e.g. only `specs/*.md`), PHPCS doesn't run and the commit proceeds.
- [ ] AC4: If `vendor/` (PHPCS) or `node_modules/` (lint-staged) is missing, a commit with PHP or lint-config files is blocked with a message naming the command to run (`composer install` / `npm install`). It doesn't fail silently or skip.
- [ ] AC5: The hook works on macOS and Linux and needs only git, PHP, Composer and Node (version in `package.json` `engines`). Husky and lint-staged are dev-only. The plugin gains no runtime dependency, and the release package must not need `node_modules/`.

### CI (GitHub Actions)

- [ ] AC6: A workflow runs on pull requests targeting `master` and on pushes to `master`. It installs Composer dev dependencies and runs `composer lint`. The job fails on any PHPCS error or warning.
- [ ] AC7: The same workflow runs `php -l` on every plugin PHP file on PHP 8.1, 8.2 and 8.3. Any syntax error fails the job.
- [ ] AC8: The workflow's `GITHUB_TOKEN` permissions are read-only (`contents: read`). Every third-party action is pinned to a full commit SHA, with the version in a comment.
- [ ] AC9: On a PR whose head has `c544d4c`'s loose comparison, the lint job fails. With the comparison fixed, all jobs pass. This is verified on a real PR or a throwaway branch.

### Docs

- [ ] AC10: CLAUDE.md's Commands section says the pre-commit hook is enabled by `npm install` (Husky) and that CI runs lint and PHP 8.1–8.3 syntax checks. The spec 001–003 note "php -l not run under 8.1" no longer applies to future work.

## Out of scope

- Turning on GitHub branch protection or "required status checks" for `master`. That's a repo setting Ben changes in GitHub, not code. The PR will list the exact setting to enable.
- PHPUnit, wp-env, integration tests in CI (the tooling spec).
- Fixing `c544d4c` itself. It's Ben's test commit; reverting or dropping it is Ben's call. It must be gone before the `maintenance` PR's CI can pass.
- Linting non-PHP files (Markdown, JSON, YAML).
- A dependency-update bot (Dependabot or Renovate).

## Security flag: no

There's no change to plugin runtime code, escaping, sanitization, capabilities, nonces or blog switching. The new attack surface is dev and CI supply chain only: third-party GitHub Actions, Composer dev packages, and npm dev packages (Husky, lint-staged), which run on developer machines through npm install scripts and the git hook. AC8 covers CI (read-only token, SHA-pinned actions); `composer.lock` and `package-lock.json` pin the dev packages. The workflow runs no secrets and no deploy steps.

## Size

About 1–2 hours: the Husky and lint-staged setup ~45 min, the workflow ~30 min, verification on a throwaway branch and PR ~30 min. Under the 8-hour limit.
