# Panel Design: Question Management v2

## Updates from Master Implementation Plan

**Issues Resolved:** Q1 (Soft Delete), Q2 (Filter Infrastructure), Q3 (usage_count hooks), Q4 (created_by), Q5 (Composite Index), Q6 (Batch Import), Q8 (question_hash)

| Issue | Change | Section |
|-------|--------|---------|
| X1/Q8 | Added `question_hash` column + generation hook | Database Schema |
| Q1 | Added soft delete: `deleted_at`, `deleted_by`, restore + permanent delete workflows | Schema + Workflows |
| Q2 | Filter infrastructure: `no_category`, `unassigned`, `duplicates`, `inactive_category`, `archived_quiz` | Filters |
| Q3 | `usage_count` hooks on question_map changes + daily cron recalculation | Hooks |
| Q4 | Added `created_by` column — set on insert | Schema |
| Q5 | Added composite index `idx_category_active (category_id, is_active)` | Schema |
| Q6 | Added `gmcq_batch_save_questions()` for CSV import | Endpoints |
| Q7 | Answers inherit question active state — no separate soft delete needed | Confirmed |

**Schema Changes from V1:**
- Added: `question_hash` (UNIQUE), `import_id` (FK to gmcq_imports), `created_by`, `deleted_at`, `deleted_by`
- Indexes: `idx_question_hash` (UNIQUE), `idx_category_active`, `idx_import`, `idx_usage`

---

## Purpose
Centralized question bank for creating, editing, searching, and managing all MCQ questions across the quiz engine. Questions are reusable across multiple quizzes.

**Data Ownership:** Questions own Category/Subcategory. This is the source of truth for classification.

---

## Page 1: Question List

```
┌─────────────────────────────────────────────────────────────────┐
│  Question Management                              [+ Add Question]│
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Filter Tabs:                                                     │
│  [All (3450)] [Active (3400)] [No Category (50)] [Unassigned (200)]│
│  [Duplicates (15)] [Inactive (50)]                                │
│                                                                   │
│  Category: [▼ All       ]  Difficulty: [▼ All    ]               │
│  Type:     [▼ All       ]  Status:     [▼ Active ]               │
│  Search:   [________________________]  [Filter] [Reset]          │
│                                                                   │
│  ┌───┬──────────────────────┬──────────┬──────┬──────┬───┐      │
│  │ ☐ │ Question             │ Category │ Type │ Diff │ # │      │
│  ├───┼──────────────────────┼──────────┼──────┼──────┼───┤      │
│  │ ☐ │ What is the capital  │ SSC CGL  │ MCQ  │ Easy │ 3 │      │
│  │   │ of India?            │          │      │      │   │      │
│  │   │ [Edit] [Delete]      │          │      │      │   │      │
│  ├───┼──────────────────────┼──────────┼──────┼──────┼───┤      │
│  │ ☐ │ Which river is the   │ RRB NTPC │ MCQ  │ Med  │ 2 │      │
│  │   │ longest in India?    │          │      │      │   │      │
│  │   │ [Edit] [Delete]      │          │      │      │   │      │
│  └───┴──────────────────────┴──────────┴──────┴──────┴───┘      │
│                                                                   │
│  Bulk: [▼ Delete / Change Category] [Apply]                      │
│  Showing 1-20 of 3,450    [◀ 1 2 3 ... 173 ▶]                  │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Filter Tabs (Dashboard Integration)

| Tab | Filter Param | SQL Condition |
|---|---|---|
| All | (none) | `is_active IN (0,1)` |
| Active | `active` | `is_active = 1 AND deleted_at IS NULL` |
| No Category | `no_category` | `category_id IS NULL` |
| Unassigned | `unassigned` | `usage_count = 0` |
| Duplicates | `duplicates` | `question_hash IN (SELECT question_hash FROM questions GROUP BY question_hash HAVING COUNT > 1)` |
| Inactive | `inactive` | `is_active = 0 AND deleted_at IS NOT NULL` |
| Inactive Category | `inactive_category` | `EXISTS (SELECT 1 FROM categories c WHERE c.id = q.category_id AND c.is_active = 0)` |
| Archived Quiz | `archived_quiz` | `EXISTS (SELECT 1 FROM question_map qm JOIN quizzes_meta zm ON zm.quiz_id = qm.quiz_id WHERE qm.question_id = q.id AND zm.is_active = 0)` |

### List Columns

| Column | Description |
|---|---|
| Checkbox | For bulk selection |
| Question | First 80 chars of question text + row actions (Edit, Delete, Restore) |
| Category | Category name (leaf category, parent derived) |
| Type | MCQ Single / MCQ Multiple / True-False |
| Difficulty | Easy (green) / Medium (yellow) / Hard (red) badge |
| # | Number of quizzes using this question (`usage_count`) |

### Row Actions

| Action | Available When | Description |
|---|---|---|
| Edit | Always | Inline edit or modal |
| Delete | Active | Soft delete (is_active = 0) |
| Restore | Inactive | Reactivate question |
| Delete Permanently | Inactive | Hard delete with confirmation |

---

## Page 2: Add / Edit Question

```
┌─────────────────────────────────────────────────────────────────┐
│  Add New Question                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Category: [▼ SSC CGL              ]  *required                  │
│  Type:     [▼ MCQ Single Answer    ]  *required                  │
│  Difficulty:[▼ Medium              ]                              │
│                                                                   │
│  Question Text: *required                                         │
│  ┌───────────────────────────────────────────────────────────┐   │
│  │ [Rich Text Editor - supports bold, italic, images]        │   │
│  │                                                           │   │
│  │ What is the capital of India?                             │   │
│  │                                                           │   │
│  └───────────────────────────────────────────────────────────┘   │
│                                                                   │
│  Answer Options:                                                  │
│  ┌───┬──────────────────────────────────┬────────────────┐       │
│  │ ✓ │ [A] New Delhi                    │ [Delete]        │       │
│  │ ☐ │ [B] Mumbai                       │ [Delete]        │       │
│  │ ☐ │ [C] Kolkata                      │ [Delete]        │       │
│  │ ☐ │ [D] Chennai                      │ [Delete]        │       │
│  └───┴──────────────────────────────────┴────────────────┘       │
│  [+ Add Option]   (min 2, max 6 options)                         │
│  ✓ = Correct answer (radio for single, checkbox for multi)       │
│                                                                   │
│  Explanation (shown after quiz submission):                       │
│  ┌───────────────────────────────────────────────────────────┐   │
│  │ New Delhi is the capital of India. It became the          │   │
│  │ capital in 1931 during British rule.                      │   │
│  └───────────────────────────────────────────────────────────┘   │
│                                                                   │
│  Marks: [1.00]    Negative Marks: [0.25]                         │
│                                                                   │
│  [Save Question]  [Save & Add Another]  [Cancel]                 │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Question Fields

| Field | Type | Required | Default | Description |
|---|---|---|---|---|
| `category_id` | dropdown | Yes | — | Leaf category (child preferred over parent) |
| `question_type` | dropdown | Yes | mcq_single | mcq_single / mcq_multiple / true_false |
| `difficulty` | dropdown | No | medium | easy / medium / hard |
| `question_text` | rich text | Yes | — | Question content (WP Editor) |
| `answers` | dynamic rows | Yes | 4 rows | 2–6 answer options |
| `explanation` | textarea | No | — | Shown after quiz submission |
| `marks` | number | No | 1.00 | Default marks for this question |
| `negative_marks` | number | No | 0.25 | Default negative marks |

### Answer Option Fields

| Field | Type | Description |
|---|---|---|
| `answer_text` | text | The answer option text |
| `is_correct` | radio/checkbox | Mark as correct (radio for single, checkbox for multiple) |

### Dynamic Behavior
- **Type = mcq_single**: Correct answer uses radio buttons (only 1 correct)
- **Type = mcq_multiple**: Correct answer uses checkboxes (1+ correct)
- **Type = true_false**: Auto-generates 2 options ("True" / "False"), hides add/delete
- **Add Option**: Appends new row (max 6)
- **Delete Option**: Removes row (min 2 must remain)

---

## Database Schema

### Questions

```sql
CREATE TABLE {prefix}gmcq_questions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     BIGINT UNSIGNED DEFAULT NULL,
    question_text   TEXT NOT NULL,
    question_hash   CHAR(32) NOT NULL COMMENT 'MD5 of normalized text',
    question_type   ENUM('mcq_single','mcq_multiple','true_false') DEFAULT 'mcq_single',
    explanation     TEXT DEFAULT NULL,
    difficulty      ENUM('easy','medium','hard') DEFAULT 'medium',
    marks           DECIMAL(5,2) DEFAULT 1.00,
    negative_marks  DECIMAL(5,2) DEFAULT 0.25,
    is_active       TINYINT(1) DEFAULT 1,
    usage_count     INT DEFAULT 0,
    import_id       BIGINT UNSIGNED DEFAULT NULL,
    created_by      BIGINT UNSIGNED DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME DEFAULT NULL,
    deleted_by      BIGINT UNSIGNED DEFAULT NULL,
    
    UNIQUE KEY idx_question_hash (question_hash),
    KEY idx_category_active (category_id, is_active),
    KEY idx_difficulty (difficulty),
    KEY idx_is_active (is_active),
    KEY idx_question_type (question_type),
    KEY idx_import (import_id),
    KEY idx_usage (usage_count),
    FULLTEXT idx_question_text (question_text)
);
```

### Answers

```sql
CREATE TABLE {prefix}gmcq_answers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id     BIGINT UNSIGNED NOT NULL,
    answer_text     TEXT NOT NULL,
    is_correct      TINYINT(1) DEFAULT 0,
    sort_order      INT DEFAULT 0,
    
    KEY idx_question (question_id),
    KEY idx_question_correct (question_id, is_correct)
);
```

---

## AJAX Endpoints

| Action | Method | Description |
|---|---|---|
| `gmcq_save_question` | POST | Create or update question + answers + generate hash |
| `gmcq_delete_question` | POST | Soft delete question (is_active = 0) |
| `gmcq_restore_question` | POST | Restore soft-deleted question |
| `gmcq_delete_question_permanently` | POST | Hard delete question + answers + remove from quizzes |
| `gmcq_bulk_questions` | POST | Bulk delete, change category, or restore |
| `gmcq_search_questions` | GET | Search/filter for list table with filter param support |
| `gmcq_get_question` | GET | Load single question for editing |
| `gmcq_batch_save_questions` | POST | Batch create questions (for CSV import) |

---

## Validation Rules

| Rule | Message |
|---|---|
| Question text required | "Question text cannot be empty" |
| Category required | "Please select a category" |
| Category must be active | "Cannot assign to inactive category" |
| Category should be leaf | "Consider selecting a specific subcategory instead of parent" |
| Min 2 options | "At least 2 answer options are required" |
| Max 6 options | "Maximum 6 answer options allowed" |
| At least 1 correct | "At least one answer must be marked correct" |
| Single = 1 correct | "Single answer type must have exactly 1 correct answer" |
| Answer text required | "All answer options must have text" |
| Marks ≥ 0 | "Marks cannot be negative" |
| Negative marks ≥ 0 | "Negative marks cannot be negative" |
| Question hash unique | "This question already exists (duplicate detected)" |

---

## Hooks and Automation

### Question Hash Generation

```php
/**
 * Generate question hash on save
 * Called automatically before insert/update
 * Normalizes text: strip_tags → remove punctuation → collapse spaces → trim → lowercase → md5
 */
function gmcq_generate_question_hash( string $question_text ): string {
    $text = strip_tags( $question_text );                      // Remove HTML
    $text = preg_replace( '/[^\w\s]/u', '', $text );           // Remove punctuation
    $text = preg_replace( '/\s+/', ' ', $text );               // Collapse multiple spaces
    $text = trim( $text );                                      // Trim whitespace
    $text = mb_strtolower( $text, 'UTF-8' );                   // Lowercase
    return md5( $text );
}

add_action('gmcq_before_save_question', function(&$data) {
    $data['question_hash'] = gmcq_generate_question_hash($data['question_text']);
});
```

### Usage Count Maintenance

```php
/**
 * Update usage_count when question added to quiz
 */
add_action('gmcq_question_added_to_quiz', function($question_id, $quiz_id) {
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gmcq_questions SET usage_count = usage_count + 1 WHERE id = %d",
        $question_id
    ));
});

/**
 * Update usage_count when question removed from quiz
 */
add_action('gmcq_question_removed_from_quiz', function($question_id, $quiz_id) {
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gmcq_questions SET usage_count = usage_count - 1 WHERE id = %d",
        $question_id
    ));
});
```

### Activity Logging (Deferred to Phase 2)

> Activity logging is a Phase 2 feature. In Phase 1, no activity logging hooks are implemented.
> The `gmcq_activity_log` table and `gmcq_log_activity()` function do not exist in Phase 1.
> See `docs/reviews/Phase2-Enhanced.md` for the Phase 2 implementation.

### Daily Recalculation (Cron)

```php
/**
 * Recalculate usage counts daily
 * Fixes drift from failed transactions or race conditions
 */
add_action('gmcq_daily_cron', 'gmcq_recalculate_usage_counts');

function gmcq_recalculate_usage_counts() {
    global $wpdb;
    $wpdb->query("
        UPDATE {$wpdb->prefix}gmcq_questions q
        SET usage_count = (
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}gmcq_question_map qm 
            WHERE qm.question_id = q.id
        )
        WHERE q.is_active = 1
    ");
}
```

---

## Soft Delete Workflow

### Delete (Soft)

```
1. Click Delete → Confirmation modal
2. Confirm → AJAX call to gmcq_delete_question
3. Backend:
   - UPDATE questions SET is_active = 0, deleted_at = NOW(), deleted_by = user_id
   - Clear dashboard cache (activity logging deferred to Phase 2)
4. UI: Row fades, moves to "Inactive" filter tab
```

### Restore

```
1. Click Restore (on inactive question)
2. Confirm → AJAX call to gmcq_restore_question
3. Backend:
   - UPDATE questions SET is_active = 1, deleted_at = NULL, deleted_by = NULL
   - Clear dashboard cache (activity logging deferred to Phase 2)
4. UI: Row moves back to "Active" filter tab
```

### Delete Permanently

```
1. Click Delete Permanently (on inactive question)
2. Confirmation: "This will permanently delete the question and remove it from all quizzes. This cannot be undone."
3. AJAX call to gmcq_delete_question_permanently
4. Backend:
   - DELETE FROM question_map WHERE question_id = ?
   - DELETE FROM answers WHERE question_id = ?
   - DELETE FROM questions WHERE id = ?
   - Recalculate usage_count for affected quizzes
   - Clear dashboard cache (activity logging deferred to Phase 2)
```

---

## Technical Notes

- **Transaction strategy:** Question save must use explicit DB transaction: `START TRANSACTION` → insert question → insert/update answers → generate hash → `COMMIT`. On any `$wpdb->last_error`, call `ROLLBACK`. This applies to all multi-table operations (save, delete, bulk, import). See Master Plan §10 Design Notes.
- `question_hash` generated automatically on save via hook
- Soft delete preserves data, allows restore
- Hard delete only available on inactive questions (safety)
- `usage_count` updated via hooks on question_map changes
- Daily cron recalculates usage_count to fix drift
- Filter tabs use `$_GET['filter']` parameter
- Activity logging deferred to Phase 2 (see `docs/reviews/Phase2-Enhanced.md`)
- Dashboard cache cleared on any question mutation
- List table uses `WP_List_Table` for native WordPress admin feel
- Pagination: 20 questions per page
- CSS class prefix: `gmcq-questions-`

---

*Version: 2.0 — Questions Management Final*