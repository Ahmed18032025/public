# Plan: Add License Hash to Vercel — Two Methods

## Problem
User cannot find the Environment Variables UI in Vercel Dashboard to add/update `VALID_LICENSES` hashes for new users.

## Solution — Two Ways to Add/Update `VALID_LICENSES`

### Method A: Vercel CLI (easiest since CLI is already set up)

**Step 1 — Add the new hash to Vercel env:**
```powershell
cd "C:\Users\leonm\Local Sites\quizmanagementwebsite\app\public"
vercel env add VALID_LICENSES production
```
When prompted, paste the **full comma-separated list** including existing hashes + the new one:
```
08B85163FD69CCF008F5CC3236E1723F67E6EFCE1039637EA49602626FDB328F,2C9FDD2DCBB1C756C9DB1AF29A3489A9D203274FB2C1F0EC88BCF9B5A68C0290,NEW_HASH_HERE
```

**Step 2 — Re-deploy to apply:**
```powershell
vercel --prod
```

**To list current env vars (see what's already set):**
```powershell
vercel env ls
```

---

### Method B: Vercel Dashboard (if they prefer UI)

1. Go to **https://vercel.com/ahmed18032025s-projects/public**
2. Click **Settings** (top tab)
3. Click **Environment Variables** (left sidebar)
4. Find `VALID_LICENSES` → click the **pencil/edit** icon
5. Append the new hash (comma-separated) to the existing value
6. Click **Save**
7. Go to **Deployments** tab → click **Redeploy** on the latest production deployment

---

## Generating a New Key + Hash for a User

Run this in PowerShell (single key):
```powershell
$key=-join((65..90)+(48..57)|Get-Random -Count 19|%{[char]$_}) -replace '(.{4})','$1-';$key=$key.Trim('-')
$hash=(Get-FileHash -Alg SHA256 -InputStream ([IO.MemoryStream]::new([Text.Encoding]::UTF8.GetBytes($key)))).Hash
Write-Host "Key: $key`nHash: $hash"
```

Or generate 10 at once:
```powershell
1..10|%{$k=-join((65..90)+(48..57)|Get-Random -Count 19|%{[char]$_}) -replace '(.{4})','$1-';$k=$k.Trim('-');$h=(Get-FileHash -Alg SHA256 -InputStream ([IO.MemoryStream]::new([Text.Encoding]::UTF8.GetBytes($k)))).Hash;Write-Host "$k : $h"}
```

Then add the hash to `VALID_LICENSES` using Method A or B above, re-deploy, and share the **key** with the user.
