$base = "http://127.0.0.1:8080"
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Get-Page($url, $method, $body) {
    try {
        if ($method -eq 'Post') { return Invoke-WebRequest -Uri $url -Method Post -Body $body -WebSession $sess -UseBasicParsing -MaximumRedirection 0 }
        return Invoke-WebRequest -Uri $url -WebSession $sess -UseBasicParsing -MaximumRedirection 0
    } catch {
        $resp = $_.Exception.Response
        if ($resp) {
            $reader = New-Object IO.StreamReader($resp.GetResponseStream())
            return [PSCustomObject]@{ StatusCode = [int]$resp.StatusCode; Content = $reader.ReadToEnd(); Headers = @{} }
        }
        throw
    }
}

# 1) login page + csrf
$loginHtml = (Get-Page "$base/index.php?r=auth/login" 'Get' $null).Content
$csrf = ([regex]::Match($loginHtml, 'name="csrf" value="([^"]+)"')).Groups[1].Value
Write-Output "csrf: $($csrf.Substring(0,8))..."

# 2) owner login
$login = Get-Page "$base/index.php?r=auth/login" 'Post' @{ r="auth/login"; csrf=$csrf; username="owner"; password="owner1234" }
Write-Output "login: $($login.StatusCode) redirect: $($login.Headers['Location'])"

# 3) GET /staff/new as owner
$form = Get-Page "$base/index.php?r=staff%2Fnew" 'Get' $null
Write-Output "staff/new: $($form.StatusCode); employee form present: $($form.Content -match 'Employee Name')"

# 4) POST create employee
$m2 = [regex]::Match($form.Content, 'name="csrf" value="([^"]+)"')
$csrf2 = $m2.Groups[1].Value
$created = Get-Page "$base/index.php?r=staff%2Fnew" 'Post' @{ r="staff/new"; csrf=$csrf2; name="Browser Test Employee"; cnic="42101-9998887-6"; phone="0300-7777777"; address="Test Address Ahmedpur"; designation="Test Technician"; department="Maintenance"; join_date="2026-11-01" }
Write-Output "create: $($created.StatusCode) redirect: $($created.Headers['Location'])"

# 5) follow redirect
if ($created.Headers['Location']) {
    $prof = Get-Page $created.Headers['Location'] 'Get' $null
    Write-Output "profile: $($prof.StatusCode); employee listed: $($prof.Content -match 'Browser Test Employee'); code present: $($prof.Content -match 'EMP-')"
}
