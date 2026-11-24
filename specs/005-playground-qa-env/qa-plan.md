# 005 — QA plan

Run 2026-11-23 on macOS with Node 22.22.2 and `@wp-playground/cli` 3.1.66 (WordPress latest, SQLite). Scenarios were driven by a throwaway `curl` script in the scratchpad (not committed), with every request time-limited.

**Note:** the working tree had Ben's unrelated staged test change in `inc/class-menufromsite.php` (`theme_location == $menu` instead of `===`). For string theme locations the two behave the same, so results aren't affected. That change isn't part of this spec.

## Findings during the build

| Finding | Resolution |
| --- | --- |
| `127.0.0.1:9400` fails: "WordPress multisites do not support custom ports" | Serve on **port 80** with `--site-url=http://localhost` (macOS allows port 80 without sudo). Documented in the README, including the Linux caveat |
| Playground defines `WP_DEBUG_LOG=true` before `wp-config.php`, so the blueprint's custom log path is ignored and errors land in the in-memory `wp-content/debug.log`. **An earlier run's "debug.log: empty" results were therefore invalid** and were redone | `setup.php` symlinks `wp-content/debug.log` → the mounted `tests/playground/logs/debug.log`. Verified: every settings-page load writes the expected `wp_get_sites()` deprecation to the host file |
| Automatic updates ran in the background on first boot | `AUTOMATIC_UPDATER_DISABLED` and `WP_AUTO_UPDATE_CORE` false in the blueprint |
| `wpmu_create_blog()` in the blueprint PHP warned about a missing `REMOTE_ADDR` | `setup.php` sets `127.0.0.1` when absent |
| `/siteb/wp-login.php` returns 404 (Playground has no `.htaccess`-style subsite rewrite for it). `--login` doesn't apply to `curl` | Log in on the main site `/wp-login.php` (`admin` / `password`); the cookie works on all sites, and `/siteb/wp-admin/*` loads. Documented |
| An earlier attempt hung for minutes | Caused by `lsof` hanging on this machine, not Playground. Removed from all commands; servers ran under a hard time limit |

## Acceptance criteria

| AC | Check | Result |
| --- | --- | --- |
| AC1 | `npm run playground` starts the server with no Docker; `@wp-playground/cli` ^3.1.66 in `devDependencies`, locked | PASS: "Ready! WordPress is running on http://127.0.0.1:80 (6 workers)" |
| AC2 | Network and content: `/` "My WordPress Website" `MAIN-SITE` ×1; `/sitea/` "Site A" `SHARED-FROM-A` ×1; `/siteb/` "Site B" `LOCAL-B` ×3 (primary, footer, widget); plugin active on B only and unconfigured; probe markers present; host `debug.log` works | PASS |
| AC2 | Live mount: a temporary file added to a mounted dir showed on the next request without restart, and disappeared when removed | PASS |
| AC2 | Auto-login via `--login` in a **browser** | **Not verified** (only `curl` was used, and `--login` doesn't apply to it). README says so |
| AC3 | `npm run playground -- --php=8.1` → banner "PHP 8.1"; default banner "PHP 8.3" | PASS. `--wp` override documented, not exercised |
| AC4 | After a run that deleted Site A and changed settings, a restart showed Site A again ("Site A" title) and B unconfigured; `preplayground` empties the log | PASS |
| AC5 | Full scenario table, PHP 8.3 and PHP 8.1 (identical results). See below | PASS |
| AC5 | Old code `f73ae27` (pre spec 002) mounted as the plugin: unconfigured Site B renders **`LOCAL-B` ×0**, so the bug reproduces | PASS |
| AC6 | `tests/playground` added to `phpcs.xml.dist`; `vendor/bin/phpcs tests/playground` → 0 errors, 0 warnings; no `phpcs:ignore`, no new exclusions | PASS |
| AC7 | QA files only under `tests/playground/`; none required by the plugin; `engines.node` `>=20.18`; `/tests/playground/logs/` git-ignored | PASS |
| AC8 | `tests/playground/README.md`; CLAUDE.md "Local env" → `npm run playground`, Workflow step 4 → Playground; Node note updated to ≥ 20.18 | PASS |
| AC9 | `specs/001–003/qa-plan.md` unchanged | PASS |

### Scenario results (PHP 8.3 and PHP 8.1, identical)

| Scenario on Site B | Result | Matches spec 002/003 |
| --- | --- | --- |
| Unconfigured | `LOCAL-B` ×3, probes `blog=3 switched=0` ×3 | Yes |
| Settings page, unconfigured | Options: empty, 1, 2 (never 3) | Yes |
| `?mfsqa_stack=1` unconfigured | Superseded: the first probe didn't exercise the plugin's switch. See "Stack probe fix" below | — |
| Saved via form: Site A + `primary` | `LOCAL-B` ×2 + `SHARED-FROM-A` ×1; settings show 2 selected, `primary` checked | Yes |
| Legacy string `primary` (via `options.php`) | `LOCAL-B` ×2 + `SHARED-FROM-A` ×1; `primary` still checked | Yes |
| Site A, no locations | `LOCAL-B` ×3 | Yes |
| Site ID empty/`0` + `primary` | `LOCAL-B` ×3 | Yes |
| `?mfsqa_stack=1` configured | Superseded: see "Stack probe fix" below | — |
| Deactivate, then activate (plugins screen) | State confirmed inactive, then active | Yes |
| Site A deleted (network admin) | `LOCAL-B` ×3, no warnings | Yes (spec 001 AC2) |
| `debug.log` across all scenarios | Only WP core `wp_get_sites()` deprecations (known issue #3); nothing from plugin files | Yes |

## Stack probe fix (after code review, 2026-11-23)

`/code-review` found that the original `?mfsqa_stack=1` probe rendered a menu by **menu ID** after `switch_to_blog( 1 )`. The plugin matches on `theme_location`, so it never switched, and the "outer switch survived" results above (and in spec 002's QA plan) only showed that a menu the plugin didn't touch leaves the stack alone. The shared-location case was never tested.

The probe now switches to the current blog (so B's settings still apply), renders `primary` and `footer` **by theme location**, and reports the blog and `ms_is_switched()` after each one. Run on PHP 8.3:

| Code | Scenario | `primary` | `footer` | Result |
| --- | --- | --- | --- | --- |
| Current (`maintenance`) | Unconfigured | `LOCAL-B`, blog 3, switched=1 | `LOCAL-B`, blog 3, switched=1 | PASS: outer switch kept |
| Current | Site A + `primary` (form) | `SHARED-FROM-A`, blog 3, switched=1 | `LOCAL-B`, blog 3, switched=1 | PASS: plugin switched to A and restored only its own switch |
| Pre-fix `f73ae27` | Unconfigured | empty (menus suppressed) | empty | Bug reproduced (spec 002) |
| Pre-fix `f73ae27` | Site A + `primary` | `SHARED-FROM-A`, blog 3, switched=1 | `LOCAL-B`, blog 3, **switched=0** | Bug reproduced: the unconditional restore after `footer` popped the outer switch |

So the probe now passes on the fixed code and fails on the old code for the case it's meant to cover. `vendor/bin/phpcs tests/playground`: 0 errors, 0 warnings. Spec 002's `qa-plan.md` wasn't edited (AC9); this section is the correction.

Not re-run: PHP 8.1 and the legacy-string location with the new probe. Neither changes which code paths the probe takes.

## Dependency audit

`npm audit` after adding `@wp-playground/cli` 3.1.66: **7 findings (1 high, 6 moderate), all transitive under `@wp-playground/cli`**:

- high: `isomorphic-git` path traversal on clone/checkout. The blueprint doesn't use git resources.
- moderate: `qs` DoS via `express`, which is the local dev server.
- moderate: `@wp-playground/*` packages, reported only via the above.

npm's suggested fix is `@wp-playground/cli@0.7.4`, a years-old major downgrade, so it wasn't applied. These are dev-only packages that run on the developer's machine. **Decision for Ben:** accept for now and re-check on the next CLI release, or track upstream.

## Not covered

- Linux: the port-80 requirement is documented but not tested.
- Browser auto-login (`--login`) and manual clicking through wp-admin in a real browser.
- `--wp` version override (documented, not run).
