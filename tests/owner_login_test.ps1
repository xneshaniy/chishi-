$base = "http://127.0.0.1:8080"
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$resp = $null
try {
    $resp = Invoke-WebRequest "$base/index.php?r=auth/login" -Method Post -Body @{ r="auth/login"; csrf=([regex]::Match((Invoke-WebRequest "$base/index.php?r=auth/login" -WebSession $sess -UseBasicParsing).Content, 'name="csrf" value="([^"]+)"')).Groups[1].Value; username="owner"; password="owner1234" } -WebSession $sess -UseBasicParsing -MaximumRedirection 0
} catch {
    $resp = $_.Exception.Response
    Write-Output "caught: $($_.Exception.Message)"
    if ($resp) { Write-Output "status: $([int]$resp.StatusCode)" }
}
if ($resp) { Write-Output "status2: $(([int]$resp.StatusCode))" }
$dash = Invoke-WebRequest "$base/index.php?r=dashboard" -WebSession $sess -UseBasicParsing
Write-Output "dashboard status: $($dash.StatusCode)"
Write-Output "dashboard has 4 tiles: $([regex]::Matches($dash.Content, 'tile-lbl').Count)"
Write-Output "first 300: "
Write-Output $dash.Content.Substring(0, [Math]::Min(300, $dash.Content.Length))
