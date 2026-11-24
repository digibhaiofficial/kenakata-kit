# Troubleshooting

## On a shop
| Problem | First check | Fix |
|---|---|---|
| Site broken right after an update | WordPress → Plugins | Deactivate Kenakata Kit (the shop runs normally without it), send the error/screenshot to the developer; a fixed version follows |
| "could not be reactivated" after updating | a WordPress 7.1 message, also with other plugins | Plugins → activate Kenakata Kit again |
| A feature does nothing | Kenakata Kit → Dashboard: is its switch on? Pro feature without a key? | switch it on / add the license |
| Checkout features missing | shop uses WooCommerce's block checkout | Checkout tab offers the one-click switch to the classic checkout |
| Old page after changing settings | page cache (LiteSpeed etc.) | purge the cache |
| Pro turned off | key expired for updates only (Pro stays), wrong domain, or key cancelled in revoked.json | License tab shows the reason |
| No update shown | License → "Check for updates now" (or Dashboard → Updates → Check again); GitHub Releases has the version? | see RELEASE-AND-UPDATES.md |
| Courier booking fails | Kenakata Kit → courier log | check API keys of that courier; "where do I find this?" links under each field |
| Server slow / many add-to-cart requests | Speed & health checks; bot counts | turn on Cart-bot guard and the bot switches |
| Orders from fake numbers | Fake-order protection tab | exact 11 digits, repeat-order block, Turnstile, blocked numbers |

PHP errors: WordPress `wp-content/debug.log` (with `WP_DEBUG_LOG`), or the host's error log. The smoke test checks
that the plugin writes no warnings or notices.

## In development
| Problem | Fix |
|---|---|
| `node dev/lint.js` cannot find php-parser | `cd dev && npm install` (or it falls back to `php -l`) |
| CI "minified files do not match" | `sh dev/minify.sh` and commit |
| Mock server says `MISSING STRINGS` / `NO BENGALI FOR` | add the key to `kk_sd_strings()` / the Bengali to `dev/i18n` |
| e2e has 1–4 timing failures | run it again; real failures repeat |
