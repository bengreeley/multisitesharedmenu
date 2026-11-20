# 002 — Approach

Spec: `specs/002-unconfigured-menu/spec.md`
Threat model: `specs/002-unconfigured-menu/threat-model.md` (security flag is yes)

## Summary

Make the unconfigured path a true no-op. The plugin must not short-circuit the menu, switch blogs or restore blogs when its settings are incomplete. All three changes stay inside `MasterSharedMenu` in `inc/class-menufromsite.php`.

## Changes

### 1. Stop short-circuiting the menu (AC1, AC2)

`menuswitch_check()` returns `false` when `validate_mfs_set()` fails (`inc/class-menufromsite.php:28`). Change it to pass the incoming filter value through unchanged:

- Rename the unused first parameter `$a` to `$output`.
- On the unconfigured path, `return $output;`.

Passing through, rather than hard-coding `null`, keeps WordPress rendering normally (`$output` is `null` unless another filter has already built a menu). It also doesn't overwrite another plugin's `pre_wp_nav_menu` output. The configured path's bare `return;` at `:48` stays as it is, because changing it belongs to known issue #2.

### 2. Treat a source site ID of `0` as unconfigured (AC2, AC5)

The settings page saves `mfs_override_site_id` through `intval`, so submitting "-- Select --" stores `0`. `validate_mfs_set()` currently accepts `'0'` because it has a length and is numeric. `get_blog_details( 0 )` then falls back to the *current* site, so the plugin switches to itself and back. The menu still renders, but this breaks AC5's "no switch on unconfigured sites".

Change: `validate_mfs_set()` also returns `false` when `(int) $sourceSiteID < 1`. Keep the existing `strlen` / `empty` / `is_numeric` checks so stored values stay backward-compatible. That includes the old single-string `mfs_override_menu_location`, which `empty()` already handles.

### 3. Restore only when this plugin switched (AC4, AC5)

Once menus render again, unconfigured sites reach `wp_nav_menu_items`, and `check_restore_blog_menu()` calls `restore_current_blog()` unconditionally. With no matching switch, that call pops someone else's switch if one is active.

Change (revised at Gate 2 re-review, 2026-11-20):

- Add `protected $switched = false;` to `MasterSharedMenu`.
- In `menuswitch_check()`, set it to `true` right after `switch_to_blog()`, then `break` out of the location loop. Only one location can match a menu, and the `break` stops a duplicated stored location from switching twice.
- In `check_restore_blog_menu()`, return `$items` untouched unless the flag is set. Otherwise clear the flag and call `restore_current_blog()`.

**Why not guard on the settings (the original plan):** `check_restore_blog_menu()` runs *after* the switch, so `get_option()` there reads the **source** site's settings. Source sites are normally unconfigured, so the guard skipped the restore and left the rest of the page on the source site. The blog-aware harness confirmed this regression before the plan was revised (AC4 failed, with the page left on site A).

**Side effect, accepted by Ben:** configured sites also stop restoring on menus whose location wasn't switched. That removes the "restore without switch" half of known issue #2. The `fallback_cb` half remains: a switched menu that ends in a fallback never reaches `wp_nav_menu_items`, so the switch leaks. The flag stays set, so the next rendered menu restores it. That's no worse than today, and it stays tracked under known issue #2.

## Dev notes

### May touch

- `inc/class-menufromsite.php`: only `menuswitch_check()`, `check_restore_blog_menu()`, `validate_mfs_set()` and the new `$switched` property.
- `specs/002-unconfigured-menu/`: `qa-plan.md` and any QA harness notes.

### Off-limits (stop and ask first)

- `admin/`, `views/`, `multisitesharedmenu.php`, `readme.txt`, activator and deactivator.
- Option names, stored formats, the `intval` sanitize callback, and the settings page. The `0` case is handled on read, not on save.
- The configured path's switching logic beyond setting the flag and the `break`, its bare `return;`, and `fallback_cb` handling (known issue #2).
- Version numbers and changelog (Release stage).

### Patterns to follow

- Match the file's existing style: tabs, `if(` spacing, camelCase locals such as `$mfsSettings`, and short `//` comments above methods. Don't reformat lines you aren't changing.
- `pre_wp_nav_menu` callbacks return the incoming value or `null`. Never `false`.
- Every `switch_to_blog()` has exactly one `restore_current_blog()`. Never restore without a switch. Never decide whether to restore from options read after a switch.

### Tests

- No PHPUnit or lint tooling exists yet. That's a separate spec, and the PR will say so.
- Before changing code, write a throwaway CLI stub harness in the scratchpad, like the one in spec 001. Its `get_option()` must be **blog-aware** (per-site options plus a real switch stack). It shows the current failures:
  - `menuswitch_check()` returns `false` when unconfigured.
  - `check_restore_blog_menu()` calls restore when nothing switched.
  - Site ID `0` triggers a switch.

  The harness should then pass after the change. It won't be committed.
- Then do manual QA in the Docker multisite from spec 001: unconfigured, no locations ticked, ID `0`, configured with selected and unselected locations, and a Navigation Menu widget. Check `debug.log` throughout. To prove AC5 in WordPress, use a small mu-plugin in the QA environment only that logs `get_current_blog_id()` and `ms_is_switched()` after each `wp_nav_menu()`, plus a case where a test mu-plugin switches blogs around a menu render.

## Risks

- **Stale flag after a `fallback_cb` leak:** the next menu restores the leaked switch. That's the same or better than today; known issue #2.
- **Pass-through behaviour change:** a plugin that previously relied on this one blanking menus would see them return. That's the intended fix.

## Size

About 2 hours of build and QA. Within the spec's estimate.
