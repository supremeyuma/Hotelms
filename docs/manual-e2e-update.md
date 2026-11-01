# Manually proving auto-update

Auto-update needs two published versions: an installed one, and a newer one to
move to. This is the owner-run walkthrough (it changes what is installed on the
machine, so it is not part of the automated gate).

## 1. Install the base version

```powershell
gh release download v0.1.1 --repo supremeyuma/Hotelms --pattern "*setup.exe" --dir $env:TEMP
Start-Process "$env:TEMP\MooreLife_0.1.1_x64-setup.exe" -ArgumentList /S -Wait
```

Start **MooreLife**, complete the server setup (or connect as a terminal), and
confirm it works. The app version is shown in the installer filename and can be
checked in the WebView console with `window.__TAURI__` tooling; the running
build is `%LOCALAPPDATA%\MooreLife\desktop.exe`.

## 2. Publish a newer version

```powershell
./scripts/release.ps1 -Version 0.1.2 -NotesFile .\notes\0.1.2.md
```

Wait for the release job/upload to finish, then confirm the manifest:

```powershell
gh release view v0.1.2 --repo supremeyuma/Hotelms --json assets,tagName
# and that the "latest" alias resolves to it:
gh api repos/supremeyuma/Hotelms/releases/latest --jq '.tag_name'
```

## 3. Trigger the update in the installed app

1. Close and reopen the installed app (the check runs at start-up).
2. A banner appears: **Update available — version 0.1.2**.
3. Click **Install update**. The app stops its embedded server (Postgres and
   FrankenPHP are children of the desktop process and must release their files),
   downloads the installer, verifies its signature and runs it in passive mode.
4. The app relaunches. The banner is gone.
5. Confirm the version: the installed exe's file version / the installer name,
   and that the server starts and serves again (`http://127.0.0.1:8000/up`).

## 4. Negative checks

- **Tampered installer**: replace the release asset with a different file but
  keep the `.sig` from the real one. The app must refuse with a signature error
  and leave the installed build untouched.
- **Missing/invalid manifest**: `latest.json` removed or malformed — the app
  starts normally and only logs `update check failed` in the WebView console.
- **Older manifest**: publishing an older version does not downgrade installed
  copies (the updater only moves forward).
- **Server stops before install**: with the server running, click Install — the
  installer must succeed (if it fails with a locked file, `setup_stop_server`
  did not release the children).

## Troubleshooting

| Symptom | Cause |
|---|---|
| No banner | The installed version is not older than `releases/latest`; `latest.json` is attached to a prerelease (the alias skips it); or the machine cannot reach GitHub. |
| "signature verification failed" | `latest.json` signature does not match the installer, or the build used a different signing key than the embedded pubkey. |
| Installer runs but files are unchanged | Update installed to a different directory (per-user NSIS) than the running copy. |
| Banner appears during the automated e2e run | The suite sets `MOORELIFE_DISABLE_UPDATES=1`; if you see it, that env var was lost. |
