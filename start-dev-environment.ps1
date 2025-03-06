# Set default UID/GID for Windows
$env:DOCKER_UID = "1000"
$env:DOCKER_GID = "1000"

# Function to test if a command exists
function Test-Command($CommandName) {
    return $null -ne (Get-Command $CommandName -ErrorAction SilentlyContinue)
}

# Change to the docker directory
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location "$scriptPath\docker"

# Start the development environment
if (Test-Command docker-compose) {
    docker-compose up -d
}
elseif ((docker compose version) -match '^\D') {
    docker compose up -d
}
else {
    Write-Error "Neither docker-compose nor docker compose is available"
    exit 1
}