# 003 — WordPress Coding Standards compliance

## Problem

The plugin's PHP doesn't follow the WordPress Coding Standards (WPCS), and the repo has no way to check it. CLAUDE.md requires WPCS and lists `composer lint` / `composer lint:fix`, but both are still TBD. As a result, every spec so far has shipped with "lint doesn't exist yet".

Baseline (PHPCS 3.13, WPCS 3, `WordPress` ruleset, the 6 plugin PHP files, commit `a5ebfe0`): **303 violations from 58 sniffs.** PHPCBF can fix 210 of them automatically.

| Group | Approx. count | Examples |
| --- | --- | --- |
| Whitespace and formatting (auto-fixable) | ~210 | trailing whitespace (45), space indentation (17), control-structure and function-call spacing, concatenation spacing, missing EOF newlines |
| Naming | ~35 | camelCase locals such as `$mfsSettings`, `$blogTemp`, `$curLocation` (33); class `mfs_options_page` |
| Docblocks | ~40 | missing or wrong-style file, class, function and property comments |
| Best-practice code checks | ~12 | assignment in condition (`class-menufromsite.php` `menuswitch_check()`), loose `==`/`!=` (4), non-Yoda conditions (2), `in_array()` without strict, unused `$args`, redundant `return;`, `new` without parentheses |
| Owned by other specs (excluded here) | 9 | `WordPress.Security.EscapeOutput` ×8 in `views/options-page.php` (known issue #4); `WordPress.WP.DeprecatedFunctions` `wp_get_sites` ×1 (known issue #3) |
| File naming (excluded here) | 4 | `WordPress.Files.FileName`: class files aren't named `class-{classname}.php` |

## Behaviour risk

Some "fixes" can change behaviour, so each one has to be done by hand rather than trusted to PHPCBF:

- **Strict comparisons.** `views/options-page.php` compares `$blogTemp['blog_id']`, which `wp_get_sites()` returns as a string, with `get_current_blog_id()`, which returns an int. Blindly changing `!=` to `!==` would list the current site in its own Source Site dropdown. Same risk for the selected-site check against `get_option()`.
- **Yoda and strict `in_array()`.** Stored locations may be the legacy single string or an array with numeric keys. Strictness must not change which boxes appear ticked.
- **Assignment in condition.** `menuswitch_check()` assigns `$mfsSettings` inside the `if`. Splitting it must keep the spec 002 pass-through and `$switched` flag behaviour exactly.

## Acceptance criteria

- [ ] AC1: `composer install` then `composer lint` runs PHPCS with a committed `phpcs.xml.dist` using the `WordPress` ruleset plus `PHPCompatibilityWP` (testVersion `8.1-`), over the plugin PHP files only (no `vendor/`, `specs/`).
- [ ] AC2: `composer lint` reports **0 errors and 0 warnings**.
- [ ] AC3: The only exclusions in `phpcs.xml.dist` are:
  - `WordPress.Security.EscapeOutput` (owner: known issue #4 spec)
  - `WordPress.WP.DeprecatedFunctions.wp_get_sitesFound` (owner: known issue #3 spec)
  - `WordPress.Files.FileName` (kept file names, by decision)

  Each has a comment naming why and, for the first two, which spec removes it. No inline `phpcs:ignore` comments.
- [ ] AC4: `composer lint:fix` runs PHPCBF with the same config.
- [ ] AC5: No behaviour change.
  - Option names (`mfs_override_site_id`, `mfs_override_menu_location`), stored formats (including the legacy single-string location), hook names, priorities and the settings-group name are unchanged.
  - The spec 002 QA scenarios all still pass: unconfigured, no locations, ID `0`, configured selected and unselected, legacy string, and the switch-stack probe.
  - The settings page lists the same sites (never the current site), preselects the saved site, and ticks the saved locations, both before and after saving.
- [ ] AC6: The class `mfs_options_page` is renamed to a WPCS-valid, prefixed name, and the global activation/deactivation functions are renamed to prefixed names (amended at Gate 2). Nothing else in the plugin refers to the old names. File names are unchanged.
- [ ] AC7: The new dev dependencies are dev-only. No runtime dependency is added, and nothing from `vendor/` is required by the plugin at runtime. `vendor/` is git-ignored.
- [ ] AC8: CLAUDE.md's Commands section no longer marks `composer lint` / `composer lint:fix` as TBD.

## Out of scope

- Escaping and sanitizing the options page, and its `EscapeOutput` violations (known issue #4).
- Replacing `wp_get_sites()` (known issue #3).
- i18n and text domains, readme/version housekeeping (known issue #5). WPCS only checks existing `__()` calls, and there are none.
- Renaming class files to `class-{classname}.php`.
- PHPUnit, wp-env, CI.
- Any functional change, including known issue #2 (`fallback_cb` leak).

## Security flag: no

There's no change to escaping, sanitization, capabilities or nonces, and the escaping sniffs are explicitly deferred to #4. The blog-switching code in `inc/class-menufromsite.php` and the settings view are reformatted, and their comparisons made strict. Those are the behaviour risks above, covered by AC5's regression QA, not by a change in security posture.

## Size

About 5–7 hours: tooling ~1h, PHPCBF plus review ~1h, manual naming, docs and strictness fixes ~2–3h, regression QA ~1–2h. Under the 8-hour limit. If it runs over, split the docblocks (WordPress-Docs) into a follow-up spec.
