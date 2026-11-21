$base = "http://127.0.0.1:8080"
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Req($url, $method, $body) {
    try {
        if ($method -eq 'Post') { return Invoke-WebRequest $url -Method Post -Body $body -WebSession $sess -UseBasicParsing -MaximumRedirection 0 }
        return Invoke-WebRequest $url -WebSession $sess -UseBasicParsing -MaximumRedirection 0
    } catch {
        $resp = $_.Exception.Response
        if ($resp) { $r = New-Object IO.StreamReader($resp.GetResponseStream()); return [PSCustomObject]@{ StatusCode=[int]$resp.StatusCode; Content=$r.ReadToEnd(); Headers=$resp.Headers } }
        throw
    }
}

$loginHtml = (Invoke-WebRequest "$base/index.php?r=auth/login" -WebSession $sess -UseBasicParsing).Content
$csrf = ([regex]::Match($loginHtml, 'name="csrf" value="([^"]+)"')).Groups[1].Value
[void] (Invoke-WebRequest "$base/index.php?r=auth/login" -Method Post -Body @{ r="auth/login"; csrf=$csrf; username="owner"; password="owner1234" } -WebSession $sess -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue)

# 1) GET /capital/invest and pull cashId + csrf
$inv = Req "$base/index.php?r=capital/invest" 'Get' $null
$cashId = ([regex]::Match($inv.Content, '<select[^>]*name="account_id"[^>]*>.*?<option[^>]*value="(\d+)"', 'Singleline')).Groups[1].Value
$csrf2 = ([regex]::Match($inv.Content, 'name="csrf" value="([^"]+)"')).Groups[1].Value
Write-Output "cashId=$cashId; csrf2 ok: $($csrf2.Length -gt 0)"

# 2) POST Rs 1 investment
$investResult = Req "$base/index.php?r=capital/invest" 'Post' @{ r="capital/invest"; csrf=$csrf2; account_id=$cashId; date="2026-11-14"; amount="1"; notes="automated render check" }
Write-Output "invest post: $($investResult.StatusCode) -> $($investResult.Headers['Location'])"

# 3) verify it appears on /capital
$cap2 = Req "$base/index.php?r=capital" 'Get' $null
Write-Output "capital shows note: $($cap2.Content -match 'automated render check'); shows 1.00: $($cap2.Content -match '>1\.00<')"

# cleanup
php "C:\Users\Haseeb\Desktop\chishti\tests\owner_flow_cleanup.php"
