param(
    [switch]$SkipTests,
    [switch]$DryRun,
    [string]$WslDistro = ""
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$DistributionSlug = "zion-eu-withdrawal"
$DeploymentDirectory = "zion-eu-withdrawal"
$SourcePluginFile = "zion-eu-withdrawal.php"
$SynologyHost = "192.168.0.10"
$SynologyPort = 2022
$SynologyUser = "wordpress-deploy"
$WordPressPluginsPath = "/volume1/www/macho.raduta.synology.me/wp-content/plugins"
$RemotePluginPath = "$WordPressPluginsPath/$DeploymentDirectory"
$RemoteBackupRoot = "$WordPressPluginsPath/.deploy-backups/$DeploymentDirectory"
$Timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$RemoteArchivePath = "/tmp/$DistributionSlug-$Timestamp.tar.gz"
$RemoteStagePath = "/tmp/$DeploymentDirectory-stage-$Timestamp"
$RemoteOldPath = "$WordPressPluginsPath/.$DeploymentDirectory-old-$Timestamp"
$SshPrivateKey = Join-Path $env:USERPROFILE ".ssh\wordpress-plugin-deploy"

function Invoke-WslScript([string]$Script) {
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($Script)
    $encoded = [Convert]::ToBase64String($bytes)
    & wsl.exe -d $script:WslDistro -- bash -lc "echo '$encoded' | base64 -d | bash"
    if ($LASTEXITCODE -ne 0) { throw "Comanda WSL a eșuat cu codul $LASTEXITCODE." }
}

function Invoke-RemoteScript([string]$Script) {
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($Script)
    $encoded = [Convert]::ToBase64String($bytes)
    & ssh.exe -i $script:SshPrivateKey -p $script:SynologyPort -o "IdentitiesOnly=yes" -o "PreferredAuthentications=publickey" "$script:SynologyUser@$script:SynologyHost" "echo '$encoded' | base64 -d | sh"
    if ($LASTEXITCODE -ne 0) { throw "Deployment-ul remote a eșuat cu codul $LASTEXITCODE." }
}

foreach ($required in @("wsl.exe", "ssh.exe", "scp.exe")) {
    if (-not (Get-Command $required -ErrorAction SilentlyContinue)) { throw "Comanda '$required' nu este disponibilă." }
}

$projectPathWindows = $PSScriptRoot
$uncPattern = '^\\\\(?:wsl\.localhost|wsl\$)\\([^\\]+)\\(.+)$'
if ($projectPathWindows -match $uncPattern) {
    if ([string]::IsNullOrWhiteSpace($WslDistro)) { $WslDistro = $Matches[1] }
    $linuxProjectPath = "/" + ($Matches[2] -replace '\\', '/')
} else {
    if ([string]::IsNullOrWhiteSpace($WslDistro)) { $WslDistro = "Ubuntu-22.04" }
    $linuxProjectPath = (& wsl.exe -d $WslDistro -- wslpath -u "$projectPathWindows").Trim()
}

if ([string]::IsNullOrWhiteSpace($WslDistro) -or [string]::IsNullOrWhiteSpace($linuxProjectPath)) { throw "Nu am putut determina mediul WSL." }
$linuxArchivePath = "$linuxProjectPath/.dist/$DistributionSlug.tar.gz"
$windowsArchivePath = Join-Path $projectPathWindows ".dist\$DistributionSlug.tar.gz"
$bashProjectPath = "'" + ($linuxProjectPath -replace "'", "'\''") + "'"

Write-Host "Pachet: $DistributionSlug | WSL: $WslDistro | Destinație: $RemotePluginPath" -ForegroundColor Green

$validate = @"
set -eu
cd $bashProjectPath
test -f '$SourcePluginFile'
command -v php >/dev/null
command -v composer >/dev/null
command -v tar >/dev/null
"@
if (-not $SkipTests) {
    $validate += @"

COMPOSER_ALLOW_SUPERUSER=1 composer check-syntax --no-interaction
COMPOSER_ALLOW_SUPERUSER=1 composer test:unit --no-interaction
"@
}
$validate += @"

bash bin/build-release.sh
mkdir -p .dist
rm -f '$linuxArchivePath'
tar -czf '$linuxArchivePath' -C build '$DistributionSlug'
test -s '$linuxArchivePath'
"@
Invoke-WslScript $validate

if (-not (Test-Path -LiteralPath $windowsArchivePath)) { throw "Arhiva nu a fost creată: $windowsArchivePath" }
if ($DryRun) { Write-Host "DryRun finalizat: build-ul și verificările au trecut." -ForegroundColor Yellow; exit 0 }

& scp.exe -O -i $SshPrivateKey -P $SynologyPort -o "IdentitiesOnly=yes" -o "PreferredAuthentications=publickey" $windowsArchivePath "$SynologyUser@$SynologyHost`:$RemoteArchivePath"
if ($LASTEXITCODE -ne 0) { throw "Transferul arhivei a eșuat cu codul $LASTEXITCODE." }

$remote = @"
set -eu
archive='$RemoteArchivePath'
plugin_root='$WordPressPluginsPath'
live='$RemotePluginPath'
stage='$RemoteStagePath'
old='$RemoteOldPath'
backup_root='$RemoteBackupRoot'
mkdir -p "\$plugin_root" "\$backup_root"
if [ -d "\$live" ]; then tar -czf "\$backup_root/$Timestamp.tar.gz" -C "\$plugin_root" '$DeploymentDirectory'; fi
rm -rf "\$stage"
mkdir -p "\$stage"
tar -xzf "\$archive" -C "\$stage"
test -f "\$stage/$DeploymentDirectory/$SourcePluginFile"
if [ -d "\$live" ]; then mv "\$live" "\$old"; fi
mv "\$stage/$DeploymentDirectory" "\$live"
rm -rf "\$old" "\$stage" "\$archive"
echo 'Deployment Synology finalizat.'
"@
Invoke-RemoteScript $remote
Write-Host "Deploy Synology finalizat cu backup în $RemoteBackupRoot." -ForegroundColor Green
