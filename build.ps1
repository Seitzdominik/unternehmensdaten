<#
.SYNOPSIS
    Baut das installierbare Plugin-Archiv.

.DESCRIPTION
    Compress-Archive aus Windows PowerShell 5.1 schreibt Backslashes als
    Pfadtrenner in das Archiv. Die ZIP-Spezifikation verlangt Forward-Slashes,
    weshalb PHP solche Eintraege als einen einzigen flachen Dateinamen liest.
    WordPress findet dann keine Plugin-Datei und meldet, die Plugin-Dateien
    existierten nicht.

    ZipFile.CreateFromDirectory aus dem .NET Framework hat dasselbe Problem: es
    uebernimmt den Pfadtrenner des Betriebssystems. Dieses Skript legt die
    Eintraege deshalb einzeln an und bestimmt ihre Namen selbst. Anschliessend
    wird das Ergebnis nachgeprueft.
#>

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

$root   = Split-Path -Parent $MyInvocation.MyCommand.Path
$slug   = 'unternehmensdaten'
$source = Join-Path $root $slug
$target = Join-Path $root "$slug.zip"

if (-not (Test-Path $source)) {
    throw "Quellordner nicht gefunden: $source"
}

# Version aus dem Plugin-Header lesen, damit das Archiv sie im Namen tragen kann.
$header  = Get-Content (Join-Path $source "$slug.php") -TotalCount 20 -Encoding UTF8
$version = ($header | Select-String -Pattern '^\s*\*\s*Version:\s*(.+)$').Matches.Groups[1].Value.Trim()

if (Test-Path $target) {
    Remove-Item $target -Force
}

# Was nicht ins ausgelieferte Archiv gehoert.
$skipFiles = @('*.zip', '.DS_Store', 'Thumbs.db', '*.log', '*.bak', '.gitignore', '.gitattributes', 'update.json')

# Ordner, die zur Entwicklung gehoeren und im Archiv nichts verloren haben.
# Der Vergleich laeuft ueber den relativen Pfad, nicht ueber den Dateinamen.
$skipDirs = @('.git', '.github', 'dev', 'build', 'node_modules')

$prefixLength = $source.Length + 1

$files = Get-ChildItem -Path $source -Recurse -File -Force | Where-Object {
    $relative = $_.FullName.Substring($prefixLength) -replace '\\', '/'
    $hit      = $false

    foreach ($pattern in $skipFiles) {
        if ($_.Name -like $pattern) { $hit = $true }
    }

    foreach ($dir in $skipDirs) {
        if ($relative -like "$dir/*") { $hit = $true }
    }

    -not $hit
}

$archive = [System.IO.Compression.ZipFile]::Open($target, 'Create')

try {
    foreach ($file in $files) {
        # Pfad relativ zum Projektordner, damit der Basisordner mitkommt, und
        # der Trenner ausdruecklich als Forward-Slash.
        $relative = $file.FullName.Substring($root.Length + 1).Replace('\', '/')

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $file.FullName,
            $relative,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $archive.Dispose()
}

# --- Nachpruefung -----------------------------------------------------------

$zip     = [System.IO.Compression.ZipFile]::OpenRead($target)
$entries = $zip.Entries | ForEach-Object { $_.FullName }
$zip.Dispose()

$bad = $entries | Where-Object { $_ -like '*\*' }

if ($bad.Count -gt 0) {
    Remove-Item $target -Force
    throw "Archiv enthaelt $($bad.Count) Eintraege mit Backslash und waere nicht installierbar."
}

$main = "$slug/$slug.php"

if ($entries -notcontains $main) {
    Remove-Item $target -Force
    throw "Hauptdatei $main fehlt im Archiv."
}

$size = [math]::Round((Get-Item $target).Length / 1KB, 1)

"OK  $slug $version"
"    $($entries.Count) Eintraege, $size KB"
"    $target"
