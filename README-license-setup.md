# GMCQ License Key Protection Setup

## Quick Start

### 1. Deploy Netlify Function
The license validation endpoint is ready in `netlify/functions/validate-license.js`.

### 2. Generate License Keys
```powershell
# Generate a license key (format: XXXX-XXXX-XXXX-XXXX)
$key = -join ((65..90) + (48..57) | Get-Random -Count 19 | % {[char]$_}) -replace '(.{4})','$1-'
$key = $key.Trim('-')
# Get hash for server (add this hash to VALID_LICENSES in Netlify)
$hash = (Get-FileHash -Algorithm SHA256 -InputStream ([System.IO.MemoryStream]::new([System.Text.Encoding]::UTF8.GetBytes($key)))).Hash
Write-Host "Key: $key"
Write-Host "Hash: $hash"
```

**Quick: Generate 10 keys at once:**
```powershell
1..10 | ForEach-Object {
    $key = -join ((65..90) + (48..57) | Get-Random -Count 19 | % {[char]$_}) -replace '(.{4})','$1-'; $key = $key.Trim('-')
    $hash = (Get-FileHash -Algorithm SHA256 -InputStream ([System.IO.MemoryStream]::new([System.Text.Encoding]::UTF8.GetBytes($key)))).Hash
    Write-Host "$($key) : $($hash)"
}
```

## Sample License Keys (Ready to Use)
| License Key | SHA256 Hash |
|-------------|-----------|
| M4GH-6XQ9-5SR2-ECAZ-FNY | 08B85163FD69CCF008F5CC3236E1723F67E6EFCE1039637EA49602626FDB328F |
| ALVZ-YR1H-QGND-SI8O-BXP | 2C9FDD2DCBB1C756C9DB1AF29A3489A9D203274FB2C1F0EC88BCF9B5A68C0290 |
| DJ5R-8GOE-V30M-FS9P-KXQ | 1B934B66AF16D19278791F8EA0168A68A25AA44D5A5AA24F624B89625E213D33 |
| EIK0-UQ3L-4HG8-CXJO-5BW | A81AB96D0E80983E75727A8A1ABABC3E1285182F665D6F6567B280DB41531624 |
| 90FW-IJNM-QOT2-1KL3-654 | 6DC909D5315180D7D0495ED67495DD08D858E11A506E2E25E8A69A8E7A88576C |

**Use Format in VALID_LICENSES:**
```
08B85163FD69CCF008F5CC3236E1723F67E6EFCE1039637EA49602626FDB328F,2C9FDD2DCBB1C756C9DB1AF29A3489A9D203274FB2C1F0EC88BCF9B5A68C0290,...
```

### 3. Configure Netlify Environment Variables
In Netlify Dashboard → Site Settings → Build & Deploy → Environment:

| Name | Value |
|------|-------|
| `JWT_SECRET` | Random secret (save this for future use!) |
| `VALID_LICENSES` | Comma-separated SHA256 hashes |

### 4. Update Plugin Endpoint
Edit `wp-content/plugins/gmcq-core/gmcq-core.php` line 29:
```php
define( 'GMCQ_LICENSE_ENDPOINT', 'https://YOUR-SITE.netlify.app/.netlify/functions/validate-license' );
```

## Files Changed in Plugin

- `includes/class-gmcq-license.php` - License validation logic
- `gmcq-core.php` - Early license check redirects to activation page
- `includes/class-gmcq-admin.php` - License menu
- `includes/class-gmcq-frontend.php` - License checks on shortcodes

## How It Works

1. User enters license key in GMCQ → License admin page
2. Plugin sends key + domain to Netlify function
3. Server validates hash and returns signed JWT token
4. Token stored locally (valid 30 days)
5. All plugin features check token before working
6. Expired tokens prompt re-validation