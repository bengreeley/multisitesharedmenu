# 001 — PHP 8.3 compatibility

## Problem

The plugin was last released in 2015 (v1.2) and has never been validated on PHP 8. It must run on PHP 8.1, 8.2 and 8.3 without fatal errors, warnings or deprecation notices, and it should declare its minimum PHP version so WordPress blocks installs on unsupported hosts.

Known PHP 8 problems found in review:

1. **Fatal on deactivation (confirmed).** `MasterSharedMenu_Deactivator::deactivate()` is static and calls the private *non-static* `unregister_pluginfields()` statically (`inc/class-menufromsite-deactivator.php:8`). PHP 8 throws `Error: Non-static method ... cannot be called statically`, so deactivating the plugin fatals.
2. **Warning when the source site no longer exists.** `menuswitch_check()` reads `$switchSite->blog_id` without checking that `get_blog_details()` returned an object (`inc/class-menufromsite.php:41-42`). If the configured source site was deleted, PHP 8 emits `Warning: Attempt to read property "blog_id" on bool` on every menu render, and passes null to `switch_to_blog()`.
3. **No declared PHP requirement.** Neither the plugin header nor `readme.txt` has `Requires PHP`.

## Repro steps

1. Multisite on PHP 8.3, plugin network-activated or activated on a site.
   Deactivate the plugin → fatal error (issue 1).
   Quick repro without WP: `php -r 'function unregister_setting(){} require "inc/class-menufromsite-deactivator.php"; MasterSharedMenu_Deactivator::deactivate();'`
2. On site B, set Source Site = site A and pick a menu location. Delete site A from the network. Load a front-end page on site B with `WP_DEBUG` on → warning (issue 2).

## Acceptance criteria

- [ ] AC1: Activating and deactivating the plugin on PHP 8.3 completes with no errors, warnings or notices.
- [ ] AC2: With a deleted/invalid source site configured, front-end pages render the site's own menu and emit no PHP warnings.
- [ ] AC3: With a valid configuration, the shared menu renders exactly as before (no behaviour change on the happy path).
- [ ] AC4: Loading the Shared Menu Settings page and saving it on PHP 8.3 produces no PHP errors, warnings or PHP-level deprecations (WordPress's own `wp_get_sites()` deprecation notice is out of scope — see below).
- [ ] AC5: `php -l` passes on every PHP file under PHP 8.1 and 8.3.
- [ ] AC6: Plugin header and `readme.txt` declare `Requires PHP: 8.1`.

## Out of scope (each has its own spec per CLAUDE.md known issues)

- `pre_wp_nav_menu` returning `false` on unconfigured sites (known issue 1).
- Balancing `switch_to_blog()` / `restore_current_blog()` in general, incl. fallback paths (known issue 2). This spec only guards the null-site case.
- Replacing `wp_get_sites()` (known issue 3).
- Escaping/sanitization on the options page (known issue 4).
- Version bump, "Tested up to", http links, i18n (known issue 5).
- Adding Composer/PHPCompatibility/PHPUnit/wp-env tooling (separate spec).
- Raising the minimum to PHP 8.3.

## Security flag: no

No change to output escaping, input sanitization, capabilities or nonces. The only blog-switching change makes the plugin switch *less* (skips switching when the source site doesn't exist); it doesn't add a new switch path.

## Approach (trivial fix — reviewed at PR)

- `inc/class-menufromsite-deactivator.php`: make `unregister_pluginfields()` `private static` and call it via `self::`.
- `inc/class-menufromsite.php`: in `menuswitch_check()`, only call `switch_to_blog()` when `get_blog_details()` returns an object.
- `multisitesharedmenu.php` header and `readme.txt`: add `Requires PHP: 8.1`.
- Files off-limits: everything else (`views/options-page.php`, admin class) unless AC4 testing turns up a PHP-level issue — then stop and ask.

## Verification

- No lint/test tooling exists yet; verification is manual (noted per CLAUDE.md).
- `php -l` on all files under 8.1 and 8.3 (AC5).
- CLI repro above returns cleanly (AC1, deactivation half).
- Local multisite on PHP 8.3 with `WP_DEBUG` + `WP_DEBUG_LOG`: run AC1–AC4 manually and confirm `debug.log` has no plugin-originated entries. Details go in `qa-plan.md` at the Review stage.

## Size

~1–2 hours. Well under the 8-hour limit.
