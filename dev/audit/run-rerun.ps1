# Teillauf der Matrix, etwa nach einer gezielten Aenderung.
#   powershell -NoProfile -ExecutionPolicy Bypass -File dev\audit\run-rerun.ps1 -Names php74-wp64,php83-wplatest,multisite
# Ohne Angabe laufen die sieben Umgebungen ohne Fremd-Plugins und ohne Multisite.
param(
  [string[]]$Names = @('php74-wp64','php83-wplatest','php84-wplatest','php85-wplatest','tt1','tt5','de')
)

# Ueber powershell -File kommt -Names a,b als ein einziger String an.
$Names = @($Names | ForEach-Object { $_ -split ',' } | ForEach-Object { $_.Trim() } | Where-Object { $_ })

$ErrorActionPreference = 'Continue'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root
$log = 'dev\audit\matrix.log'
"RERUN $(Get-Date -Format s): $($Names -join ', ')" | Out-File $log -Append -Encoding utf8
foreach ($name in $Names) {
  Remove-Item "dev\audit\result-$name.txt", "dev\audit\debug-$name.log", "dev\audit\requests-$name.log" -ErrorAction SilentlyContinue
  $t0 = Get-Date
  $extra = @()
  # WordPress-Multisite vertraegt keinen Port in der Adresse.
  if ($name -eq 'multisite') { $extra = @('--site-url','http://multisite.test') }
  $out = & npx --yes '@wp-playground/cli@latest' run-blueprint "--blueprint=dev/audit/bp-$name.json" --mount-dir $root '/wordpress/build' --verbosity=quiet @extra 2>&1 | Where-Object { $_ -notmatch 'lockWholeFile' }
  $code = $LASTEXITCODE
  $envLine = 'KEIN RESULT'
  if (Test-Path "dev\audit\result-$name.txt") { $envLine = (Get-Content "dev\audit\result-$name.txt" -Encoding UTF8 | Select-String 'ENV ') -join ' ' }
  "=== $name : exit=$code dauer=$(((Get-Date) - $t0).ToString('mm\:ss')) | $envLine" | Out-File $log -Append -Encoding utf8
  (($out | Select-Object -Last 4) -join "`n") | Out-File $log -Append -Encoding utf8
}
"END2 $(Get-Date -Format s)" | Out-File $log -Append -Encoding utf8
