# 002 — Threat model (STRIDE, scoped to this change)

## What changes

The unconfigured path through `pre_wp_nav_menu` → `wp_nav_menu_items` becomes a no-op: no short-circuit, no `switch_to_blog()`, no `restore_current_blog()`. A source site ID of `0` is treated as unconfigured. The restore is now driven by a `$switched` flag set when the plugin itself switches, so it never restores a switch it didn't make, on configured or unconfigured sites. See `approach.md`.

Assets: the request's current-blog context (the `$GLOBALS['_wp_switched_stack']` / `$blog_id` state), and the site's own navigation output.

## Relevant threats

| STRIDE | Threat | Before | After this change |
| --- | --- | --- | --- |
| **Tampering** | The plugin's `restore_current_blog()` pops a blog switch it didn't make, such as one another plugin or theme made around a menu render. The rest of the page then runs in the wrong site's context. | Not reachable on unconfigured sites, because the menu was short-circuited before `wp_nav_menu_items`. | Reachable once menus render. **Mitigated** by the `$switched` flag (approach §3, AC5). |
| **Information disclosure** | Following from the above, content after the menu (posts, options, user data) is read from the wrong site and shown on this site's pages. | Not reachable on unconfigured sites. | Same mitigation as Tampering. |
| **Elevation of privilege** | Following from the above, capability checks later in the request run against the wrong site's roles. | Not reachable on unconfigured sites. | Same mitigation as Tampering. |
| **Tampering** (rejected design) | Guarding the restore by re-reading settings reads the *source* site's options after the switch, skips the restore, and leaves the page on the source site. | n/a | **Avoided.** Caught by the blog-aware harness before review; replaced with the flag. |
| **Denial of service** (availability) | All classic menus on the site render empty. | **Present.** This is the bug. | Fixed (AC1, AC2). |

## Not affected

- **Spoofing and Repudiation:** no change to authentication, logging or who can save settings.
- **Settings input:** saving is unchanged and still behind `manage_options` with the Settings API nonce. Sanitization and escaping gaps on the options page are known issue #4, which is out of scope.

## Residual risk (out of scope, known issue #2)

- **Restore without switch on configured sites:** now **removed** by the flag. This is a side effect of the approach Ben accepted at Gate 2.
- **`fallback_cb` leak remains:** a switched menu that falls back never reaches `wp_nav_menu_items`, so the page stays on the source site until the next menu render, which restores it via the stale flag. That's the same tampering and disclosure class as above. It isn't worsened by this change and is tracked for the known-issue-#2 spec.

## How it's verified

- Blog-aware CLI harness: configured and unconfigured scenarios end on the home site with an empty switch stack, and another plugin's outer switch survives.
- AC5: in the QA environment, an mu-plugin switches to another site, renders a menu on an unconfigured site, and confirms afterwards that `get_current_blog_id()` and `ms_is_switched()` are unchanged. The test mu-plugin's own switch must survive.
- AC6: `debug.log` is clean in every scenario.
