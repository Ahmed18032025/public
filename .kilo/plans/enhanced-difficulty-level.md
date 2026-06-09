# Plan: Enhanced Difficulty Selection (Level vs Common Toggle)

## Problem
Current difficulty selection only offers Easy/Medium/Hard. Need to add optional Level field with mutual exclusivity.

## Data Model Changes

### Database Schema (`includes/class-gmcq-db.php`)
**Add column to `gmcq_questions` table:**
- `difficulty_level` VARCHAR(50) DEFAULT NULL (stores custom level text like "Level 1", "Advanced", etc.)

**Modify difficulty column:**
- Keep `difficulty` enum('easy','medium','hard') but allow NULL for Level mode

**Update schema at line 269:**
```sql
difficulty enum('easy','medium','hard') DEFAULT NULL,
difficulty_level varchar(50) DEFAULT NULL,
```

### Schema Contract Updates
Add `difficulty_level` to `gmcq_get_schema_contract()` columns array.

---

## Backend Changes (`includes/class-gmcq-questions.php`)

### 1. Validation (lines 758-763)
Add new validation logic:
```php
// Difficulty mode: 'common' (easy/medium/hard) or 'level' (custom text)
$difficulty_mode = isset($data['difficulty_mode']) ? sanitize_key($data['difficulty_mode']) : 'common';
$difficulty = null;
$difficulty_level = null;

if ($difficulty_mode === 'level' && !empty($data['difficulty_level'])) {
    $difficulty_level = sanitize_text_field($data['difficulty_level']);
} elseif ($difficulty_mode === 'common') {
    $valid_difficulty = array('easy', 'medium', 'hard');
    $difficulty = isset($data['difficulty']) ? sanitize_key($data['difficulty']) : 'medium';
    if (!in_array($difficulty, $valid_difficulty, true)) {
        $difficulty = null;
    }
}
```

### 2. Create/Update Functions (lines 73-86, 190-200)
Add `difficulty_level` to insert/update arrays:
```php
'difficulty_level' => $difficulty_level,
```

### 3. AJAX Handler (lines 973-983)
Read both fields:
```php
'difficulty_mode' => isset($_POST['difficulty_mode']) ? sanitize_key($_POST['difficulty_mode']) : 'common',
'difficulty_level' => isset($_POST['difficulty_level']) ? sanitize_text_field($_POST['difficulty_level']) : '',
```

### 4. Search Function (lines 653-655)
Add difficulty_level filter support:
```php
if (!empty($args['difficulty'])) {
    $where[] = $wpdb->prepare('q.difficulty = %s', $args['difficulty']);
}
if (!empty($args['difficulty_level'])) {
    $where[] = $wpdb->prepare('q.difficulty_level = %s', $args['difficulty_level']);
}
```

---

## Frontend Changes (`includes/class-gmcq-questions.php` - form renderer)

### Replace Difficulty Row (lines 1512-1520)
```html
<tr>
    <th scope="row"><?php esc_html_e('Difficulty', 'gmcq'); ?></th>
    <td>
        <div class="gmcq-difficulty-toggle" style="margin-bottom:10px">
            <label style="margin-right:15px">
                <input type="radio" name="difficulty_mode" value="common" checked> 
                <?php esc_html_e('Common', 'gmcq'); ?>
            </label>
            <label>
                <input type="radio" name="difficulty_mode" value="level"> 
                <?php esc_html_e('Level', 'gmcq'); ?>
            </label>
        </div>
        
        <div id="gmcq-common-difficulty">
            <select name="difficulty" id="gmcq-q-difficulty">
                <option value=""><?php esc_html_e('— None —', 'gmcq'); ?></option>
                <option value="easy" <?php selected($q_diff, 'easy'); ?>><?php esc_html_e('Easy', 'gmcq'); ?></option>
                <option value="medium" <?php selected($q_diff, 'medium'); ?>><?php esc_html_e('Medium', 'gmcq'); ?></option>
                <option value="hard" <?php selected($q_diff, 'hard'); ?>><?php esc_html_e('Hard', 'gmcq'); ?></option>
            </select>
        </div>
        
        <div id="gmcq-level-difficulty" style="display:none">
            <input type="text" name="difficulty_level" id="gmcq-q-difficulty-level" 
                   value="<?php echo esc_attr($q->difficulty_level ?? ''); ?>" 
                   placeholder="<?php esc_attr_e('Enter level (e.g., Level 1, Advanced)', 'gmcq'); ?>" 
                   style="width:200px">
        </div>
    </td>
</tr>
```

### Add Toggle Script (in the form's JS section)
```javascript
// Difficulty mode toggle
$('input[name="difficulty_mode"]').on('change', function() {
    if ($(this).val() === 'common') {
        $('#gmcq-common-difficulty').show();
        $('#gmcq-level-difficulty').hide();
    } else {
        $('#gmcq-common-difficulty').hide();
        $('#gmcq-level-difficulty').show();
    }
});

// Pre-check correct mode on edit
if ($q->difficulty_level) {
    $('input[name="difficulty_mode"][value="level"]').prop('checked', true).trigger('change');
} else {
    $('input[name="difficulty_mode"][value="common"]').prop('checked', true);
}
```

### Update List Page Display (around line 1334)
Show both values in list:
```php
<td>
    <?php if ($q->difficulty_level): ?>
        <?php echo esc_html($q->difficulty_level); ?>
    <?php elseif ($q->difficulty): ?>
        <span style="color:<?php echo esc_attr($diff_color[$q->difficulty] ?? '#666'); ?>;font-weight:600">
            <?php echo esc_html(ucfirst($q->difficulty)); ?>
        </span>
    <?php else: ?>
        <em style="color:#999"><?php esc_html_e('None', 'gmcq'); ?></em>
    <?php endif; ?>
</td>
```

---

## API Compatibility

### Search/Filter Args Update
Add `difficulty_level` to search args (around line 1119):
```php
'difficulty_level' => isset($_REQUEST['difficulty_level']) ? sanitize_text_field($_REQUEST['difficulty_level']) : '',
```

---

## Migration Notes

### For Existing Questions
- Questions with existing `difficulty` values keep them unchanged
- Questions using old "hard" but wanting Level: convert appropriately
- No data loss - both fields nullable

### Backward Compatibility
- AJAX endpoints accept both old format (`difficulty` only) and new format
- Frontend defaults to "Common" mode if no `difficulty_level` set