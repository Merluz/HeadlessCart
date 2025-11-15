# -----------------------------------------
# HeadlessCart – PowerShell build script (FIXED)
# -----------------------------------------

$Plugin = "headlesscart"
$Version = "1.0.0"

$Root = Split-Path -Parent $PSCommandPath     # Folder containing this script
$Project = Split-Path -Parent $Root           # Root of repo (..)
$BuildRoot = Join-Path $Project "build"
$BuildDir  = Join-Path $BuildRoot $Plugin

Write-Host "Building $Plugin v$Version..." -ForegroundColor Cyan

# --- Clean previous build
if (Test-Path $BuildRoot) {
    Remove-Item $BuildRoot -Recurse -Force
}

New-Item -ItemType Directory -Path $BuildDir | Out-Null

# --- Exclusions
$exclude = @(
    ".git",
    ".github",
    "node_modules",
    "tests",
    "vendor\bin",
    "*.md",
    "composer.*",
    "tools"
)

# --- Copy with exclusions
function Copy-With-Exclude {
    param (
        [string]$Source,
        [string]$Destination,
        [array]$ExcludeList
    )

    $sourceFull = (Get-Item $Source).FullName
    $destFull   = (Get-Item $Destination).FullName

    Get-ChildItem -Path $sourceFull -Recurse -Force | ForEach-Object {
        $relative = $_.FullName.Substring($sourceFull.Length).TrimStart("\","/")

        # Skip excluded patterns
        foreach ($ex in $ExcludeList) {
            if ($relative -like $ex -or $_.Name -like $ex) {
                return
            }
        }

        $target = Join-Path $destFull $relative

        if ($_.PSIsContainer) {
            if (!(Test-Path $target)) {
                New-Item -ItemType Directory -Path $target | Out-Null
            }
        } else {
            $parent = Split-Path $target -Parent
            if (!(Test-Path $parent)) {
                New-Item -ItemType Directory -Path $parent | Out-Null
            }
            Copy-Item $_.FullName $target -Force
        }
    }
}

Copy-With-Exclude -Source $Project -Destination $BuildDir -ExcludeList $exclude

# --- Create ZIP
$zipPath = Join-Path $BuildRoot "$Plugin-$Version.zip"

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($BuildDir, $zipPath)

Write-Host "Build completed → $zipPath" -ForegroundColor Green
