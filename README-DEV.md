# GMCQ Quiz Engine - Developer Guide

## Installation

### Prerequisites
- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+

### Plugin Installation
1. Copy the `wp-content/plugins/gmcq-core` folder to your WordPress installation
2. Go to **WordPress Admin → Plugins → Installed Plugins**
3. Activate "GMCQ Quiz Engine"
4. Or upload `gmcq-core.zip` via **Plugins → Add New → Upload Plugin**

### Initial Setup
After activation, go to **GMCQ → License** to activate your license key.

---

## Vercel License Server

### Endpoints
- **Production:** `https://public-xi-seven-92.vercel.app/api/validate-license`
- **Aliases:** `https://public-mm6kx7gn9-ahmed18032025s-projects.vercel.app/api/validate-license`

### Environment Variables (Production)
| Variable | Value |
|----------|-------|
| `JWT_SECRET` | Random secret string for signing tokens |
| `VALID_LICENSES` | Comma-separated SHA256 hashes of valid license keys |

### Deploying Updates
```powershell
cd "C:\Users\leonm\Local Sites\quizmanagementwebsite\app\public"
vercel --prod --yes
```

---

## Issuing License Keys to Users

### Generate License Key + Hash
```powershell
# Single key
$key = -join ((65..90)+(48..57) | Get-Random -Count 19 | %{[char]$_}) -replace '(.{4})','$1-'; $key = $key.Trim('-')
$hash = (Get-FileHash -Algorithm SHA256 -InputStream ([System.IO.MemoryStream]::new([System.Text.Encoding]::UTF8.GetBytes($key)))).Hash
Write-Host "Key: $key"
Write-Host "Hash: $hash"

# Batch of 10 keys
1..10 | ForEach-Object {
    $key = -join ((65..90)+(48..57) | Get-Random -Count 19 | %{[char]$_}) -replace '(.{4})','$1-'; $key = $key.Trim('-')
    $hash = (Get-FileHash -Algorithm SHA256 -InputStream ([System.IO.MemoryStream]::new([System.Text.Encoding]::UTF8.GetBytes($key)))).Hash
    Write-Host "$key : $hash"
}
```

### Add New User Key
```powershell
vercel env rm VALID_LICENSES production --yes
vercel env add VALID_LICENSES production --value="HASH1,HASH2,NEW_HASH_HERE" --yes
vercel --prod --yes
```

### To User
Share the license key (format: `XXXX-XXXX-XXXX-XXXX-XXX`) with the user. They enter it at **GMCQ → License**.

---

## Plugin Architecture

### Core Files
- `gmcq-core.php` - Main plugin file, loads all components
- `includes/class-gmcq-admin.php` - Admin menu structure
- `includes/class-gmcq-license.php` - License validation (endpoint defined at line 9)
- `includes/class-gmcq-db.php` - Database schema
- `includes/class-gmcq-frontend.php` - Frontend shortcodes
- `includes/class-gmcq-*.php` - Feature modules (categories, questions, quizzes, attempts)

### License Flow
1. User enters license key in admin
2. Plugin POSTs to Vercel endpoint with `{license_key, domain}`
3. Server validates hash, returns signed JWT token
4. Token stored in `wp_options` (`gmcq_license_token`, `gmcq_license_key`, `gmcq_license_activated_at`)
5. Token validated on each admin page load (365-day expiry)

---

## Testing

Test the license endpoint:
```powershell
node -e "const https = require('https'); const data = JSON.stringify({license_key: 'M4GH-6XQ9-5SR2-ECAZ-FNY', domain: 'https://example.com'}); const req = https.request({hostname: 'public-xi-seven-92.vercel.app', path: '/api/validate-license', method: 'POST', headers: {'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data)}}, (res) => { let body = ''; res.on('data', chunk => body += chunk); res.on('end', () => console.log('Status:', res.statusCode, body)); }); req.write(data); req.end();"
```

Expected: `Status: 200 {"valid":true,"token":"...","expires_in":31536000}`

---

## Sample License Keys (Pre-generated)

| License Key | SHA256 Hash |
|-------------|-------------|
| M4GH-6XQ9-5SR2-ECAZ-FNY | 08B85163FD69CCF008F5CC3236E1723F67E6EFCE1039637EA49602626FDB328F |
| ALVZ-YR1H-QGND-SI8O-BXP | 2C9FDD2DCBB1C756C9DB1AF29A3489A9D203274FB2C1F0EC88BCF9B5A68C0290 |
| DJ5R-8GOE-V30M-FS9P-KXQ | 1B934B66AF16D19278791F8EA0168A68A25AA44D5A5AA24F624B89625E213D33 |