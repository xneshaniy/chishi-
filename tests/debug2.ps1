$base = "http://127.0.0.1:8080"
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginHtml = (Invoke-WebRequest "$base/index.php?r=auth/login" -WebSession $sess -UseBasicParsing).Content
$csrf = ([regex]::Match($loginHtml, 'name="csrf" value="([^"]+)"')).Groups[1].Value
[void] (Invoke-WebRequest "$base/index.php?r=auth/login" -Method Post -Body @{ r="auth/login"; csrf=$csrf; username="owner"; password="owner1234" } -WebSession $sess -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue)
$cap = Invoke-WebRequest "$base/index.php?r=capital" -WebSession $sess -UseBasicParsing
Write-Output "capital status: $($cap.StatusCode), length: $($cap.Content.Length)"
Write-Output $cap.Content.Substring(0, [Math]::Min(800, $cap.Content.Length))
Write-Output "===="
Write-Output $cap.Content.Substring([Math]::Max(0, $cap.Content.Length - 600))
