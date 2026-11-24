# Kenakata Kit documentation

Kenakata Kit is a stand-alone WordPress + WooCommerce plugin for Bangladeshi online shops, made and sold by
DigiBhai.com. These documents are written so that a new developer or an AI agent can take over without help.

| Document | For whom | What it answers |
|---|---|---|
| [STATUS.md](STATUS.md) | everyone, read first | Where the project stands, where everything lives, open items, roadmap, owner decisions |
| [FEATURES.md](FEATURES.md) | owner, sales, support, developers | Every feature: what it does for the shop, Free or Pro, where to switch it on, which file holds it |
| [SETTINGS-REFERENCE.md](SETTINGS-REFERENCE.md) | developers, support | Every settings tab and field, shortcodes, Elementor widgets (generated from the code) |
| [ARCHITECTURE.md](ARCHITECTURE.md) | developers | How the plugin loads, stores data, talks to the browser, translates, updates itself |
| [DEVELOPMENT.md](DEVELOPMENT.md) | developers, AI agents | Setting up, rules, how to add or fix something, tests, checklist before a release |
| [RELEASE-AND-UPDATES.md](RELEASE-AND-UPDATES.md) | developers, owner | From a change to the update on every shop: tags, signing, GitHub Actions, what to do when it fails |
| [LICENSE-SYSTEM.md](LICENSE-SYSTEM.md) | owner, developers | Free/Pro, license keys, the key panel, cancelling keys, what must never be changed |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | owner, support, developers | Common problems on a shop and in the release chain, and the fix |
| [OWNER-GUIDE-bn.md](OWNER-GUIDE-bn.md) | owner (বাংলা) | The PC folder, a new computer, working with an AI, the one-click push |
| [../CLAUDE.md](../CLAUDE.md) | AI agents, developers | The binding code rules and the exact test and release commands |

The shop owner's own user guide (Bengali, shown inside WordPress) is `kenakata-kit/docs/index.html`.
The per-version changelog is `kenakata-kit/readme.txt`.

To regenerate the settings reference after changing settings: `php dev/docs/build-reference.php > docs/SETTINGS-REFERENCE.md`.
