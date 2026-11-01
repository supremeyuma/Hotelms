param(
    [Parameter(Mandatory = $true)][string]$Version,
    [string]$NotesFile = "",
    [switch]$SkipPush,
    [switch]$Prerelease
)

# Build, sign and publish a MooreLife release.
#
# Signs the updater artifacts with the minisign key in
# %USERPROFILE%\.tauri\moorelife.key, writes latest.json (the manifest
# tauri-plugin-updater polls), and publishes installer + signature + manifest to
# GitHub so installed copies can update themselves.
#
# The private key is never committed. In CI the same values arrive as
# TAURI_SIGNING_PRIVATE_KEY / TAURI_SIGNING_PRIVATE_KEY_PASSWORD secrets.

$ErrorActionPreference = "Stop"

$Repo = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$KeyPath = Join-Path $env:USERPROFILE ".tauri\moorelife.key"
$KeyPasswordPath = Join-Path $env:USERPROFILE ".tauri\moorelife.key.password"
$AppDir = Join-Path $Repo "desktop"
$ConfPath = Join-Path $AppDir "src-tauri\tauri.conf.json"
$BundleDir = Join-Path $AppDir "src-tauri\target\release\bundle\nsis"
$AssetName = "MooreLife_${Version}_x64-setup.exe"
$AssetPath = Join-Path $BundleDir $AssetName
$SignaturePath = "$AssetPath.sig"
$LatestJson = Join-Path $BundleDir "latest.json"
$Tag = "v$Version"

if ($Version -notmatch '^\d+\.\d+\.\d+$') {
    throw "Version must look like 1.2.3 (got '$Version')"
}

if (-not $env:TAURI_SIGNING_PRIVATE_KEY) {
    if (-not (Test-Path $KeyPath)) { throw "No updater signing key at $KeyPath" }
    $env:TAURI_SIGNING_PRIVATE_KEY = $KeyPath
}
if (-not $env:TAURI_SIGNING_PRIVATE_KEY_PASSWORD -and (Test-Path $KeyPasswordPath)) {
    $env:TAURI_SIGNING_PRIVATE_KEY_PASSWORD = (Get-Content $KeyPasswordPath -Raw).Trim()
}

# --- Version bump -------------------------------------------------------------
$conf = Get-Content $ConfPath -Raw | ConvertFrom-Json
$conf.version = $Version
$conf | ConvertTo-Json -Depth 20 | Set-Content $ConfPath -Encoding utf8

$desktopPackagePath = Join-Path $AppDir "package.json"
$desktopPackage = Get-Content $desktopPackagePath -Raw | ConvertFrom-Json
$desktopPackage.version = $Version
$desktopPackage | ConvertTo-Json -Depth 20 | Set-Content $desktopPackagePath -Encoding utf8

$cargoPath = Join-Path $AppDir "src-tauri\Cargo.toml"
$cargo = Get-Content $cargoPath -Raw
$cargo = $cargo -replace '(?m)^version = "[0-9]+\.[0-9]+\.[0-9]+"', "version = `"$Version`""
Set-Content $cargoPath $cargo -NoNewline

Write-Host "Version set to $Version (tauri.conf.json, package.json, Cargo.toml)"

# --- Build + sign -------------------------------------------------------------
Push-Location $AppDir
try {
    npx tauri build --bundles nsis
} finally {
    Pop-Location
}

if (-not (Test-Path $SignaturePath)) {
    throw "Expected an updater signature at $SignaturePath; is createUpdaterArtifacts enabled?"
}

# --- Updater manifest ---------------------------------------------------------
$signature = (Get-Content $SignaturePath -Raw).Trim()
$notes = if ($NotesFile -and (Test-Path $NotesFile)) { Get-Content $NotesFile -Raw } else { "MooreLife $Version" }

$manifest = [ordered]@{
    version   = $Version
    notes     = $notes
    pub_date  = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
    platforms = [ordered]@{
        "windows-x86_64" = [ordered]@{
            signature = $signature
            url       = "https://github.com/supremeyuma/Hotelms/releases/download/$Tag/$AssetName"
        }
    }
}
$manifest | ConvertTo-Json -Depth 10 | Set-Content $LatestJson -Encoding utf8
Write-Host "Wrote $LatestJson"

# --- Git + GitHub -------------------------------------------------------------
Push-Location $Repo
try {
    git add $ConfPath $desktopPackagePath $cargoPath
    git commit -m "chore: release $Version" | Out-Host
    git tag $Tag
    if (-not $SkipPush) {
        git push origin main
        git push origin $Tag
    }
} finally {
    Pop-Location
}

$ghArgs = @(
    "release", "create", $Tag,
    $AssetPath,
    $SignaturePath,
    $LatestJson,
    "--repo", "supremeyuma/Hotelms",
    "--title", "MooreLife $Version",
    "--notes-file", $NotesFile
)
if (-not (Test-Path $NotesFile)) {
    $ghArgs = $ghArgs | Where-Object { $_ -ne "--notes-file" -and $_ -ne $NotesFile }
    $ghArgs += @("--notes", "MooreLife $Version")
}
if ($Prerelease) { $ghArgs += "--prerelease" }

& gh @ghArgs
if ($LASTEXITCODE -ne 0) { throw "gh release create failed" }

Write-Host "Published $Tag"
Write-Host "Updater manifest: https://github.com/supremeyuma/Hotelms/releases/latest/download/latest.json"
