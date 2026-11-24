# 005 — WordPress Playground QA environment

## Problem

Manual QA for specs 001–003 ran in a Docker multisite (WordPress + MariaDB + WP-CLI). It was rebuilt by hand from shell commands kept outside the repo, so:

- **It isn't repeatable or reviewable.** Nothing is committed. Each spec re-created sites, theme, menus, widget, `WP_DEBUG_LOG` and the QA probe mu-plugin from scratch.
- **Docker was unreliable on the dev machine.** The disk filled up so MariaDB couldn't start (spec 001), and the daemon hung three times and needed a manual restart (specs 002, 003). That blocked QA each time.
- **CLAUDE.md is out of date.** It names `npx wp-env start` (TBD) as the local environment, and wp-env also needs Docker.

WordPress Playground (`@wp-playground/cli`) runs WordPress on PHP-WASM under Node, with no Docker. A committed blueprint can describe the whole QA setup.

## Acceptance criteria

- [ ] AC1: One documented command (an npm script, e.g. `npm run playground`) starts a local WordPress Playground server with no Docker. `@wp-playground/cli` is a **dev** dependency, locked in `package-lock.json`.
- [ ] AC2: The command builds the QA environment from a committed blueprint:
  - a WordPress **multisite** with subdirectory sites: main site (1), Site A (2), Site B (3);
  - Twenty Twenty-One active on all three;
  - menus: main `MAIN-SITE`; A `SHARED-FROM-A` on `primary`; B `LOCAL-B` on `primary` **and** `footer`, plus a Navigation Menu widget showing B's menu;
  - the plugin **mounted from the working tree** (edits show without a rebuild) and activated on Site B only, unconfigured;
  - `WP_DEBUG` and `WP_DEBUG_LOG` on, with `WP_DEBUG_DISPLAY` off and a documented way to read `debug.log`;
  - the QA probe mu-plugin (`<!--mfsqa blog=N switched=0|1-->` after each menu, and `?mfsqa_stack=1`) installed;
  - an admin user logged in automatically.
- [ ] AC3: PHP and WordPress versions can be chosen when starting (at least PHP 8.1 and 8.3; default 8.3). The defaults are documented.
- [ ] AC4: Each start gives a fresh, known state, so a QA run never depends on leftovers from a previous one. If persistence is offered at all, it's opt-in and documented.
- [ ] AC5: The spec 002 and 003 QA scenarios can be run in the Playground environment and give the same results as recorded in their `qa-plan.md` files:
  - unconfigured: `LOCAL-B` ×3;
  - site A + `primary`: `LOCAL-B` ×2 + `SHARED-FROM-A` ×1;
  - legacy string location;
  - no locations, and source site ID `0`;
  - `?mfsqa_stack=1` on configured and unconfigured B;
  - deleted source site;
  - settings page: dropdown lists sites 1 and 2 only, and the saved site and location stay selected;
  - activate and deactivate with a clean `debug.log`.

  In addition, running the old code (spec 002's pre-fix `f73ae27`) in Playground reproduces the "no menus on unconfigured site" bug, so the environment can catch regressions and not just pass.
- [ ] AC6: Every committed QA file passes `composer lint`, including the probe mu-plugin and any PHP used by the blueprint. Its path is added to the `phpcs.xml.dist` file list. No new exclusions are added (spec 003 AC3 still holds).
- [ ] AC7: None of the QA files are loaded by the plugin at runtime. They live outside `inc/`, `admin/` and `views/` (e.g. `tests/playground/`). `package.json` `engines.node` is raised to Playground's minimum (≥ 20.18).
- [ ] AC8: Docs are updated.
  - CLAUDE.md: the "Local env" command becomes the Playground command, and the Workflow step 4 wording "manual step in wp-env" becomes Playground.
  - A short `tests/playground/README.md` covers how to start, choose PHP/WP, log in, read `debug.log`, use the probe, and reset.
- [ ] AC9: The QA steps in specs 001–003 aren't rewritten. Historical `qa-plan.md` files stay as they were. Future specs use the Playground environment.

## Out of scope

- Scripted or automated smoke tests, and running Playground in CI. (Possible follow-up spec.)
- PHPUnit integration tests and their host. The CLAUDE.md "Tests" line (wp-env) stays TBD for its own spec.
- A committed Docker setup or Docker fallback (by decision).
- Any plugin code change.
- MySQL-specific behaviour. Playground uses SQLite, which is accepted for QA.

## Known risks (accepted; not gated)

- **Multisite in Playground isn't well documented.** `enableMultisite` is documented only as defining constants. Creating subdirectory sites, routing `/sitea/` and `/siteb/`, and `switch_to_blog()` behaviour are unverified. If they don't work, stop and bring it back to Ben, with a proposed AC change.
- **SQLite instead of MySQL.** Unlikely to matter for this plugin, which uses options, menus and blog switching, but it differs from production.
- **Outbound network on start.** Downloading WordPress and Twenty Twenty-One needs network access unless cached.
- **Known CLI issue (3.1.64):** `login` + `defineWpConfigConsts` with multiple workers can lose constants. The workaround is documented upstream (`--workers=1` or `rewrite-wp-config`).
- **New dev dependency** `@wp-playground/cli`, updated often (3.1.65 → 3.1.66 in one week). It's locked in `package-lock.json`.

## Security flag: no

Dev-only tooling. No change to plugin runtime code, escaping, sanitization, capabilities, nonces or blog switching.

The probe mu-plugin exposes a debug query parameter (`?mfsqa_stack=1`), so AC7 keeps it out of the plugin's runtime paths, and it must never be shipped. The new npm dev dependency adds supply-chain surface on developer machines only, pinned by `package-lock.json`.

## Size

About 3–5 hours:

- blueprint and multisite setup ~1.5h (most of the risk);
- npm script, probe move and lint ~0.5h;
- docs ~0.5h;
- running the spec 002 and 003 scenarios plus the old-code repro ~1–2h.

Under the 8-hour limit.
