<#
  OPTIONAL. The "website" folder is already ready to copy and paste to your host.
  Use this script only if you want to (a) switch the site to a different domain
  (rewrites sitemap.xml and robots.txt) and/or (b) turn on the HTTPS redirect, and
  get a zip of the result.

    .\build-for-hosting.ps1 -Domain https://www.yourdomain.org
    .\build-for-hosting.ps1 -Domain https://www.yourdomain.org -ForceHttps

  Output (the "website" folder itself is never modified):
    dist\                      upload-ready copy with your settings applied
    coc-website-upload.zip     the same files zipped (files at the zip root)
#>
param(
  [string]$Domain = 'https://www.camoncenter.org',
  [switch]$ForceHttps
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$site = Join-Path $root 'website'
$dist = Join-Path $root 'dist'
$Domain = $Domain.TrimEnd('/')
$utf8 = New-Object System.Text.UTF8Encoding($false)

if (Test-Path $dist) { Remove-Item $dist -Recurse -Force }
New-Item -ItemType Directory -Path $dist | Out-Null
Get-ChildItem $site -Force | Copy-Item -Destination $dist -Recurse

# Never ship local test data: a stale local database/secret would overwrite the live ones.
Get-ChildItem (Join-Path $dist 'data') -Force | Where-Object { $_.Name -notin @('.htaccess', 'index.html') } | Remove-Item -Recurse -Force

$pages = Get-ChildItem $dist -File -Filter *.html | Where-Object { $_.Name -notin @('404.html', 'review.html') } | Sort-Object Name
$urls = foreach ($p in $pages) {
  $loc = if ($p.Name -eq 'index.html') { "$Domain/" } else { "$Domain/$($p.Name)" }
  "  <url><loc>$loc</loc></url>"
}
$sitemap = @('<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">') + $urls + '</urlset>'
[System.IO.File]::WriteAllLines((Join-Path $dist 'sitemap.xml'), $sitemap, $utf8)

$robotsPath = Join-Path $dist 'robots.txt'
$robots = [System.IO.File]::ReadAllText($robotsPath) -replace '(?m)^Sitemap:.*$', "Sitemap: $Domain/sitemap.xml"
[System.IO.File]::WriteAllText($robotsPath, $robots, $utf8)

if ($ForceHttps) {
  $htPath = Join-Path $dist '.htaccess'
  $ht = [System.IO.File]::ReadAllText($htPath) -replace '(?m)^#(?=<IfModule mod_rewrite\.c>|  Rewrite|</IfModule>)', ''
  [System.IO.File]::WriteAllText($htPath, $ht, $utf8)
}

Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
$zipPath = Join-Path $root 'coc-website-upload.zip'
if (Test-Path $zipPath) { Remove-Item $zipPath }
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
try {
  Get-ChildItem $dist -Recurse -File -Force | ForEach-Object {
    $rel = $_.FullName.Substring($dist.Length + 1).Replace('\', '/')
    [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $rel, 'Optimal')
  }
} finally { $zip.Dispose() }

$files = Get-ChildItem $dist -Recurse -File -Force
$mb = [math]::Round(($files | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Host "Built $($files.Count) files ($mb MB) for $Domain$(if ($ForceHttps) { ' with HTTPS redirect' })"
Write-Host "  Folder: $dist"
Write-Host "  Zip:    $zipPath"
