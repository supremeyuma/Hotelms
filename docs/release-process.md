# Release process

MooreLife is distributed as a Windows NSIS installer with **signed updater
artifacts**. Installed copies poll a signed manifest and can update themselves.

## Signing keys

| Purpose | Key | Where it lives |
|---|---|---|
| Updater artifact signature (minisign) | `moorelife.key` + public key in `tauri.conf.json` → `plugins.updater.pubkey` | Private key: `%USERPROFILE%\.tauri\moorelife.key`, password in `moorelife.key.password`. **Never commit them.** |
| Windows Authenticode | Not configured | The installer is unsigned for SmartScreen; that is separate from updater signing and needs a code-signing certificate/HSM. |

The public key is embedded in every build, so an update is only installed if its
signature matches. Losing the private key or password means no future updates
can be shipped to existing installations.

CI secrets (Settings → Secrets and variables → Actions):

- `TAURI_SIGNING_PRIVATE_KEY` — contents of `moorelife.key`
- `TAURI_SIGNING_PRIVATE_KEY_PASSWORD` — its password

## Local release

```powershell
# from the repository root; signing key and password are read from ~/.tauri
./scripts/release.ps1 -Version 0.1.1 -NotesFile .\notes\0.1.1.md
```

The script:

1. bumps `tauri.conf.json`, `desktop/package.json` and `src-tauri/Cargo.toml`;
2. runs `tauri build --bundles nsis` (which stages the app and bundles runtime,
   Postgres and the Laravel tree);
3. signs the installer with the minisign key, producing
   `MooreLife_<version>_x64-setup.exe.sig`;
4. writes `latest.json` (version, notes, pub_date, platform signature + URL);
5. commits `chore: release <version>`, tags `v<version>` and pushes;
6. creates the GitHub release with installer, `.sig` and `latest.json`.

Installed copies read:

```
https://github.com/supremeyuma/Hotelms/releases/latest/download/latest.json
```

`latest.json` must stay attached to the **latest** (non-prerelease) release;
that is what `releases/latest` resolves to.

## CI release

`.github/workflows/release.yml` runs on a `v*` tag (or manual dispatch) and does
the same build with `tauri-apps/tauri-action`, which uploads the installer, its
signature and `latest.json`.

The bundled PHP/FrankenPHP runtime and the trimmed Postgres are **not in git**
(too large). CI downloads them from the `runtime-v1` prerelease asset
(`desktop-runtime-windows-x64.zip`, SHA-256
`E07E1D8926C67CD3F36A831FC22E196378BFB3877886F4A28E8A863AB1B66779`). Rebuild
that bundle only when the vendored runtime versions change:

```powershell
Compress-Archive -Path resources\bin, resources\postgres-runtime `
  -DestinationPath desktop-runtime-windows-x64.zip -CompressionLevel Optimal
gh release upload runtime-v1 desktop-runtime-windows-x64.zip --clobber
```

## Pre-release checklist

1. `npx playwright test` — web workflows (68).
2. `php artisan test` — 467 tests including the upgrade test.
3. `cargo test --lib` in `desktop/src-tauri` — 39 tests.
4. Build the installer with `--features e2e`, install it, and run
   `E2E_APP_BINARY=%LOCALAPPDATA%\MooreLife\desktop.exe npm run e2e` — the
   installed-artifact walkthrough (11 tests).
5. Build the real installer **without** the feature and confirm it opens no
   WebDriver port (4445) and contains no `tauri-plugin-wdio` symbols.
6. Publish, then perform the update walkthrough in
   `docs/manual-e2e-update.md`.

## Rollback

GitHub releases are immutable once published with a fixed tag; to roll back a
bad update, publish a **higher** version containing the fix (the updater only
moves forward). Users can always reinstall a specific version from its release
page; data migrations are forward-only, so keep a database backup before a
major-version update.
