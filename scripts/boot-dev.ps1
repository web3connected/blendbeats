param(
    [int]$FrontendPort = 5173,
    [int]$BackendPort = 8000,
    [int]$StopAfterSeconds = 0
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$BackendRoot = Join-Path $Root "backend"
$FrontendUrl = "http://localhost:$FrontendPort"
$BackendUrl = "http://127.0.0.1:$BackendPort"

function Write-Info($Message) {
    Write-Host "[boot] $Message" -ForegroundColor Cyan
}

function Write-Warn($Message) {
    Write-Host "[boot] $Message" -ForegroundColor Yellow
}

function Assert-Command($Name, $InstallHint) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Missing required command '$Name'. $InstallHint"
    }
}

function Test-PortFree([int]$Port) {
    $Listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Port)
    try {
        $Listener.Start()
        return $true
    }
    catch {
        return $false
    }
    finally {
        $Listener.Stop()
    }
}

function Ensure-RootEnv {
    $EnvFile = Join-Path $Root ".env"
    $ExampleFile = Join-Path $Root "env.example"

    if (-not (Test-Path $EnvFile) -and (Test-Path $ExampleFile)) {
        Copy-Item $ExampleFile $EnvFile
        Write-Info "Created root .env from env.example"
    }
}

function Ensure-BackendEnv {
    $EnvFile = Join-Path $BackendRoot ".env"
    $ExampleFile = Join-Path $BackendRoot ".env.example"
    $DatabaseFile = Join-Path $BackendRoot "database\database.sqlite"

    if (-not (Test-Path $EnvFile) -and (Test-Path $ExampleFile)) {
        Copy-Item $ExampleFile $EnvFile
        Write-Info "Created backend .env from backend/.env.example"
    }

    if (-not (Test-Path $DatabaseFile)) {
        New-Item -ItemType File -Path $DatabaseFile | Out-Null
        Write-Info "Created backend SQLite database file"
    }

    $EnvText = Get-Content $EnvFile -Raw
    if ($EnvText -match "(?m)^APP_KEY=\s*$") {
        Write-Info "Generating Laravel app key"
        Push-Location $BackendRoot
        try {
            php artisan key:generate --ansi
        }
        finally {
            Pop-Location
        }
    }
}

function Assert-ProjectReady {
    Assert-Command "npm.cmd" "Install Node.js 22+ and npm, then rerun this script."
    Assert-Command "php" "Install PHP 8.3+ and make sure it is available in PATH."

    if (-not (Test-Path (Join-Path $Root "node_modules"))) {
        throw "Root node_modules is missing. Run 'npm install' from $Root."
    }

    if (-not (Test-Path (Join-Path $BackendRoot "vendor"))) {
        throw "Backend vendor is missing. Run 'composer install' from $BackendRoot."
    }

    if (-not (Test-PortFree $FrontendPort)) {
        throw "Frontend port $FrontendPort is already in use. Pass -FrontendPort <port> or stop the existing process."
    }

    if (-not (Test-PortFree $BackendPort)) {
        throw "Backend port $BackendPort is already in use. Pass -BackendPort <port> or stop the existing process."
    }
}

function Stop-PortProcess([int]$Port) {
    $Listeners = netstat -ano | Select-String "LISTENING" | Select-String ":$Port\s"
    foreach ($Listener in $Listeners) {
        $Parts = ($Listener.Line -replace "^\s+", "") -split "\s+"
        $ProcessId = [int]$Parts[-1]

        if ($ProcessId -gt 0) {
            Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
        }
    }
}

Write-Info "Preparing BlendBeats dev environment"
Ensure-RootEnv
Ensure-BackendEnv
Assert-ProjectReady

$Processes = @(
    @{
        Name = "backend"
        Process = Start-Process -FilePath "php" -ArgumentList @("artisan", "serve", "--host=127.0.0.1", "--port=$BackendPort") -WorkingDirectory $BackendRoot -PassThru -NoNewWindow
    },
    @{
        Name = "frontend"
        Process = Start-Process -FilePath "npm.cmd" -ArgumentList @("run", "dev", "--", "--host", "0.0.0.0", "--port", "$FrontendPort") -WorkingDirectory $Root -PassThru -NoNewWindow
    }
)

Write-Info "Backend:  $BackendUrl"
Write-Info "Frontend: $FrontendUrl"
Write-Info "Press Ctrl+C to stop both servers."

$StartedAt = Get-Date

try {
    while ($true) {
        foreach ($Entry in $Processes) {
            if ($Entry.Process.HasExited) {
                throw "$($Entry.Name) server exited with code $($Entry.Process.ExitCode)."
            }
        }

        if ($StopAfterSeconds -gt 0 -and ((Get-Date) - $StartedAt).TotalSeconds -ge $StopAfterSeconds) {
            Write-Info "StopAfterSeconds reached; shutting down."
            break
        }

        Start-Sleep -Milliseconds 300
    }
}
finally {
    Write-Warn "Stopping dev servers"
    foreach ($Entry in $Processes) {
        Stop-Process -Id $Entry.Process.Id -Force -ErrorAction SilentlyContinue
    }
    Stop-PortProcess $FrontendPort
    Stop-PortProcess $BackendPort
}
