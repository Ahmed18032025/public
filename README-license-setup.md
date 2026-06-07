# GMCQ License Key Protection Setup

## Quick Start

### 1. Deploy Netlify Function
The license validation endpoint is ready in `netlify/functions/validate-license.js`.

### 2. Generate License Keys
```powershell
# Generate a license key (format: XXXX-XXXX-XXXX-XXXX)
$key = -join ((65..90) + (48..57) | Get-Random -Count 19 | % {[char]$_}) -replace '(.{4})','$1-'
$key = $key.Trim('-')
# Get hash for server
$hash = (Get-FileHash -Algorithm SHA256 -InputStream ([System.IO.MemoryStream]::new([System.Text.Encoding]::UTF8.GetBytes($key)))).Hash
Write-Host "Key: $key"
Write-Host "Hash: $hash"
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