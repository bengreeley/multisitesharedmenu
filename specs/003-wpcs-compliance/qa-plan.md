# 003 — QA plan

## Tooling

- Installed: `squizlabs/php_codesniffer` 3.13.6, `wp-coding-standards/wpcs` 3.4.1, `phpcompatibility/phpcompatibility-wp` 2.1.8 (pulls `phpcompatibility/php-compatibility` **9.3.5**), `phpcsstandards/phpcsextra` 1.5.1, `phpcsstandards/phpcsutils` 1.2.3, `dealerdirect/phpcodesniffer-composer-installer` 1.2.1. All are `require-dev`; `composer.lock` is committed.
- **Limitation:** PHPCompatibility 9.3.5 is the latest stable release, and it predates PHP 8.1. `testVersion 8.1-` is set, but it can't detect most 8.1–8.3 deprecations. `php -l` on real PHP versions stays a QA step (below).
- **Prefix change from the approach:** WPCS rejects `mfs` as a prefix because it's under 4 characters (`PrefixAllGlobals.ShortPrefixPassed`). The configured prefixes are `menufromsite` and `mastersharedmenu`, so the class is `MasterSharedMenu_Options_Page` (matching `MasterSharedMenu_Activator`) rather than `MFS_Options_Page`. The activator's inner `mfs_version_warning()` is also renamed to `menufromsite_version_warning()`.

## Results so far (2026-11-20)

| AC | Check | Result |
| --- | --- | --- |
| AC1 | `composer install` then `composer lint` runs PHPCS with `phpcs.xml.dist` (WordPress + PHPCompatibilityWP, `testVersion 8.1-`) over the 6 plugin PHP files only | PASS |
| — | Baseline before code changes, with the final ruleset | 305 violations (spec's 303, minus 2 file-name cases now counted under the excluded sniff, plus the newly configured `PrefixAllGlobals` hits). PHPCompatibility: 0 |
| AC2 | `composer lint` after the changes | PASS: 0 errors, 0 warnings, exit 0 |
| AC3 | Exclusions: `WordPress.Security.EscapeOutput`, `WordPress.WP.DeprecatedFunctions.wp_get_sitesFound`, `WordPress.Files.FileName`, each commented | PASS. `grep -rn 'phpcs:'` in plugin files finds none |
| AC4 | `composer lint:fix` runs PHPCBF with the same config | PASS (used for the whitespace pass, with `--exclude=Universal.Operators.StrictComparisons` on the command line) |
| AC5 | String literals and inline HTML unchanged: every `T_CONSTANT_ENCAPSED_STRING` / `T_INLINE_HTML` token diffed against `HEAD` | PASS. Only the intended renames differ (`'menufromsite_activate'`, `'menufromsite_deactivate'`, `'menufromsite_version_warning'`) |
| AC5 | Spec 002 blog-aware harness (15 checks) on PHP 8.3.26 and 8.4.13 | PASS (also passed before the changes) |
| AC5 | Settings page in WordPress, old (`HEAD`) vs new, in 3 states (unconfigured; saved via the form as site A + `primary`; legacy string `primary`). `.wrap` HTML diffed with the nonce value stripped | PASS: **byte-identical** in all 3 states (1582 / 1611 / 1611 bytes). The dropdown lists sites 1 and 2 only (never B = 3); site A is preselected after saving; `primary` is ticked for both the array and legacy forms. Form save redirects with `settings-updated=true` |
| AC5 | Front end, old vs new, same 3 states | PASS: identical counts. Unconfigured `LOCAL-B` ×3; configured and legacy `LOCAL-B` ×2 + `SHARED-FROM-A` ×1. All probes `blog=3 switched=0` |
| AC5 | Remaining spec 002 scenarios on new code: site A + no locations; site ID `0` + `primary`; `?mfsqa_stack=1` unconfigured and configured; source site deleted (spec 001 AC2) | PASS: own menus ×3 where expected; the probe's outer switch survives and restores to B; no warnings |
| AC6 | `mfs_options_page` class → `MasterSharedMenu_Options_Page`; `activate_menufromsite`/`deactivate_menufromsite` → `menufromsite_activate`/`menufromsite_deactivate`; old names grep → none; file names unchanged | PASS. Activate and deactivate in WordPress with a clean `debug.log` |
| AC5/AC6 | `debug.log` across all WordPress scenarios | Only WP core's `wp_get_sites()` deprecation on settings-page loads, identical for old and new code (known issue #3, excluded). Nothing from plugin files |
| AC7 | Dev-only deps; plugin never requires `vendor/`; `/vendor/` in `.gitignore` | PASS |
| AC8 | CLAUDE.md lint commands no longer TBD | PASS |
| — | `php -l` on all plugin files | PASS on 8.3.26 and 8.4.13. 8.1 not available locally |

## Behaviour-relevant manual changes (review closely)

- `views/options-page.php`:
  - `get_current_blog_id() !== (int) $site['blog_id']`
  - `(int) get_option( 'mfs_override_site_id' ) === (int) $site['blog_id']`. The old `esc_attr()` around a compared-only value was dropped.
  - `in_array( ..., true )`
  - `$checked == true` → `$checked`
- `inc/class-menufromsite.php`:
  - The assignment is moved out of the `if`.
  - `$menu_object->theme_location === $menu`
  - The trailing `return;` → `return null;`
  - `check_restore_blog_menu()` drops the unused `$args`, and its filter is registered with `accepted_args` 1 (priority unchanged).

## WordPress environment (2026-11-20)

Same as spec 002: `wordpress:php8.3-apache` (PHP 8.3), WordPress 7.1.5, Twenty Twenty-One, the main site plus sites A and B, B's menu on `primary` and `footer` plus a Navigation Menu widget, and the QA-only `mfsqa-probe.php` mu-plugin. The old code is `git archive HEAD` installed as `mfs-old`. It was run on its own, because both versions define the same classes. The environment was torn down afterwards.
