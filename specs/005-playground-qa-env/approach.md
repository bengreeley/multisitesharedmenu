# 005 — Approach

Spec: `specs/005-playground-qa-env/spec.md`. Security flag: no.

**Gate note:** Ben approved the spec and asked to build directly (2026-11-23). This approach was written before the build so the build would stay inside the dev notes. Ben reviews it at PR review instead of a separate Gate 2. The Design and Tests sections below were updated after the build to describe what was actually built. "Changes during build" lists where that differs from the original plan.

## Changes during build (2026-11-23)

- **Port 80 + `--site-url=http://localhost`** in the script: Playground refuses multisite on `127.0.0.1:9400` ("multisites do not support custom ports").
- **Debug log via symlink**, not a `WP_DEBUG_LOG` path: Playground pre-defines `WP_DEBUG_LOG=true`, so `setup.php` symlinks `wp-content/debug.log` to the mounted log. An `ini_set()` mu-plugin was tried and dropped, because WPCS `WordPress.PHP.IniSet` warns and ignores aren't allowed.
- **Auto-updates disabled** and **`REMOTE_ADDR` default** in setup, to keep the state known and the log clean.
- **Admin access** via main-site `/wp-login.php`, because Playground 404s `/siteb/wp-login.php`.
- **State changes** for QA go through real admin screens (settings form, `options.php` for the legacy string, network admin for deleting a site), with no QA endpoints that change data.

## Design

- **`package.json`:**
  - `devDependencies`: `@wp-playground/cli` `^3.1.66` (exact version locked in `package-lock.json`).
  - `engines.node`: `>=20.18` (AC7).
  - Script `playground`: `wp-playground-cli server` with:
    - `--blueprint=tests/playground/blueprint.json`
    - `--mount=.:/wordpress/wp-content/plugins/multisitesharedmenu` (working tree, live edits; AC2)
    - `--mount=tests/playground/mu-plugins:/wordpress/wp-content/mu-plugins`
    - `--mount=tests/playground/logs:/wordpress/wp-content/qa-logs`
    - `--login`
    - `--port=80 --site-url=http://localhost` (multisite needs a URL with no port)
  - PHP and WP default to the CLI defaults (PHP 8.3, latest WP). Override them with `npm run playground -- --php=8.1 --wp=6.8` (AC3). `--php` was verified; `--wp` is documented but wasn't run.
  - A `preplayground` script (Node one-liner, cross-platform) creates `tests/playground/logs/` and empties `debug.log`.
- **Fresh state (AC4):** `server` keeps the site in a temp dir, and only the plugin, mu-plugin and log dirs are mounted, so the database is rebuilt on every start. No persistence option is offered.
- **`tests/playground/blueprint.json`:**
  - `defineWpConfigConsts` with `method: rewrite-wp-config`, which avoids the documented multi-worker constants bug:
    - `WP_DEBUG` and `WP_DEBUG_LOG` true, `WP_DEBUG_DISPLAY` false;
    - `AUTOMATIC_UPDATER_DISABLED` true and `WP_AUTO_UPDATE_CORE` false.
  - `enableMultisite`, then `installTheme` Twenty Twenty-One (not activated).
  - One `runPHP` step that `require`s `wp-load.php` and the committed `tests/playground/setup.php`. Setup lives in a real, linted PHP file rather than inline JSON (AC6). The mounts are in place before blueprint steps run, so no `--mount-before-install` or `writeFile` was needed.
- **`tests/playground/setup.php`** (WordPress APIs, idempotent, prefixed `menufromsite_qa_`):
  - Sets `$_SERVER['REMOTE_ADDR']` to `127.0.0.1` if it's empty, because blueprint PHP runs outside a web request.
  - Symlinks `wp-content/debug.log` to `wp-content/qa-logs/debug.log`, so the log is readable on the host at `tests/playground/logs/debug.log`.
  - Network-enables Twenty Twenty-One. On each site, it switches to that theme, creates the menu (`wp_create_nav_menu()` + `wp_update_nav_menu_item()`) and assigns its locations (`set_theme_mod( 'nav_menu_locations' )`).
  - Creates Site A and Site B with `wpmu_create_blog()` (subdirectories under the main site's domain). It throws a `RuntimeException` on failure.
  - Adds B's Navigation Menu widget (`widget_nav_menu` option + `sidebars_widgets`).
  - Runs `activate_plugin()` on Site B only. Each `switch_to_blog()` is matched by a `restore_current_blog()`.
- **`tests/playground/mu-plugins/mfsqa-probe.php`:** the spec 002 probe, made WPCS-compliant with prefixed names and docblocks.
  - The `?mfsqa_stack=1` flag is read from `$_SERVER['QUERY_STRING']` (`wp_unslash` + `sanitize_text_field`, then `parse_str`), not `$_GET`.
  - The WPCS nonce and input sniffs pass with no `phpcs:ignore`.
  - The probe only reads and reports; it changes no data.
- **`phpcs.xml.dist`:** adds `<file>tests/playground</file>` (AC6). The prefix list already covers `menufromsite`/`mastersharedmenu`.
- **`.gitignore`:** adds `/tests/playground/logs/`.
- **Docs (AC8):**
  - `tests/playground/README.md`: start and stop, versions, the port 80 requirement, sites, login, debug log, probe, admin URLs, known limits.
  - CLAUDE.md: the "Local env" line, Workflow step 4 "manual step in wp-env" → Playground, and the Node note → ≥ 20.18.

## Dev notes

### May touch

- New: `tests/playground/**` (blueprint, setup, probe, README).
- `package.json` / `package-lock.json`: devDependency, engines, scripts.
- `.gitignore`, `phpcs.xml.dist` (file list only), `CLAUDE.md` (Local env line, step 4 wording, Node note).
- `specs/005-playground-qa-env/`.

### Off-limits (stop and ask)

- Plugin PHP (`multisitesharedmenu.php`, `inc/`, `admin/`, `views/`), including Ben's staged `==` test change, which must not be committed.
- New PHPCS exclusions or `phpcs:ignore`.
- Earlier specs' `qa-plan.md` (AC9).
- CI workflow.

### Tests

- Start `npm run playground`, then use `curl` against `http://localhost` (sites at `/`, `/sitea/`, `/siteb/`) and the logs mount to run the spec 002/003 scenario table. The driver script is a throwaway in the scratchpad, not committed (scripted smoke tests are out of scope).
- Log in for admin pages with a cookie jar via the **main site's** `/wp-login.php` (`admin` / `password`). The cookie works on every site.
- Change state only through real admin screens:
  - the settings form;
  - `/siteb/wp-admin/options.php` for the legacy single-string location;
  - the plugins screen to deactivate and activate;
  - network admin to delete Site A.

  No QA endpoints that change data, and no new runtime hooks in the plugin.
- Repeat with `-- --php=8.1`.
- Old-code repro: mount `git archive f73ae27` as the plugin instead of the working tree.
- `composer lint` must pass with the new files in scope.
