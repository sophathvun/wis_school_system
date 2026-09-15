param(
    [ValidateRange(1024, 65535)][int]$Port = 8002,
    [string]$PhpPath,
    [string]$HostAddress = '0.0.0.0',
    [switch]$Check
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent

try {
    foreach ($required in @('.env', 'vendor/autoload.php', 'public/build/manifest.json')) {
        if (-not (Test-Path (Join-Path $projectRoot $required))) {
            throw "Missing $required. Complete the project setup before starting the site."
        }
    }

    $candidates = @()
    if ($PhpPath) {
        $candidates = @($PhpPath)
    } else {
        $onPath = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($onPath) { $candidates += $onPath.Source }
        $laragonRoot = Split-Path (Split-Path $projectRoot -Parent) -Parent
        foreach ($root in @($laragonRoot, 'C:\laragon') | Select-Object -Unique) {
            $candidates += Get-ChildItem "$root\bin\php\*\php.exe" -ErrorAction SilentlyContinue |
                Select-Object -ExpandProperty FullName
        }
    }

    $compatible = @(foreach ($candidate in $candidates | Select-Object -Unique) {
        if (-not (Test-Path -LiteralPath $candidate)) { continue }
        $versionText = & $candidate -r 'echo PHP_VERSION;' 2>$null
        if ($LASTEXITCODE -eq 0 -and "$versionText" -match '^(\d+\.\d+\.\d+)') {
            $version = [version]$Matches[1]
            if ($version -ge [version]'8.4.1') {
                [PSCustomObject]@{ Path = $candidate; Version = $version }
            }
        }
    })
    $php = $compatible | Sort-Object Version -Descending | Select-Object -First 1
    if (-not $php) { throw 'PHP 8.4.1 or newer is required. Install it in Laragon or pass -PhpPath C:\path\to\php.exe.' }

    Push-Location $projectRoot
    try {
        & $php.Path -r "require 'vendor/autoload.php';"
        if ($LASTEXITCODE -ne 0) { throw 'The installed PHP does not satisfy the Composer dependencies.' }
        $extensions = & $php.Path -m
        if ($extensions -notcontains 'pdo_mysql') { throw 'Enable pdo_mysql in the selected PHP configuration.' }
        Write-Host "Using PHP $($php.Version): $($php.Path)"
        if ($Check) { Write-Host 'Startup checks passed.'; exit 0 }

        $bindAddress = [System.Net.IPAddress]::Parse($HostAddress)
        $listener = [System.Net.Sockets.TcpListener]::new($bindAddress, $Port)
        try { $listener.Start() } catch { throw "Port $Port is already in use. Try http://127.0.0.1:$Port/ or run with -Port 8003." } finally { $listener.Stop() }

        Write-Host "Site: http://127.0.0.1:$Port/"
        if ($HostAddress -eq '0.0.0.0') {
            $lanAddresses = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
                Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
                Select-Object -ExpandProperty IPAddress)
            foreach ($address in $lanAddresses) {
                Write-Host "LAN:  http://$address`:$Port/"
            }
        }
        Write-Host 'Keep this window open. Press Ctrl+C to stop. MySQL must be running in Laragon.'
        Set-Location (Join-Path $projectRoot 'public')
        $router = Join-Path $projectRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
        & $php.Path -S "$HostAddress`:$Port" $router
        if ($LASTEXITCODE -ne 0) { throw "PHP server exited with code $LASTEXITCODE." }
    } finally { Pop-Location }
} catch {
    Write-Host "Cannot start the site: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
