# Playground QA environment

A throwaway WordPress multisite for manual QA, run with [WordPress Playground](https://developer.wordpress.org/playground/). No Docker needed.

## Start

```sh
npm install          # once; Node >= 20.18
npm run playground   # PHP 8.3, latest WordPress
```

Wait for `Ready! WordPress is running on http://127.0.0.1:80`, then open <http://localhost/siteb/>. Stop it with `Ctrl+C`.

Choose versions by passing CLI flags after `--`:

```sh
npm run playground -- --php=8.1
npm run playground -- --php=8.2 --wp=6.8
```

**Port 80 is required.** WordPress multisite doesn't allow a port in the site URL, so the script serves `http://localhost` on port 80. macOS allows this without `sudo`. On Linux, ports below 1024 need extra permission, e.g. `sudo sysctl net.ipv4.ip_unprivileged_port_start=80`. If port 80 is already in use, stop whatever is using it first.

## What you get

Every start builds a **fresh** site from `blueprint.json` and `setup.php`. Nothing persists between runs.

| Site | URL | Menus |
| --- | --- | --- |
| Main (1) | <http://localhost/> | `MAIN-SITE` on `primary` |
| Site A (2) | <http://localhost/sitea/> | `SHARED-FROM-A` on `primary` |
| Site B (3) | <http://localhost/siteb/> | `LOCAL-B` on `primary` and `footer`, plus a Navigation Menu widget |

- All sites use Twenty Twenty-One.
- The plugin is the **working tree**, mounted live, so edits show on the next page load. It's activated on **Site B only** and starts unconfigured: Appearance → Shared Menu Settings.
- WordPress automatic updates are off.

## Logging in

The script passes Playground's `--login` flag, which is meant to log your browser in as admin automatically (not verified in a browser during spec 005, only via `curl`). If you need to log in yourself, use the **main site's** login page, <http://localhost/wp-login.php> (`admin` / `password`). The cookie works for every site.

Subsite login URLs such as `/siteb/wp-login.php` return 404 in Playground. That's a Playground routing limit, not a plugin bug.

## Debug log

`WP_DEBUG` is on and display is off. PHP errors go to **`tests/playground/logs/debug.log`** on your machine:

```sh
tail -f tests/playground/logs/debug.log
```

The file is emptied on every start. Expect one `wp_get_sites() is deprecated` line per settings-page load until known issue #3 is fixed; anything else from plugin files is a problem.

## QA probe

`mu-plugins/mfsqa-probe.php` is loaded on every site:

- After each rendered menu, the page source has `<!--mfsqa blog=N switched=0|1-->`. On Site B this should always be `blog=3 switched=0`, because the plugin must not leave the page switched to another site.
- <http://localhost/siteb/?mfsqa_stack=1> simulates another plugin wrapping a menu render in `switch_to_blog( 1 )`. Expect `after_menu_blog=1 switched=1`, which means the outer switch survived, then `after_own_restore_blog=3 switched=0`.

## Useful admin pages

- Plugin settings on Site B: <http://localhost/siteb/wp-admin/themes.php?page=manage-mfs-options.php>
- Old single-string location format: set `mfs_override_menu_location` to `primary` on <http://localhost/siteb/wp-admin/options.php>.
- Delete Site A (to test a deleted source site): <http://localhost/wp-admin/network/sites.php>

## Known limits

- Playground uses SQLite, not MySQL.
- The first start downloads WordPress and Twenty Twenty-One, so it needs network access.
- Errors raised before mu-plugins or during the blueprint's own setup may stay in Playground's in-memory log.
- `@wp-playground/cli` has `npm audit` findings in its own dependencies (see `specs/005-playground-qa-env/qa-plan.md`). It's dev-only and runs locally; don't expose the server beyond your machine.

None of these files ship with the plugin.
