param([string]$PhpPath = 'php', [string]$Filter = '*Test.php')

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Push-Location $projectRoot
try {
    & $PhpPath scripts/test-suite.php "--filter=$Filter"
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
