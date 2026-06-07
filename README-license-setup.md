# GMCQ License Key Protection Setup

## Vercel Deployment Checklist

### 1. Prepare Files
The following files are ready for Vercel:
- `api/validate-license.js` — Vercel Serverless Function (Node.js)
- `vercel.json` — Vercel project configuration
- `package.json` — declares `jsonwebtoken` dependency

### 2. Deploy to Vercel
**Option A: Vercel CLI (recommended)**
```powershell
npm install -g vercel
vercel login
vercel
```
Follow the prompts. When asked for project settings, confirm the defaults.

**Option B: Vercel Dashboard**
1. Go to [vercel.com/new](https://vercel.com/new)
2. Import your repository (or drag-and-drop the `app/public` folder)
3. Framework preset: **Other**
4. Build command: leave empty (static site, no build step)
5. Publish directory: `.` (root)
6. Click **Deploy**

### 3. Configure Environment Variables
In Vercel Dashboard → Your Project → Settings → Environment Variables:

| Name | Value |
|------|-------|
| `JWT_SECRET` | A random secret string (save this securely) |
| `VALID_LICENSES` | Comma-separated SHA256 hashes of valid license keys |

After adding env vars, **re-deploy** the project so they take effect.

### 4. Generate License Keys
```powershell
# Generate a single key + hash
$key = -join ((65..90) + (48..57) | Get-Random -Count 19 | % {[char]$_}) -replace '(.{4})','$1-'; $key = $key.Trim('-')
$hash = (Get-FileHash -Algorithm SHA256 -InputStream ([System.IO.MemoryStream]::new([System.Text.Encoding]::UTF8.GetBytes($key)))).Hash
Write-Host "Key: $key"
Write-Host "Hash: $hash"
```

```powershell
# Generate 10 keys at once
1..10 | ForEach-Object {
    $key = -join ((65..90) + (48..57) | Get-Random -Count 19 | % {[char]$_}) -replace '(.{4})','$1-'; $key = $key.Trim('-')
    $hash = (Get-FileHash -Algorithm SHA256 -InputStream ([System.IO.MemoryStream]::new([System.Text.Encoding]::UTF8.GetBytes($key)))).Hash
    Write-Host "$($key) : $($hash)"
}
```

### 5. Sample License Keys (Ready to Use)

| License Key | SHA256 Hash |
|-------------|-----------|
| M4GH-6XQ9-5SR2-ECAZ-FNY | 08B85163FD69CCF008F5CC3236E1723F67E6EFCE1039637EA49602626FDB328F |
| ALVZ-YR1H-QGND-SI8O-BXP | 2C9FDD2DCBB1C756C9DB1AF29A3489A9D203274FB2C1F0EC88BCF9B5A68C0290 |
| DJ5R-8GOE-V30M-FS9P-KXQ | 1B934B66AF16D19278791F8EA0168A68A25AA44D5A5AA24F624B89625E213D33 |
| EIK0-UQ3L-4HG8-CXJO-5BW | A81AB96D0E80983E75727A8A1ABABC3E1285182F665D6F6567B280DB41531624 |
| 90FW-IJNM-QOT2-1KL3-654 | 6DC909D5315180D7D0495ED67495DD08D858E11A506E2E25E8A69A8E7A88576C |

**Format for `VALID_LICENSES` env var:**
```
08B85163FD69CCF008F5CC3236E1723F67E6EFCE1039637EA49602626FDB328F,2C9FDD2DCBB1C756C9DB1AF29A3489A9D203274FB2C1F0EC88BCF9B5A68C0290,...
```

### 6. Update WordPress Plugin
After deploying to Vercel, you'll get a URL like `https://gmcq-license.vercel.app`.

Open `wp-content/plugins/gmcq-core/gmcq-core.php` line 29 and update:
```php
define( 'GMCQ_LICENSE_ENDPOINT', 'https://YOUR-PROJECT.vercel.app/api/validate-license' );
```

Also update `wp-content/plugins/gmcq-core/includes/class-gmcq-license.php` line 9 with the same URL.

### 7. Verify Setup
- [ ] Visit your Vercel function URL directly: `https://YOUR-PROJECT.vercel.app/api/validate-license` — should return a 405 JSON response (only accepts POST)
- [ ] In WordPress admin, go to **GMCQ → License** and try activating with one of the sample keys
- [ ] Confirm the license status shows "Activated"

## How It Works

1. User enters license key in GMCQ → License admin page
2. Plugin sends key + domain to Vercel function
3. Server validates hash and returns signed JWT token
4. Token stored locally (valid 30 days)
5. All plugin features check token before working
6. Expired tokens prompt re-validation

## Files Changed in Plugin

- `includes/class-gmcq-license.php` — License validation logic + endpoint URL
- `gmcq-core.php` — Endpoint constant + early license check redirect
- `includes/class-gmcq-admin.php` — License menu + AJAX handlers
- `includes/class-gmcq-frontend.php` — Shortcodes + REST route protection
