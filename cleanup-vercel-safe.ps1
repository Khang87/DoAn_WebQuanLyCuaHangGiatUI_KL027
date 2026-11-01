
param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectId,

    [Parameter(Mandatory = $true)]
    [string]$ProductionDomain,

    [string]$TeamId = "",
    [int]$KeepLatest = 5,
    [switch]$Execute
)

$ErrorActionPreference = "Stop"

if ($KeepLatest -lt 5) {
    throw "Phai giu it nhat 5 deployment."
}

$Secure = Read-Host "Vercel API Token" -AsSecureString
$Ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Secure)

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
    $Scope = "?teamId=$([uri]::EscapeDataString($TeamId))"
}

function ApiUrl([string]$Path, [string]$Query = "") {
    $Parts = @()

    if ($TeamId) {
        $Parts += "teamId=$([uri]::EscapeDataString($TeamId))"
    }

    if ($Query) {
        $Parts += $Query
    }

    $Url = "$Base$Path"
    if ($Parts.Count -gt 0) {
        $Url += "?" + ($Parts -join "&")
    }

    return $Url
}

# Xac minh project
$Project = Invoke-RestMethod `
    -Uri (ApiUrl "/v9/projects/$ProjectId") `
    -Headers $Headers

if ($Project.id -ne $ProjectId) {
    throw "Project ID khong khop."
}

Write-Host "Project: $($Project.name)"

# Xac minh domain Production hien tai
$Domain = $ProductionDomain `
    -replace '^https?://', '' `
    -replace '/.*$', ''

$EncodedDomain = [uri]::EscapeDataString($Domain)

$Active = Invoke-RestMethod `
    -Uri (ApiUrl "/v13/deployments/ignored" "url=$EncodedDomain") `
    -Headers $Headers

$ActiveId = if ($Active.id) { $Active.id } else { $Active.uid }

if (-not $ActiveId -or $Active.projectId -ne $ProjectId) {
    throw "Khong xac minh duoc Production cua project."
}

Write-Host "Active Production: $ActiveId"

# Lay tat ca deployment
$All = @()
$Until = $null

do {
    $Query = "projectId=$([uri]::EscapeDataString($ProjectId))&limit=100"

    if ($null -ne $Until) {
        $Query += "&until=$Until"
    }

    $Result = Invoke-RestMethod `
        -Uri (ApiUrl "/v6/deployments" $Query) `
        -Headers $Headers

    $Page = @($Result.deployments)
    if ($Page.Count -eq 0) { break }

    $All += $Page

    $Next = $Result.pagination.next
    if ($null -eq $Next) { break }

    if ("$Next" -eq "$Until") {
        throw "Pagination khong tien trien."
    }

    $Until = $Next
} while ($true)

$Sorted = @($All | Sort-Object created -Descending)

$Protected = @{}

# Bao ve Production hien tai
$Protected[$ActiveId] = "Active Production"

# Bao ve 5 deployment moi nhat
$Recent = @($Sorted | Select-Object -First $KeepLatest)

foreach ($D in $Recent) {
    $Protected[$D.uid] = "Recent"
}

# Kiem tra alias thuc te cua TUNG deployment
# Neu API loi, dung toan bo, khong xoa.
foreach ($D in $Sorted) {
    $Url = ApiUrl "/v2/deployments/$($D.uid)/aliases"

    $AliasResult = Invoke-RestMethod `
        -Uri $Url `
        -Headers $Headers

    if ($null -eq $AliasResult.aliases) {
        throw "Khong doc duoc aliases: $($D.uid)"
    }

    if (@($AliasResult.aliases).Count -gt 0) {
        $Protected[$D.uid] = "Active Alias"
    }
}

# Chuan bi danh sach xoa
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
    Write-Host "Khong co deployment can xoa."
    exit
}

if (-not $Execute) {
    Write-Host "DRY RUN - Chua xoa gi."
    Write-Host "Dung -Execute sau khi kiem tra."
    exit
}

Write-Host ""
Write-Host "Sap xoa $($Candidates.Count) deployment."
$Confirm = Read-Host "Nhap DELETE de xac nhan"

if ($Confirm -cne "DELETE") {
    Write-Host "Da huy."
    exit
}

# Xac minh lai Production ngay truoc khi xoa
$Check = Invoke-RestMethod `
    -Uri (ApiUrl "/v13/deployments/ignored" "url=$EncodedDomain") `
    -Headers $Headers

$CheckId = if ($Check.id) { $Check.id } else { $Check.uid }

if ($CheckId -ne $ActiveId) {
    throw "Production da thay doi. Huy xoa."
}

$Deleted = 0
$Failed = 0

foreach ($D in $Candidates) {
    # Kiem tra alias lai truoc khi xoa
    try {
        $AliasCheck = Invoke-RestMethod `
            -Uri (ApiUrl "/v2/deployments/$($D.uid)/aliases") `
            -Headers $Headers

        if ($null -eq $AliasCheck.aliases) {
            throw "Khong xac minh duoc alias."
        }

        if (@($AliasCheck.aliases).Count -gt 0) {
            Write-Host "Skipped active alias: $($D.uid)"
            continue
        }

        $DeleteUrl = ApiUrl "/v13/deployments/$($D.uid)"

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

        # Dung neu co loi, tranh xoa tiep khi
        # trang thai API khong chac chan.
        break
    }
}

Write-Host ""
Write-Host "Hoan tat."
Write-Host "Da xoa: $Deleted"
Write-Host "That bai: $Failed"
