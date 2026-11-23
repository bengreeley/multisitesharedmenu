# 002 — QA plan

No lint or test tooling exists yet (no Composer, PHPCS, PHPUnit or wp-env). Verification so far is a throwaway CLI stub harness plus manual QA in a Docker multisite. Neither is committed.

## CLI harness (2026-11-20, blog-aware)

The harness loads `inc/class-menufromsite.php` with core functions stubbed:

- Options are stored **per site**.
- `switch_to_blog()` / `restore_current_blog()` maintain a real stack.
- Like core, `wp_nav_menu_items` only runs when `pre_wp_nav_menu` returned `null`.
- `get_blog_details( 0 )` falls back to the current site.
- Site B (3) is home, site A (2) is the source, and site 1 is the main site.

The first harness version used one shared set of options. It passed the "narrow guard" design, which re-read settings in `check_restore_blog_menu()`. Self-review of the diff found that after the switch, those settings come from the source site. The blog-aware version confirmed the regression, and the design changed to the `$switched` flag (approach §3).

| Check | `HEAD` (`f73ae27`) | Narrow guard (rejected) | Flag, PHP 8.3.26 + 8.4.13 |
| --- | --- | --- | --- |
| AC1: nothing saved → menu renders | FAIL (`false`) | PASS | PASS |
| AC2: site saved, no locations → renders | FAIL (`false`) | PASS | PASS |
| AC2: site ID empty → renders | FAIL (`false`) | PASS | PASS |
| AC2/AC5: site ID `0` → no switch/restore | FAIL (1/1) | PASS | PASS |
| AC5: unconfigured cases → no switch/restore, end on B | PASS only via short-circuit | PASS | PASS |
| Pass-through of another filter's `pre_wp_nav_menu` output | FAIL | PASS | PASS |
| AC5: another plugin's outer switch (B → 1) survives a menu render | PASS | PASS | PASS |
| AC4: configured, selected → rendered on A, back on B with empty stack | PASS | **FAIL (left on A)** | PASS |
| AC3: configured, unselected → no switch, no restore | FAIL (unmatched restore) | **FAIL (left on A)** | PASS |
| AC3/4: several menus in a row end on B | PASS | **FAIL** | PASS |
| AC4: legacy single-string location switches and restores | PASS | **FAIL** | PASS |
| `php -l inc/class-menufromsite.php` | — | — | PASS on 8.3.26 and 8.4.13. **8.1 not run** (local `php@8.1` keg is 8.4) |

## Manual QA in WordPress (2026-11-20)

### Environment

- `wordpress:php8.3-apache` (PHP 8.3), WordPress 7.1.5, MariaDB 11.4, subdirectory multisite, Twenty Twenty-One on every site
- `WP_DEBUG_LOG` on, and `debug.log` cleared before every scenario
- Main site (1): `Main Menu` (`MAIN-SITE`)
- Site A (2): `A Menu` (`SHARED-FROM-A`) on `primary`
- Site B (3): `B Menu` (`LOCAL-B`) on `primary` **and** `footer`, plus a Navigation Menu widget showing `B Menu` in `sidebar-1`. With all three, a normal page has `LOCAL-B` ×3.
- QA-only mu-plugin `mfsqa-probe.php` (not committed):
  - Appends `<!--mfsqa blog=N switched=0|1-->` after each rendered menu.
  - `?mfsqa_stack=1` switches to blog 1, renders a menu, and reports whether its own switch survived.
- The plugin is mounted read-only from the working tree. The old code is `git archive HEAD`, installed as `mfs-old`.

### Results

| AC | Scenario on site B | `LOCAL-B` | `SHARED-FROM-A` | Context after each menu | `debug.log` | Result |
| --- | --- | --- | --- | --- | --- | --- |
| — | Baseline: plugin inactive | 3 | 0 | `blog=3 switched=0` ×3 | empty | reference |
| — | **Old code** (`HEAD`), unconfigured | **0** | 0 | no menus rendered | empty | bug reproduced |
| AC1 | New code, activated, nothing saved | 3 | 0 | `blog=3 switched=0` ×3 | empty (incl. activation) | **PASS**, matches baseline |
| AC2 | Source = A, no locations | 3 | 0 | `blog=3 switched=0` ×3 | empty | **PASS** |
| AC2 | Source ID `0`, `primary` ticked | 3 | 0 | `blog=3 switched=0` ×3 | empty | **PASS** |
| AC3 + AC4 | Source = A, `primary` ticked | 2 (footer + widget) | 1 (primary) | `blog=3 switched=0` ×3 | empty | **PASS** |
| AC4 | Legacy single-string location `primary` | 2 | 1 | `blog=3 switched=0` ×3 | empty | **PASS** |
| AC5 | Unconfigured B, `?mfsqa_stack=1` | — | — | `after_menu_blog=1 switched=1`, then `after_own_restore_blog=3 switched=0` | empty | **PASS**, the outer switch survived |
| AC5 | Configured B, `?mfsqa_stack=1` (rendered while switched to blog 1) | — | — | same as above | empty | **PASS** |
| AC6 | All scenarios above | | | | all empty | **PASS** |

### Not covered

- `php -l` under PHP 8.1: the local `php@8.1` keg is 8.4.
- The `fallback_cb` leak on configured sites: out of scope (known issue #2).
- The rejected narrow-guard design was only shown failing in the CLI harness, not in WordPress.
