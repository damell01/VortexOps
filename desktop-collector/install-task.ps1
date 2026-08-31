$ErrorActionPreference = 'Stop'

$taskName = 'VortexOps Whatnot Collector'
$runner = Join-Path $PSScriptRoot 'Scheduled Sync.bat'

if (-not (Test-Path $runner)) {
    throw "Scheduled runner not found: $runner"
}

$action = New-ScheduledTaskAction `
    -Execute $env:ComSpec `
    -Argument "/d /c `"`"$runner`"`"" `
    -WorkingDirectory $PSScriptRoot

$trigger = New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Hours 1)

$principal = New-ScheduledTaskPrincipal `
    -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) `
    -LogonType Interactive `
    -RunLevel Limited

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew

$task = New-ScheduledTask `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings

Register-ScheduledTask -TaskName $taskName -InputObject $task -Force | Out-Null
Write-Host "Installed '$taskName'. It will run every hour while this Windows user is logged in."
Write-Host "Log: $(Join-Path $PSScriptRoot 'logs\scheduled-sync.log')"
