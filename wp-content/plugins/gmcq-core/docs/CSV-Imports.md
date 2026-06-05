# CSV Import — GMCQ Quiz Engine v2 (Phase 1)

## Purpose
Bulk import questions and answers from CSV files into the question bank, with optional assignment to a quiz. Duplicates are detected and skipped via `question_hash`.

**Phase 1 Scope:** Single-pass import (no resume). No `processed_rows`/`temp_file_path`. Backup before import via JSON files.

---

## CSV Format

### Required Columns
| Column | Type | Description |
|---|---|---|
| `question_text` | text | The question content |
| `option_a` | text | First answer option |
| `option_b` | text | Second answer option |
| `correct_answer` | text | Correct option letter(s): `A`, `B`, `C`, `D`, or `A,C` for multiple |

### Optional Columns
| Column | Type | Default | Description |
|---|---|---|---|
| `option_c` | text | — | Third answer option |
| `option_d` | text | — | Fourth answer option |
| `explanation` | text | — | Explanation shown after quiz |
| `difficulty` | text | medium | easy / medium / hard |
| `marks` | number | 1.00 | Marks for correct answer |
| `negative_marks` | number | 0.25 | Marks deducted for wrong answer |
| `question_type` | text | mcq_single | mcq_single / mcq_multiple / true_false |
| `category_slug` | text | — | Override target category per row (supports "parent/child" format) |

---

## Import Flow (Phase 1)

### Step 1: Upload + Backup
1. User selects CSV file, target category, optional target quiz
2. **Pre-import backup** — auto-create JSON backup of existing questions + answers
3. Concurrent import protection: transient lock prevents multiple simultaneous imports

### Step 2: Preview & Validate
1. PHP parses CSV, validates every row
2. Check each row's `question_hash` against database
3. Shows summary: total rows, valid count, duplicate count, error count
4. Preview table shows first 5 rows with status

### Step 3: Import
1. Single-pass import (all batches in sequence)
2. Each batch: generate normalized hash → check duplicate → insert in transaction
3. If quiz selected: also insert into `gmcq_question_map`
4. Update import record with final status and counts

### Step 4: Complete
1. Show success summary
2. Recalculate category counts, usage counts, quiz counts
3. Links to: view questions, import another

**Deferred to Phase 2:** Progress persistence, resume capability, `processed_rows`/`temp_file_path` columns.

---

## Duplicate Detection

```php
// Hash normalization (catches "What is India?" vs "What is INDIA ?" etc.)
function gmcq_generate_question_hash( string $question_text ): string {
    $text = strip_tags( $question_text );
    $text = preg_replace( '/[^\w\s]/u', '', $text );   // Remove punctuation
    $text = preg_replace( '/\s+/', ' ', $text );        // Collapse spaces
    $text = trim( $text );
    $text = mb_strtolower( $text, 'UTF-8' );
    return md5( $text );
}
```

---

## Backup Before Import

```php
function gmcq_backup_before_import( int $import_id, array $import_data ): void {
    $backup_dir = wp_upload_dir()['basedir'] . '/gmcq-backups';
    if ( ! file_exists( $backup_dir ) ) { wp_mkdir_p( $backup_dir ); }

    $backup = [
        'questions' => $import_data,
        'timestamp' => current_time( 'mysql' ),
        'type'      => 'pre_import',
        'import_id' => $import_id,
    ];
    $filename = 'gmcq-backup-pre-import-' . $import_id . '-' . date( 'Y-m-d-His' ) . '.json';
    file_put_contents( $backup_dir . '/' . $filename, wp_json_encode( $backup, JSON_PRETTY_PRINT ) );

    $backups = get_option( 'gmcq_backup_index', [] );
    $backups[] = [ 'id' => uniqid(), 'type' => 'pre_import', 'file' => $filename,
                   'created' => current_time( 'mysql' ), 'import_id' => $import_id ];
    update_option( 'gmcq_backup_index', $backups );
}
```

---

## Database Schema (Phase 1)

```sql
CREATE TABLE {prefix}gmcq_imports (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename        VARCHAR(255) NOT NULL,
    total_rows      INT DEFAULT 0,
    imported        INT DEFAULT 0,
    skipped_dupes   INT DEFAULT 0,
    skipped_errors  INT DEFAULT 0,
    status          ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    target_category_id BIGINT UNSIGNED DEFAULT NULL,
    target_quiz_id  BIGINT UNSIGNED DEFAULT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    error_log       JSON DEFAULT NULL,
    started_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME DEFAULT NULL,

    KEY idx_status  (status),
    KEY idx_user    (user_id),
    KEY idx_started (started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Phase 1:** No `processed_rows`, no `temp_file_path` (resume deferred to Phase 2).

---

## Post-Import Recalculation (Phase 1)

```php
add_action( 'gmcq_import_completed', function ( int $import_id ): void {
    global $wpdb;
    $import = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gmcq_imports WHERE id = %d", $import_id
    ) );
    gmcq_recalculate_category_counts();
    gmcq_recalculate_usage_counts();
    if ( $import && $import->target_quiz_id ) {
        do_action( 'gmcq_quiz_questions_changed', (int) $import->target_quiz_id );
    }
} );
```

---

## Technical Notes (Phase 1)

- Single-pass import — no resume capability
- Pre-import JSON backup created automatically
- Duplicate detection via normalized MD5 hash
- Concurrent import protection via transient lock
- Post-import: recalculate category counts, usage counts, quiz counts
- No activity logging in Phase 1 (added in Phase 2)

---

*Version: 2.0 — CSV Import (Phase 1)*