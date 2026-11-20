# CLAUDE.md — Multisite Shared Menu

This repo uses spec-driven development: every change starts from an approved spec, you build from an approved approach, and Ben approves at each gate. You draft; Ben approves. Never treat your own review as an approval.

## Project

WordPress multisite plugin that lets a site render a nav menu from another site on the same network. Last released 2015 (v1.2); now being modernized.

- `multisitesharedmenu.php` — bootstrap, activation/deactivation hooks, only instantiates on multisite
- `inc/class-menufromsite.php` — core: `pre_wp_nav_menu` switches blogs, `wp_nav_menu_items` restores
- `admin/class-menufromsite-admin-options.php` — settings registration (Appearance → Shared Menu Settings)
- `views/options-page.php` — settings page markup
- Options: `mfs_override_site_id` (int), `mfs_override_menu_location` (array of theme locations)

Targets: PHP 8.1+, current WordPress, multisite only.

## Commands

<!-- Fill in as tooling is added. Do not invent commands that don't exist yet. -->
- Lint: `composer lint` (TBD — PHPCS with WPCS + PHPCompatibility)
- Fix lint: `composer lint:fix` (TBD)
- Tests: `composer test` (TBD — PHPUnit integration tests via wp-env, multisite enabled)
- Local env: `npx wp-env start` (TBD)

Run lint and tests before saying any change is done. If they don't exist yet, say so rather than skipping silently.

## Workflow

Each unit of work lives in `specs/NNN-short-slug/`. Work moves through these stages; stop at each gate and wait for Ben.

1. **Specify** → `spec.md`: problem, acceptance criteria, repro steps (bugs), out of scope, security flag (yes/no with reason).
   **Gate 1:** Ben approves the spec. No approach or code until then.
2. **Plan** → `approach.md`: technical approach plus dev notes (files you may touch, patterns to follow, what's off-limits). If the security flag is yes, add a `threat-model.md` section (STRIDE, only the parts this change affects).
   **Gate 2:** Ben approves the approach. For a trivial fix, put a short approach in `spec.md` and Ben checks it at PR review instead.
3. **Build:** implement only inside the dev notes. If you need to touch something outside them, stop and say why — don't expand scope on your own. Write or update tests first where practical.
4. **Review and verify** → `qa-plan.md`: how each acceptance criterion is checked (automated test or manual step in wp-env). Self-review the diff, run lint and tests, then open the PR using the template below.
5. **Release** (Gate 3): Ben approves. You draft the changelog entry and readme updates; versions in the plugin header, `readme.txt` Stable tag, and the class `$version` must match.

Work that fails review goes back to Build on the same spec; if the acceptance criteria were wrong, back to Specify.

Size rule: if a spec looks like more than ~8 hours of work, propose splitting it before planning.

## Skills

If skills are installed for a stage (spec writing, technical approach, dev notes, threat modeling, QA plans, PR review, release), use them. If not, follow the stage description above.

## PR template

```
## Spec
specs/NNN-slug/spec.md

## What changed
## How it was verified (link qa-plan.md)
## Security flag: yes/no
If yes: what's affected (escaping, sanitization, capabilities, nonces, blog switching) and how it was checked.
```

## Coding rules

- WordPress Coding Standards. Escape all output late (`esc_html`, `esc_attr`, `esc_url`); sanitize and allowlist all saved input; check capabilities on anything admin-facing.
- Every `switch_to_blog()` must have exactly one matching `restore_current_blog()` on every code path, including menu fallbacks.
- `pre_wp_nav_menu` must return `null` to let WordPress render normally.
- Use `get_sites()`, not the deprecated `wp_get_sites()`.
- Keep option names and stored data formats backward-compatible, including the old single-string `mfs_override_menu_location`.
- Prefix new functions, classes and hooks; add a text domain for any new strings.
- No new runtime dependencies. Dev dependencies via Composer/npm are fine.

## Never

- Commit or merge without lint and tests passing (or an explicit note that they don't exist yet).
- Approve your own work or mark a gate as passed.
- Change files outside the approved dev notes without asking.
- Rewrite history on `main`.

## Known issues (from initial review — each needs its own spec)

1. Unconfigured sites: `menuswitch_check` returns `false` from `pre_wp_nav_menu`, likely suppressing menus.
2. Unbalanced blog switching: restore runs on menus that never switched; `fallback_cb` paths can switch without restoring.
3. `wp_get_sites()` deprecated since WP 4.6.
4. Options page: unescaped output; `validate_pluginvalues()` doesn't sanitize.
5. Housekeeping: readme "Tested up to 4.1", stable tag/version mismatch, http links, no i18n, no tooling or tests.
