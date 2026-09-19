$ErrorActionPreference = 'Continue'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root
$log = 'dev\audit\matrix.log'
"START $(Get-Date -Format s)" | Out-File $log -Encoding utf8
Remove-Item dev\audit\result-*.txt, dev\audit\debug-*.log, dev\audit\requests-*.log, dev\audit\ms-activate.txt -ErrorAction SilentlyContinue
foreach ($name in @('php74-wp64','php83-wplatest','php84-wplatest','php85-wplatest','tt1','tt5','de','yoast','rankmath','autoptimize','multisite')) {
  $t0 = Get-Date
  $extra = @()
  if ($name -eq 'multisite') { $extra = @('--site-url','http://multisite.test') }
  $out = & npx --yes '@wp-playground/cli@latest' run-blueprint "--blueprint=dev/audit/bp-$name.json" --mount-dir $root '/wordpress/build' --verbosity=quiet @extra 2>&1 | Where-Object { $_ -notmatch 'lockWholeFile' }
  $code = $LASTEXITCODE
  $envLine = 'KEIN RESULT'
  if (Test-Path "dev\audit\result-$name.txt") { $envLine = (Get-Content "dev\audit\result-$name.txt" -Encoding UTF8 | Select-String 'ENV ') -join ' ' }
  "=== $name : exit=$code dauer=$(((Get-Date) - $t0).ToString('mm\:ss')) | $envLine" | Out-File $log -Append -Encoding utf8
  (($out | Select-Object -Last 4) -join "`n") | Out-File $log -Append -Encoding utf8
}
"END $(Get-Date -Format s)" | Out-File $log -Append -Encoding utf8
