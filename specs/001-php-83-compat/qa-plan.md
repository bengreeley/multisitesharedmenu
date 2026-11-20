# 001 — QA plan

No lint or test tooling exists yet (no Composer, PHPCS, PHPUnit or wp-env), so every check below is a CLI stub check or a manual check in a throwaway Docker multisite.

## Environment (manual checks, 2026-11-20)

- `wordpress:php8.3-apache` (PHP 8.3.37), WordPress 7.1.5, MariaDB 11.4, subdirectory multisite
- `WP_DEBUG` on, `WP_DEBUG_LOG` on, `WP_DEBUG_DISPLAY` off. `debug.log` cleared before each step
- Sites: A (`/sitea/`, ID 2) and B (`/siteb/`, ID 3), both on Twenty Twenty-One. A's `primary` menu has the item `SHARED-FROM-A`; B's has `LOCAL-B`
- Plugin mounted read-only from the working tree and activated on site B only

## Results

| AC | Check | Result |
| --- | --- | --- |
| AC1 | Activate on B (WP-CLI) | Pass: `debug.log` empty |
| AC1 | Deactivate on B (WP-CLI) | Pass: `debug.log` empty. Pre-fix code: `PHP Fatal error: Uncaught Error: Non-static method MasterSharedMenu_Deactivator::unregister_pluginfields() cannot be called statically` |
| AC1 | CLI stub of `deactivate()` | Pass on 8.3.26 and 8.4.13 |
| AC2 | Delete site A, load B's front end | Pass: HTTP 200, B shows `LOCAL-B`, `debug.log` empty. Pre-fix code: `PHP Warning: Attempt to read property "blog_id" on false ... class-menufromsite.php on line 42` |
| AC2 | CLI stub, `get_blog_details()` returns `false` | Pass on 8.3/8.4: no warning, no switch |
| AC3 | Valid config (source = A, location = `primary`), load B's front end | Pass: B shows `SHARED-FROM-A`, title still "Site B", `debug.log` empty |
| AC4 | Log in as admin, load settings page, save source = A + `primary` | Pass: page renders, save redirects with `settings-updated=true`, saved values reselected. Only log entries: WP core's `wp_get_sites()` deprecation (x2), excluded by spec |
| AC5 | `php -l` on all PHP files | Pass on 8.3.26 and 8.4.13. **8.1 not run**: the local Homebrew `php@8.1` keg resolves to 8.4.13 |
| AC6 | `Requires PHP: 8.1` in plugin header and `readme.txt` | Pass (diff) |

## Observed, out of scope

- With the plugin active but not configured, site B rendered **no** primary menu. This confirms known issue #1 (`pre_wp_nav_menu` returns `false`). Not changed by this spec.

## Manual steps (to re-run)

1. Create sites A and B on the same classic theme, and give each a `primary` menu with distinct items.
2. On B, activate the plugin and check `debug.log` (AC1).
3. Load and save Appearance → Shared Menu Settings with Source Site = A and `primary` checked (AC4).
4. Load B's front end. It should show A's menu (AC3).
5. Delete site A, then load B's front end. It should show B's own menu with no warnings (AC2).
6. Deactivate the plugin on B with no fatal error (AC1).
