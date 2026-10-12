<#
.SYNOPSIS
Stages a trimmed copy of the Laravel application into resources/app so the
desktop installer can bundle it (Task 7). Run before `tauri build`.

Structure is copied per top-level entry so exclusions are only needed in the
few places they apply:
  - top-level directories are excluded by name (desktop, docs, vendor? no,
    vendor is kept; see list), copied wholesale otherwise;
  - the Laravel `resources` subtree is copied with path-based /XD exclusions
    for the desktop runtime archives (bin/postgres/runtime/app);
  - top-level files are copied individually, skipping dev/build/secret files.
#>
param()

$ErrorActionPreference = "Stop"

$Repo = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Dest = Join-Path $Repo "resources\app"

if (Test-Path $Dest) {
    Write-Host "Clearing previous staging at $Dest"
    Remove-Item -LiteralPath $Dest -Recurse -Force
}
New-Item -ItemType Directory -Path $Dest | Out-Null

# Top-level entries never staged. Note: vendored desktop runtime lives under
# resources/, so it is handled inside the resources subtree copy below, and the
# staging destination itself (also under resources/) must stay excluded there.
$skipDirs = @(
    "desktop",
    "docs",
    "docker",
    "node_modules",
    "scripts",
    "tests",
    ".git",
    ".github",
    ".planning",
    ".opencode",
    ".continue",
    ".impeccable",
    ".vscode"
) | ForEach-Object { $_.ToLower() }

$skipFiles = @(
    ".env",
    ".env.example",
    ".env.testing",
    ".gitattributes",
    ".gitignore",
    ".dockerignore",
    ".editorconfig",
    ".phpunit.result.cache",
    "jsconfig.json",
    "package.json",
    "package-lock.json",
    "postcss.config.js",
    "tailwind.config.js",
    "vite.config.js",
    "phpunit.xml",
    "Dockerfile",
    "deploy.sh",
    "helpers.php"
) | ForEach-Object { $_.ToLower() }

function Invoke-Robocopy {
    param(
        [string]$Source,
        [string]$Target,
        [string[]]$ExcludeDirs,
        [string[]]$ExcludeFiles
    )
    $args = @($Source, $Target, "/E", "/MT:16", "/NFL", "/NDL", "/NJH", "/NJS")
    if ($ExcludeDirs.Count -gt 0) {
        $args += "/XD"
        $args += @($ExcludeDirs)
    }
    if ($ExcludeFiles.Count -gt 0) {
        $args += "/XF"
        $args += @($ExcludeFiles)
    }
    & robocopy @args | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy failed (exit $LASTEXITCODE): $Source -> $Target"
    }
}

$entries = Get-ChildItem -LiteralPath $Repo -Force
foreach ($entry in $entries) {
    $name = $entry.Name
    $isDir = $entry.PSIsContainer

    if ($isDir) {
        if ($skipDirs -contains $name.ToLower()) {
            Write-Host "skip dir  $name"
            continue
        }
        $target = Join-Path $Dest $name
        if ($name -eq "resources") {
            # Laravel's resources/ (views, css, js) minus the desktop runtime.
            Invoke-Robocopy `
                -Source $entry.FullName `
                -Target $target `
                -ExcludeDirs @(
                    (Join-Path $entry.FullName "app"),
                    (Join-Path $entry.FullName "bin"),
                    (Join-Path $entry.FullName "postgres"),
                    (Join-Path $entry.FullName "runtime")
                ) `
                -ExcludeFiles @("*.md")
        }
        else {
            Invoke-Robocopy -Source $entry.FullName -Target $target
        }
        Write-Host "copied dir  $name"
    }
    else {
        if ($skipFiles -contains $name.ToLower()) {
            Write-Host "skip file  $name"
            continue
        }
        if ($name -match "\.(md|txt)$") {
            Write-Host "skip file  $name"
            continue
        }
        Copy-Item -LiteralPath $entry.FullName -Destination (Join-Path $Dest $name)
        Write-Host "copied file $name"
    }
}

# Robocopy does not carry empty directory trees; storage/ is almost entirely
# empty skeletons that Laravel needs at runtime. Re-create them explicitly.
$storageDirs = @(
    "storage\app",
    "storage\app\public",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "storage\logs"
)
foreach ($dir in $storageDirs) {
    $path = Join-Path $Dest $dir
    if (-not (Test-Path $path)) {
        New-Item -ItemType Directory -Path $path -Force | Out-Null
    }
}

# Guard: the destination must never contain a nested copy of itself.
$nested = Join-Path $Dest "resources\app\resources\app"
if (Test-Path $nested) {
    throw "staging recursively copied itself; aborted"
}

$size = (Get-ChildItem -LiteralPath $Dest -Recurse -File | Measure-Object -Property Length -Sum).Sum
Write-Host "Staged app: $([math]::Round(($size / 1MB), 1)) MB at $Dest"