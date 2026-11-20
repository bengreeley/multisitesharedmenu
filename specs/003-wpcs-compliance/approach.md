# 003 — Approach

Spec: `specs/003-wpcs-compliance/spec.md`
Security flag: no, so there's no threat model.

## Summary

Add PHPCS tooling first, so every later step is measured. Then let PHPCBF do the whitespace, and make the remaining fixes by hand in small, reviewable commits-worth of change, keeping behaviour identical. Regression QA reuses the spec 002 harness and Docker scenarios.

## Decision for Ben (needed at Gate 2)

**Resolved at Gate 2 (2026-11-20): Ben said "build" on the recommendations.** Both sniffs configured, functions renamed, spec AC6 amended; trailing `return;` becomes `return null;`.

**Should `PrefixAllGlobals` and `I18n` be configured?** Both WPCS sniffs stay dormant until they're given prefixes and a text domain. Configured with prefixes `mfs`, `menufromsite`, `mastersharedmenu` and text domain `multisitesharedmenu`, they add 13 errors:

- 11 are view-template variables in `views/options-page.php` (`$blogList`, `$curLocation` and so on). They're file-scope to PHPCS, though really local to the method that includes the view. They're being renamed to snake_case anyway, so adding an `mfs_` prefix costs nothing.
- 1 is `$options_page` in the admin file. That's the same situation: rename it to `$mfs_options_page`.
- 2 are the global functions `activate_menufromsite()` and `deactivate_menufromsite()`. They'd become `menufromsite_activate()` and `menufromsite_deactivate()`. They're only referenced by `register_*_hook()` in the same file, but they are public global functions, and spec AC6 only authorises renaming the class.

**Recommendation:** configure both sniffs, so future code is checked against CLAUDE.md's "prefix new functions" and "add a text domain" rules, and rename the two functions. That needs a one-line amendment to spec AC6: "...and the global activation/deactivation functions are renamed to prefixed names". If you'd rather not touch the functions, leave both sniffs unconfigured (dormant). AC2 still passes, but nothing enforces the prefix rule.

## Changes

### 1. Tooling (AC1, AC4, AC7, AC8)

- **`composer.json`** (new):
  - `require-dev`: `wp-coding-standards/wpcs` `^3.1`, `phpcompatibility/phpcompatibility-wp` (latest stable `^2`), `dealerdirect/phpcodesniffer-composer-installer` `^1.0`.
  - `config.allow-plugins` for the installer.
  - Scripts: `lint` → `phpcs`, `lint:fix` → `phpcbf`.
  - No `require` section beyond `php: >=8.1`.
- **`composer.lock`**: commit it, so `composer lint` gives the same answer for everyone. This is a dev-tool lock and doesn't ship.
- **`phpcs.xml.dist`** (new):
  - Files: `multisitesharedmenu.php`, `inc/`, `admin/`, `views/`.
  - Excludes `vendor/` and `specs/`, with `--extensions=php`.
  - Rules: `WordPress` plus `PHPCompatibilityWP`, `testVersion` `8.1-`.
  - Exactly the three exclusions in spec AC3, each with a comment.
  - Prefix and text-domain properties, depending on the decision above.
  - Uses `-s` and `-p` and runs in parallel.
- **`.gitignore`** (new): `/vendor/`.
- **`CLAUDE.md`**: in Commands, drop "TBD" from `composer lint` / `composer lint:fix` (AC8). `composer test` and `wp-env` stay TBD. This is the only CLAUDE.md edit.
- **Watch out:** PHPCompatibility 9.x (what `phpcompatibility-wp ^2` pulls) has only partial PHP 8.1+ sniffs. If that's still the case at build time, note it in `qa-plan.md` and keep `php -l` on 8.1 and 8.3 as a QA step rather than claiming full 8.1–8.3 coverage. Don't pull an unstable PHPCompatibility 10 without asking.
- **Watch out:** `phpcbf` exits `1` when it fixed something, which Composer reports as a failure. That's acceptable for `lint:fix`; note it in CLAUDE.md only if Ben wants it.

### 2. Automatic fixes, excluding the risky sniff (~210 violations)

Run `composer lint:fix` with `Universal.Operators.StrictComparisons` excluded **for that run only** (`--exclude=` on the command line, not in the ruleset), so PHPCBF can't turn `!=` into `!==` blindly. Then review the diff. It should be whitespace, indentation, spacing, EOF newlines and `new X()` parentheses only. Any non-whitespace change PHPCBF makes gets checked by hand.

### 3. Manual fixes

**Naming (AC6):**

- Rename local variables to snake_case. Spec 002's `$switched` and `$output` are already fine. Map for the busy file: `$mfsSettings` → `$mfs_settings`, `$sourceSiteID` → `$source_site_id`, `$destinationMenuLocation` → `$destination_menu_location`, `$switchSite` → `$switch_site`.
- In `views/options-page.php`, use the `mfs_` prefix if the decision above is "configure": `$mfs_sites`, `$mfs_site`, `$mfs_locations`, `$mfs_saved_locations`, `$mfs_option_count`, `$mfs_location`, `$mfs_checked`.
- Rename `mfs_options_page` → `MFS_Options_Page`, and `$options_page` → `$mfs_options_page`. A `grep -rn mfs_options_page` afterwards must find only method names (`mfs_options_page()`, the menu callback, `mfs_options_admin_menu()`), never the class.
- Keep option names, hook names, the settings group `menufromsite-group`, the page slug `manage-mfs-options.php` and the method names hooked by string unchanged.

**Strictness (behaviour risk in the spec), done by hand with explicit casts:**

- `views/options-page.php:26`: `(int) $site['blog_id'] !== get_current_blog_id()`, in Yoda form where WPCS requires it.
- `views/options-page.php:30`: compare `(int) get_option( 'mfs_override_site_id' ) === (int) $site['blog_id']`. Drop the pointless `esc_attr()` around a value that's only compared; it's not output.
- `views/options-page.php:62`: `in_array( $location, $saved_locations, true )`. Locations are strings in both the array and legacy forms, so strict is safe. Cast the legacy value with `(array)` rather than wrapping it by hand, keeping the existing `is_array()` branch semantics.
- `views/options-page.php:69`: `$checked == true` → just `$checked`, since it's already a bool.
- `inc/class-menufromsite.php` `menuswitch_check()`: `$menu == $menu_object->theme_location` → `$menu_object->theme_location === $menu`. Both are strings. Check `theme_location` is a string when unset (it's `''` in core args), so the comparison result doesn't change.

**Other code checks:**

- `menuswitch_check()`: move the assignment out of the `if`, as `$mfs_settings = $this->validate_mfs_set(); if ( ! $mfs_settings ) { return $output; }`. Same semantics.
- Replace the trailing bare `return;` in `menuswitch_check()` with `return $output;`. **Stop and check:** this is behaviour-neutral only while `$output` is `null` on the configured path. If another filter already supplied output, `return $output` would keep it, while today's `return;` discards it. That's a behaviour change, and it interacts with the `$switched` flag (a switch would leak). **Recommended: change it to `return null;`**, which satisfies `NonExecutableCode` (an explicit value isn't redundant) and changes nothing. Leave the pass-through question to the known-issue-#2 spec.
- `check_restore_blog_menu()`: drop the unused `$args` parameter and register the filter with `accepted_args` `1`. The hook name and priority stay the same. WordPress passes only as many args as are accepted, so behaviour is identical.

**Docblocks (WordPress-Docs, ~40):**

- File header with `@package MultisiteSharedMenu` in each PHP file.
- Class docblocks, `@var` on properties, and `@param` / `@return` on every function.
- Convert `//` comments above methods into docblocks and end inline comments with punctuation.
- Describe what the code does today. Don't document intended future behaviour.

### 4. Leave alone

- `EscapeOutput` lines and the `wp_get_sites()` call: excluded, owned by #4 and #3. Don't add escaping "while you're there".
- The activator's nested `mfs_version_warning()` and its logic: format only.

## Dev notes

### May touch

- `multisitesharedmenu.php`, `inc/*.php`, `admin/class-menufromsite-admin-options.php`, `views/options-page.php`: formatting, docblocks, local renames, strict comparisons and the specific changes listed above.
- New files: `composer.json`, `composer.lock`, `phpcs.xml.dist`, `.gitignore`.
- `CLAUDE.md`: the Commands lines for lint only.
- `specs/003-wpcs-compliance/`.

### Off-limits (stop and ask first)

- Option names and stored formats, hook names and priorities, the settings group and page slug, method names that are hooked by string, and file names.
- Escaping, sanitization, capabilities and nonces (known issue #4). `wp_get_sites()` (#3). i18n strings and the readme (#5).
- Logic in `menuswitch_check()` / `check_restore_blog_menu()` beyond the listed mechanical changes. The spec 002 flag behaviour must be identical.
- Adding inline `phpcs:ignore`, or any exclusion beyond the three in spec AC3.
- Runtime dependencies or autoloading.

### Patterns to follow

- WPCS style: tabs, spaces inside parentheses, Yoda conditions, `array()` long syntax (matches existing code and WPCS default), and `! $x` with a space.
- Keep diffs reviewable. Separate the tooling, the PHPCBF whitespace pass and the manual fixes, so a reviewer can skim the whitespace change and read the manual changes closely. That can be one PR with three logical commits, if Ben agrees at commit time.

### Tests

- **Before** any code change: run `composer lint` and record the baseline counts in `qa-plan.md`. They should match the spec's 303, plus any PHPCompatibility findings and the dormant-sniff findings if configured.
- After each step, run `composer lint`. It must reach 0 errors and 0 warnings (AC2).
- Run the spec 002 blog-aware harness before and after. All 15 checks must still pass (AC5).
- Run `php -l` on PHP 8.3 and 8.4, and on 8.1 if one is available.
- Docker multisite on PHP 8.3, reusing the spec 002 setup and probe: run the full spec 002 scenario table, plus the settings page.
  - The Source Site dropdown must never list site B itself, and must preselect A after saving.
  - Location ticks must show for both the array and the legacy string forms.
  - Compare the rendered settings-page HTML before and after. Only expected attribute-order or whitespace differences are allowed.
- Activate and deactivate in WordPress, with a clean `debug.log`. This checks the renamed class and, if renamed, the activation functions.

## Risks

| Risk | Mitigation |
| --- | --- |
| PHPCBF or a manual strict comparison changes which site or location shows as selected | Excluded from the PHPCBF run; explicit casts; before/after settings-page HTML diff in QA |
| Renaming a class or function breaks a third party referencing it | Low: nothing documents them as API. Called out in the PR and changelog at Release |
| Docblocks drift into describing intended behaviour | Review rule above |
| PHPCompatibility under-reports 8.1+ issues | `php -l` stays in QA; noted honestly in `qa-plan.md` |

## Size

About 5–7 hours, within the spec's estimate.
