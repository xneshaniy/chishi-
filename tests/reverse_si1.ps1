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

# login as owner
$loginHtml = (Get-Page "$base/index.php?r=auth/login" 'Get' $null).Content
$csrf = ([regex]::Match($loginHtml, 'name="csrf" value="([^"]+)"')).Groups[1].Value
$login = Get-Page "$base/index.php?r=auth/login" 'Post' @{ r="auth/login"; csrf=$csrf; username="owner"; password="owner1234" }
Write-Output "owner login: $($login.StatusCode)"

# open invoice SI-00001 page to get its csrf
$inv = Get-Page "$base/index.php?r=sales%2Finvoices%2F1" 'Get' $null
Write-Output "invoice page: $($inv.StatusCode)"
$csrf2 = ([regex]::Match($inv.Content, 'name="csrf" value="([^"]+)"')).Groups[1].Value

# reverse it
$rev = Get-Page "$base/index.php?r=sales%2Finvoices%2F1%2Freverse" 'Post' @{ r="sales/invoices/1/reverse"; csrf=$csrf2; reason="Reversing test invoice created during screen testing" }
Write-Output "reverse: $($rev.StatusCode) $($rev.Headers['Location'])"
