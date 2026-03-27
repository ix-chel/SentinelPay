param(
    [string]$BaseUrl = 'http://localhost:8080/api/v1',
    [string]$ApiKey = 'sp_live_demo1234567890',
    [decimal]$Amount = 25.00,
    [string]$Currency = 'USD',
    [string]$HmacSecret = 'your-super-secret-hmac-key-change-in-production',
    [string]$WebhookUrl = ''
)

$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Write-Json {
    param($Value)
    $Value | ConvertTo-Json -Depth 10
}

function New-Signature {
    param([string]$Body)

    $hmac = [System.Security.Cryptography.HMACSHA256]::new([System.Text.Encoding]::UTF8.GetBytes($HmacSecret))

    try {
        $hashBytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($Body))
    } finally {
        $hmac.Dispose()
    }

    return ([System.BitConverter]::ToString($hashBytes)).Replace('-', '').ToLowerInvariant()
}

$headers = @{
    'X-API-Key' = $ApiKey
}

Write-Step "Health check"
$health = Invoke-RestMethod -Uri "$BaseUrl/health" -Method Get
Write-Host (Write-Json $health)

Write-Step "Fetching balances"
$balances = Invoke-RestMethod -Uri "$BaseUrl/balances" -Method Get -Headers $headers
Write-Host (Write-Json $balances)

if (-not $balances.accounts -or $balances.accounts.Count -lt 2) {
    throw 'At least two accounts are required for the demo flow.'
}

$sourceAccount = $balances.accounts[0]
$destinationAccount = $balances.accounts[1]
$idempotencyKey = "demo-transfer-$([guid]::NewGuid().ToString())"

$transferBody = @{
    source_account_id = $sourceAccount.id
    destination_account_id = $destinationAccount.id
    amount = '{0:F2}' -f $Amount
    currency = $Currency.ToUpperInvariant()
} | ConvertTo-Json

$transferHeaders = @{
    'X-API-Key' = $ApiKey
    'Idempotency-Key' = $idempotencyKey
    'Content-Type' = 'application/json'
    'X-Signature' = New-Signature -Body $transferBody
}

Write-Step "Creating transfer"
$transferResponse = Invoke-RestMethod -Uri "$BaseUrl/transfers" -Method Post -Headers $transferHeaders -Body $transferBody
Write-Host (Write-Json $transferResponse)

Write-Step "Replaying same request to prove idempotency"
$replayResponse = Invoke-RestMethod -Uri "$BaseUrl/transfers" -Method Post -Headers $transferHeaders -Body $transferBody
Write-Host (Write-Json $replayResponse)

Write-Step "Fetching balances after transfer"
$balancesAfter = Invoke-RestMethod -Uri "$BaseUrl/balances" -Method Get -Headers $headers
Write-Host (Write-Json $balancesAfter)

$transferId = $transferResponse.transfer.id

Write-Step "Fetching transfer by ID"
$transferDetails = Invoke-RestMethod -Uri "$BaseUrl/transfers/$transferId" -Method Get -Headers $headers
Write-Host (Write-Json $transferDetails)

Write-Step "Fetching ledger for source account"
$ledger = Invoke-RestMethod -Uri "$BaseUrl/ledger/$($sourceAccount.id)" -Method Get -Headers $headers
Write-Host (Write-Json $ledger)

if ($WebhookUrl) {
    Write-Step "Registering webhook"

    $webhookHeaders = @{
        'X-API-Key' = $ApiKey
        'Content-Type' = 'application/json'
    }

    $webhookBody = @{
        url = $WebhookUrl
        events = @('transfer.succeeded')
    } | ConvertTo-Json

    $webhookResponse = Invoke-RestMethod -Uri "$BaseUrl/webhooks" -Method Post -Headers $webhookHeaders -Body $webhookBody
    Write-Host (Write-Json $webhookResponse)
}

Write-Step "Demo complete"
Write-Host "Source account:      $($sourceAccount.id)"
Write-Host "Destination account: $($destinationAccount.id)"
Write-Host "Transfer ID:         $transferId"
Write-Host "Idempotency-Key:     $idempotencyKey"
