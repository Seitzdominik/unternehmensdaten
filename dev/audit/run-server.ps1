$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root
& npx --yes '@wp-playground/cli@latest' server --blueprint=dev/dev-server.json --mount-dir "$root\unternehmensdaten" '/wordpress/wp-content/plugins/unternehmensdaten' --mount-dir $root '/wordpress/build' --port 9500 --site-url http://127.0.0.5:9500 --login *> 'dev\audit\server.log'
