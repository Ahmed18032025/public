# Plan: Extend License to 1 Year + Document Key Generation

## Changes

### 1. Vercel function — `api/validate-license.js`
- Line 54: `expiresIn: '30d'` → `expiresIn: '365d'`
- Line 60: `expires_in: 2592000` → `expires_in: 31536000`

### 2. WordPress plugin — `wp-content/plugins/gmcq-core/includes/class-gmcq-license.php`
- Line 21: `30 * DAY_IN_SECONDS` → `365 * DAY_IN_SECONDS`

### 3. README — `README-license-setup.md`
- Line 21: "valid 30 days" → "valid 365 days (1 year)"
- Line 91: "30 days" → "365 days (1 year)"

## How to Generate Keys for Other Users

Already documented in README lines 38–54 — two PowerShell one-liners:

```powershell
# Single key
$key = -join ((65..90)+(48..57)|Get-Random -Count 19|%{[char]$_}) -replace '(.{4})','$1-'; $key=$key.Trim('-')
$hash=(Get-FileHash -Alg SHA256 -InputStream ([IO.MemoryStream]::new([Text.Encoding]::UTF8.GetBytes($key)))).Hash
Write-Host "Key: $key`nHash: $hash"

# Batch of 10
1..10|%{
  $key=-join((65..90)+(48..57)|Get-Random -Count 19|%{[char]$_}) -replace '(.{4})','$1-';$key=$key.Trim('-')
  $hash=(Get-FileHash -Alg SHA256 -InputStream ([IO.MemoryStream]::new([Text.Encoding]::UTF8.GetBytes($key)))).Hash
  Write-Host "$key : $hash"
}
```

**To issue to a new user:**
1. Run the script to get a fresh `Key` + `Hash`
2. Append the new `Hash` to the `VALID_LICENSES` env var in Vercel (comma-separated)
3. Re-deploy on Vercel
4. Share the `Key` (e.g. `XXXX-XXXX-XXXX-XXXX-XXX`) with the user — they enter it in GMCQ → License
