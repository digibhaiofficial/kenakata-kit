# License system (Free / Pro)

## What Pro unlocks
Store dashboard, courier booking + courier check, incomplete orders (`kk_pro_modules()` in kenakata-kit.php). Without
a valid key these files are not loaded at all. Everything else is Free. Development hosts (`localhost`, `*.local`,
`*.test`) are treated as Pro.

## The key
`KK1.<payload>.<signature>` — payload = base64url JSON `{id, d: domains (sub-domains included), p: plan label,
e: updates-until date or '' for lifetime, i: issued}`, signature = Ed25519 over the payload, checked in
`includes/license.php` with `sodium_crypto_sign_verify_detached` against `KK_LICENSE_PUBKEY`.
- A key works only on its domains (the settings screen refuses a key for another domain).
- After `e` the shop keeps Pro but gets no more update downloads.
- Once a day each shop reads `revoked.json` from this repo; a listed key id loses Pro within a day.

## The key panel (owner's tool)
- Address: https://digibhaiofficial.github.io/kenakata-kit/ (from `license-panel/index.html`, published by `panel.yml`).
  The page holds no secret; it can also be opened offline by double-clicking the file.
- The secret signing key ("সিলমোহর") lives only in the owner's browser, locked with his password; the backup is
  `kk-vault.json` in the **private** repo `digibhaiofficial/kenakata-license-panel`, still locked. A paper recovery
  code resets a forgotten password without changing the key.
- Panel: Dashboard (income, active keys, expiring soon + WhatsApp reminder), New key (+ send on WhatsApp),
  Customers (find, resend, edit domain, renew, cancel), Backup & settings (plans, prices, message texts).
- Customer ledger: download from the panel → replace `customers.json` in the private repo.
- Cancel a key: panel → Cancel → copy the shown `revoked.json` → replace `revoked.json` in this repo → push.
Full step-by-step for the owner (Bengali): `README.md` of the private repo.

## Never
- Never make a new signing key in the panel (all sold keys would stop working).
- Never change `KK_LICENSE_PUBKEY` unless every key is re-issued.
- Never make `kenakata-license-panel` public; never put the vault password or recovery code online.
