# Release and automatic updates

## The chain
```
change in the code ──▶ commit on main ──▶ tag vX.Y.Z pushed to GitHub
   ──▶ GitHub Actions release.yml: PHP 7.4 syntax, JS syntax, tag = version, minify, build zip,
       sign zip (secret KK_RELEASE_SIGNING_KEY), release notes from readme.txt, publish release, mark highest "Latest"
   ──▶ every shop: updater.php asks GitHub (every 6 h; at once with Kenakata Kit → License → "Check for updates now" or WordPress Dashboard → Updates → Check again),
       downloads the zip, checks the .sig against KK_RELEASE_PUBKEY, WordPress installs it
```
Pushing `main` without a tag changes nothing on the shops. A zip with a wrong or missing signature is never installed
(after a shop has once installed a signed version).

## Making a release (developer / AI agent)
1. Bump the version in **both** places in `kenakata-kit/kenakata-kit.php` (`* Version:` and `KK_VERSION`). Never reuse a number.
2. Add `= X.Y.Z =` with the changes at the top of the changelog in `kenakata-kit/readme.txt` (becomes the release notes).
3. Run every test (DEVELOPMENT.md). All must pass.
4. `git commit`, then `git tag vX.Y.Z`.
5. Get it to GitHub:
   - with push access: `git push origin main --tags`;
   - from a cloud chat without access: `git bundle create kenakata-kit.bundle main --tags` → put it in
     `D:\kenakata-kit\inbox\` → the owner double-clicks `D:\kenakata-kit\PUSH-TO-GITHUB.bat`.
6. Check https://github.com/digibhaiofficial/kenakata-kit/actions (release takes ~30 s) and the Releases page:
   the zip and `.zip.sig` must be attached.
7. On a test shop: Dashboard → Updates → Check again → update → check the changed feature.

## Rolling back
Never delete a release or reuse a version. To go back, take the old code (`git checkout vOLD -- kenakata-kit`),
give it a new higher version number and release it.

## Secrets used by the chain
| Secret | Where | Note |
|---|---|---|
| Release signing key (Ed25519 private key) | GitHub → repo Settings → Secrets → Actions → `KK_RELEASE_SIGNING_KEY`; backup copy in the owner's `secrets\` folder | must match `KK_RELEASE_PUBKEY` in `includes/license.php`. Keep the backup safe: shops that installed a signed version accept only updates signed with this key |
| GitHub login | the owner's Windows credential manager / GitHub Desktop, or the Claude GitHub App | never in a file |

## When it fails
| Symptom | Cause | Fix |
|---|---|---|
| Action "Tag matches the plugin version" red | tag ≠ `KK_VERSION` or header ≠ constant | fix the version, commit, push a new tag (new number) |
| Warning "this release is NOT signed" | repo secret missing | add `KK_RELEASE_SIGNING_KEY` (whole .pem) and release a new version |
| "Signature does not match KK_RELEASE_PUBKEY" | wrong key pasted as the secret | paste the right .pem; new version |
| Shops do not see the update | no tag pushed, release not "Latest", or 6-hour cache | check Releases; Dashboard → Updates → Check again |
| `PUSH-TO-GITHUB.bat` says the package does not fit | the folder and the package changed the same lines | send the screenshot to the developer/AI; it re-makes the package |
| Push refused (403 / login) | GitHub login expired | open GitHub Desktop, sign in again, run the bat again |
