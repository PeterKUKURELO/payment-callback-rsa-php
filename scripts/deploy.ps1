param(
    [string]$Stage = "preprod",
    [string]$Region = $env:AWS_REGION
)

if ([string]::IsNullOrWhiteSpace($Region)) {
    $Region = "us-east-1"
}

$composer = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composer) {
    Write-Error "composer no esta instalado o no esta en PATH."
    exit 1
}

$npx = Get-Command npx -ErrorAction SilentlyContinue
if (-not $npx) {
    Write-Error "npx (Node.js) no esta instalado o no esta en PATH."
    exit 1
}

if (-not (Test-Path ".env")) {
    Write-Warning ".env no existe. Copia .env.example a .env antes del deploy."
}

Write-Host "Instalando dependencias PHP para deploy..." -ForegroundColor Cyan
composer install --no-dev -o
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Desplegando con Serverless (stage=$Stage, region=$Region)..." -ForegroundColor Cyan
npx serverless deploy --stage $Stage --region $Region
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Deploy completado." -ForegroundColor Green
