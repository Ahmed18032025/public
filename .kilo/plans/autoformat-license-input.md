# Plan: Auto-Format License Key Input + Backend Management

## Problem
Users paste or type license keys in any format (lowercase, no dashes, wrong grouping), causing activation failures.

## Current state
- Input in `class-gmcq-license.php:128` is a plain text field
- Placeholder says `XXXX-XXXX-XXXX-XXXX` (4 groups), but keys are actually 19 chars in format `XXXX-XXXX-XXXX-XXXX-XXX` (4-4-4-4-3)
- No validation/formatting on the client side

## Changes

### 1. `wp-content/plugins/gmcq-core/includes/class-gmcq-license.php`

**Line 128** — update placeholder and add class:
```php
<td><input type="text" name="license_key" id="gmcq-license-key" class="regular-text aqc-license-input" placeholder="XXXX-XXXX-XXXX-XXXX-XXX" required autocomplete="off"></td>
```

**Lines 135–157** — extend the existing jQuery script to include input masking:

```javascript
function formatLicenseKey(value) {
    var raw = value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 19);
    var formatted = '';
    var groups = [4, 4, 4, 4, 3];
    var pos = 0;
    for (var i = 0; i < groups.length; i++) {
        if (i > 0) formatted += '-';
        formatted += raw.substring(pos, pos + groups[i]);
        pos += groups[i];
    }
    return formatted;
}

jQuery(function($) {
    $('#gmcq-license-key').on('input paste', function() {
        var $el = $(this);
        var cursorPos = this.selectionStart;
        var oldLen = $el.val().length;
        var newVal = formatLicenseKey($el.val());
        $el.val(newVal);
        var newLen = newVal.length;
        if (cursorPos === oldLen) {
            this.setSelectionRange(newLen, newLen);
        }
    });

    $( '#gmcq-license-form' ).on( 'submit', function( e ) {
        // existing submit handler unchanged
    } );
});
```

### 2. Server-side safety (already fine)
- `class-gmcq-license.php:172` — `sanitize_text_field` handles dashes
- `api/validate-license.js:25` — hashing strips everything non-hex via `digest('hex')`, so dashes in input don't matter

## Backend Management — Adding Keys to Vercel

### Viewing Current Keys
```powershell
vercel env ls
```

### Adding a New License Key

**Generate a key+hash:**
```powershell
$key=-join((65..90)+(48..57)|Get-Random -Count 19|%{[char]$_}) -replace '(.{4})','$1-';$key=$key.Trim('-')
$hash=(Get-FileHash -Alg SHA256 -InputStream ([IO.MemoryStream]::new([Text.Encoding]::UTF8.GetBytes($key)))).Hash
Write-Host "$key : $hash"
```

**Method A: Vercel CLI (recommended)**
```powershell
vercel env add VALID_LICENSES production
```
Paste the full comma-separated list (existing hashes + new one):
```
08B85163FD69CCF008F5CC3236E1723F67E6EFCE1039637EA49602626FDB328F,NEW_HASH_HERE
```

**Method B: Vercel Dashboard**
1. Go to https://vercel.com/ahmed18032025s-projects/public/settings/environment-variables
2. Find `VALID_LICENSES` → click pencil/edit
3. Append the new hash (comma-separated)
4. Click **Save**

### Redeploy After Adding Keys
```powershell
vercel --prod
```

### Managing Multiple Users
- Keep a spreadsheet or text file of issued keys and their hashes
- Each user gets a unique key from the batch of 10 that was pre-generated
- The `VALID_LICENSES` env var contains ALL hashes currently allowed
- To revoke a key: remove its hash from the env var and re-deploy

## Behavior after change
- Typing or pasting `p7is0dv5hrfck2bz6ug` → auto-format to `P7IS-0DV5-HRFC-K2BZ-6UG`
- Only uppercase letters and digits allowed (others stripped)
- Max 19 characters accepted
- Dashes auto-inserted at correct positions: 4-4-4-4-3
- Cursor stays at the end after paste/typing