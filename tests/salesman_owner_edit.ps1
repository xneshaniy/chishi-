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

# owner login
$loginHtml = (Get-Page "$base/index.php?r=auth/login" 'Get' $null).Content
$csrf = ([regex]::Match($loginHtml, 'name="csrf" value="([^"]+)"')).Groups[1].Value
$login = Get-Page "$base/index.php?r=auth/login" 'Post' @{ r="auth/login"; csrf=$csrf; username="owner"; password="owner1234" }
Write-Output "owner login: $($login.StatusCode)"

# load edit form
$edit = Get-Page "$base/index.php?r=salesmen%2F2%2Fedit" 'Get' $null
Write-Output "edit form: $($edit.StatusCode)"
$csrf2 = ([regex]::Match($edit.Content, 'name="csrf" value="([^"]+)"')).Groups[1].Value

# find Bahawalpur city id from DB — use PowerShell to query the dev sqlite
$py = @"
import sqlite3
conn = sqlite3.connect(r'C:\Users\Haseeb\Desktop\chishti\storage\dev.sqlite')
row = conn.execute(\"SELECT id FROM pk_provinces WHERE name='Punjab'\").fetchone()
pid = row[0]
row = conn.execute(\"SELECT id FROM pk_cities WHERE province_id=? AND name='Bahawalpur'\", (pid,)).fetchone()
print(f\"{pid} {row[0]}\")
"@
$loc = python -c $py
$parts = $loc -split ' '
$punjabId = $parts[0]; $bahawalId = $parts[1]
Write-Output "province=$punjabId city=$bahawalId"

# POST the edit
$editBody = @{
    r = "salesmen/2/edit"
    id = "2"
    csrf = $csrf2
    code = "DEMO-SM-001"
    name = "DEMO Salesman One"
    phone = "0302-7778899"
    cnic = ""
    province_id = $punjabId
    city_id = $bahawalId
    area = "Farid Gate"
    commission_percent = "2.5"
    salary = "30000"
    join_date = ""
    address = "Main Bahawalpur Road"
    is_active = "1"
}
$saved = Get-Page "$base/index.php?r=salesmen%2F2%2Fedit" 'Post' $editBody
Write-Output "save: $($saved.StatusCode) -> $($saved.Headers['Location'])"
