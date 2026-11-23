# 002 — Menus disappear on unconfigured sites

## Problem

On any site where the plugin is active but its settings are incomplete, every classic nav menu (`wp_nav_menu()`) renders empty. Admins who activate the plugin network-wide, or before configuring it, lose their site navigation with no error or notice.

### Cause

`MasterSharedMenu::menuswitch_check()` is hooked to `pre_wp_nav_menu` (`inc/class-menufromsite.php:21`). When `validate_mfs_set()` finds the settings incomplete, it returns `false` (`inc/class-menufromsite.php:28`). WordPress's `wp_nav_menu()` treats any `pre_wp_nav_menu` value other than `null` as a finished menu, so it outputs `false` (an empty string) and stops. Only `null` lets WordPress render normally, as CLAUDE.md already requires.

"Incomplete" means any of:

- `mfs_override_site_id` not saved, empty, or non-numeric
- `mfs_override_menu_location` not saved or empty, e.g. settings saved with a source site but no location ticked

It affects every `wp_nav_menu()` call on the site: all theme locations and Navigation Menu widgets, not just locations the plugin would override. Block-theme Navigation blocks don't call `wp_nav_menu()` and aren't affected.

## Repro steps

1. Multisite with two sites on a classic theme (e.g. Twenty Twenty-One), each with a menu assigned to `primary`.
2. Activate the plugin on site B. Don't open or save Appearance → Shared Menu Settings.
3. Load site B's front end. **Expected:** B's own primary menu. **Actual:** no menu rendered.
4. Variation: save the settings with a source site selected but no menu location ticked, then reload. The menu is still missing.

Observed during spec 001 QA (WordPress 7.1.5, PHP 8.3): site B with the plugin active and unconfigured rendered no primary menu, and rendered its own menu before activation.

## Acceptance criteria

- [ ] AC1: With the plugin active and no settings saved, every menu on the site (all theme locations and Navigation Menu widgets) renders exactly as it does with the plugin inactive.
- [ ] AC2: Same as AC1 when settings are saved with a source site but no menu location, and when the saved source site ID is empty or `0`.
- [ ] AC3: When the plugin is configured, locations **not** selected in its settings render the site's own menus (no regression).
- [ ] AC4: When the plugin is configured, selected locations still render the source site's menu (no regression).
- [ ] AC5: On an unconfigured site, rendering a menu causes no `switch_to_blog()` and no `restore_current_blog()` calls, so the blog-switch stack is unchanged after each `wp_nav_menu()`.
- [ ] AC6: No PHP errors, warnings or notices from plugin files in any of the scenarios above (`WP_DEBUG_LOG` on).

## Out of scope

- General switch/restore balancing on configured sites, incl. `fallback_cb` paths (known issue #2). AC5 only covers unconfigured sites.
- Admin notice prompting the user to configure the plugin.
- Replacing `wp_get_sites()` (known issue #3), options-page escaping and sanitization (known issue #4), housekeeping (known issue #5).
- Block-theme Navigation block support.
- Lint/test tooling (separate spec). Verification may be manual until it exists.

## Security flag: yes

The change sits on the `pre_wp_nav_menu` → `wp_nav_menu_items` blog-switching path. Today, unconfigured sites never reach `wp_nav_menu_items` because the menu is short-circuited. Once menus render again, `check_restore_blog_menu()` will run its unconditional `restore_current_blog()` there. If another plugin or theme has switched blogs around a menu render, that call would pop *their* switch and leave the rest of the page in the wrong site's context, which is an integrity risk. AC5 guards this. A `threat-model.md` section (Tampering / Information disclosure around blog context) is needed at the Plan stage.

## Size

Small: about 2–3 hours including the threat-model note and QA. Well under the 8-hour limit.
