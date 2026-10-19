
param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectId,

    [string]$TeamId = "",

    [int]$KeepLatest = 5,

    [switch]$Execute
)

$ErrorActionPreference = "Stop"

if ($KeepLatest -lt 5) {
    throw "KeepLatest phai >= 5."
}

# Nhap token an toan, khong luu vao file
$SecureToken = Read-Host "Vercel API Token" -AsSecureString
$Ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureToken)
try {
    $Token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($Ptr)
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($Ptr)
}

$Headers = @{
    Authorization = "Bearer $Token"
}

$Base = "https://api.vercel.com"
$Scope = ""
if ($TeamId) {
    $Scope = "&teamId=$([uri]::EscapeDataString($TeamId))"
}

# Lay tat ca deployment, bao gom pagination
$All = @()
$Until = $null

do {
    $Url = "$Base/v6/deployments?projectId=$([uri]::EscapeDataString($ProjectId))&limit=100$Scope"

    if ($null -ne $Until) {
        $Url += "&until=$Until"
    }

    $Result = Invoke-RestMethod `
        -Uri $Url `
        -Headers $Headers `
        -Method Get

    $All += @($Result.deployments)

    $Next = $Result.pagination.next
    if ($null -eq $Next) { break }

    if ("$Next" -eq "$Until") {
        throw "Pagination khong tien trien."
    }

    $Until = $Next
} while ($true)

$Sorted = @($All | Sort-Object created -Descending)

# Bao ve moi deployment production.
# Cach nay bao thu, giu ca production cu.
$Protected = @{}
$Recent = @($Sorted | Select-Object -First $KeepLatest)

foreach ($D in $Recent) {
    $Protected[$D.uid] = "Recent"
}

foreach ($D in $Sorted) {
    if ($D.target -eq "production") {
        $Protected[$D.uid] = "Production"
    }

    # Bao ve deployment co alias (domain)
    if (@($D.alias).Count -gt 0 -and $D.alias) {
        $Protected[$D.uid] = "Alias"
    }
}

$Candidates = @(
    $Sorted | Where-Object {
        -not $Protected.ContainsKey($_.uid)
    }
)

Write-Host ""
Write-Host "Tong deployment: $($Sorted.Count)"
Write-Host "Duoc bao ve: $($Protected.Count)"
Write-Host "Du kien xoa: $($Candidates.Count)"
Write-Host ""

$Candidates | Select-Object `
    uid, url, target,
    @{Name="Created";Expression={
        [DateTimeOffset]::FromUnixTimeMilliseconds(
            [long]$_.created
        ).LocalDateTime
    }} | Format-Table -AutoSize

if ($Candidates.Count -eq 0) {
    Write-Host "Khong co deployment nao can xoa."
    exit
}

if (-not $Execute) {
    Write-Host ""
    Write-Host "DRY RUN: Chua xoa deployment nao."
    Write-Host "Them -Execute de bat dau xoa."
    exit
}

Write-Host ""
$Confirm = Read-Host "Nhap DELETE de xac nhan"

if ($Confirm -cne "DELETE") {
    Write-Host "Da huy. Khong co gi bi xoa."
    exit
}

$Deleted = 0
$Failed = 0

foreach ($D in $Candidates) {
    $DeleteUrl = "$Base/v13/deployments/$($D.uid)?$($Scope.TrimStart('&'))"

    try {
        Invoke-RestMethod `
            -Uri $DeleteUrl `
            -Headers $Headers `
            -Method Delete | Out-Null

        Write-Host "Deleted: $($D.url)"
        $Deleted++
    }
    catch {
        Write-Warning "Failed: $($D.uid) - $($_.Exception.Message)"
        $Failed++
    }
}

Write-Host ""
Write-Host "Hoan tat."
Write-Host "Da xoa: $Deleted"
Write-Host "That bai: $Failed"
