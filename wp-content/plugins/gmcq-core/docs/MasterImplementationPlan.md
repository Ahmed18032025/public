# GMCQ Quiz Engine — Master Implementation Plan

**Status:** Pre-Development  
**Version:** 2.0 (Phase 1 Scoped)  
**Execution Order:** Schema → CRUD → Hooks → UI → Settings

---

## PHASED ROADMAP

| Phase | Modules | Status |
|-------|---------|--------|
| **Phase 1 (MVP)** | Categories, Questions, Quizzes, Quiz Attempts, Reports, CSV Import, Settings, Dashboard | **Now** |
| **Phase 2** | Activity Logs, Advanced Filters, Duplicate Analysis, Category Merge, Import Resume, Archive System | Later |
| **Phase 3** | Roles & Permissions, API, Analytics, Multi-site Support | Future |

---

## TABLE OF CONTENTS

1. [Phase 1 Database Schema](#1-phase-1-database-schema)
2. [Category Ownership Model](#2-category-ownership-model)
3. [Question Hashing & Search](#3-question-hashing--search)
4. [Quiz Count Maintenance](#4-quiz-count-maintenance)
5. [Attempt System](#5-attempt-system)
6. [Reports & CSV Export](#6-reports--csv-export)
7. [CSV Import Flow](#7-csv-import-flow)
8. [Dashboard](#8-dashboard)
9. [Settings & Backup](#9-settings--backup)
10. [Plugin Bootstrap & Activation](#10-plugin-bootstrap--activation)
11. [Cron Jobs](#11-cron-jobs)
12. [Phase 1 Execution Checklist](#12-phase-1-execution-checklist)
13. [Phase 2 & 3 Feature Backlog](#13-phase-2--3-feature-backlog)

---

## 1. Phase 1 Database Schema

All tables in creation order (respects dependencies). Only 8 tables in Phase 1. Activity log, archive tables deferred to Phase 2.

### 1.1 `gmcq_categories`

**Phase 1:** Deactivate only (`is_active = 0`). No `deleted_at`/`deleted_by` soft delete columns. No merge capability.

```sql
CREATE TABLE {prefix}gmcq_categories (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id           BIGINT UNSIGNED DEFAULT NULL          COMMENT 'NULL = top-level category',
    name                VARCHAR(255) NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    description         TEXT DEFAULT NULL,
    question_count      INT DEFAULT 0                         COMMENT 'Direct questions; recalculated daily',
    sort_order          INT DEFAULT 0,
    is_active           TINYINT(1) DEFAULT 1,
    created_by          BIGINT UNSIGNED DEFAULT NULL          COMMENT 'WP user_id',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY idx_slug          (slug),
    KEY idx_parent_active        (parent_id, is_active),
    KEY idx_active_created       (is_active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**No sub_question_count column** — tree counts computed on read via cached query `gmcq_get_category_tree_counts()`.
**No deleted_at/deleted_by** — categories are deactivated (`is_active = 0`) rather than soft-deleted.

---

### 1.2 `gmcq_questions`

Soft delete supported (`deleted_at`, `deleted_by`) — historical attempt data must not break.

```sql
CREATE TABLE {prefix}gmcq_questions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     BIGINT UNSIGNED DEFAULT NULL              COMMENT 'Leaf category (source of truth)',
    question_text   TEXT NOT NULL,
    question_hash   CHAR(32) NOT NULL                        COMMENT 'MD5 of normalized text (strip_tags, remove punctuation, collapse spaces, lowercase)',
    question_type   ENUM('mcq_single','mcq_multiple','true_false') DEFAULT 'mcq_single',
    explanation     TEXT DEFAULT NULL,
    difficulty      ENUM('easy','medium','hard') DEFAULT 'medium',
    marks           DECIMAL(5,2) DEFAULT 1.00,
    negative_marks  DECIMAL(5,2) DEFAULT 0.25,
    is_active       TINYINT(1) DEFAULT 1,
    usage_count     INT DEFAULT 0                            COMMENT 'Number of quizzes using this question; updated via hooks + daily cron',
    import_id       BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Source import batch (FK gmcq_imports.id)',
    created_by      BIGINT UNSIGNED DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME DEFAULT NULL,
    deleted_by      BIGINT UNSIGNED DEFAULT NULL,

    UNIQUE KEY idx_question_hash  (question_hash),
    KEY idx_category_active       (category_id, is_active),
    KEY idx_difficulty            (difficulty),
    KEY idx_is_active             (is_active),
    KEY idx_question_type         (question_type),
    KEY idx_import                (import_id),
    KEY idx_usage                 (usage_count),
    FULLTEXT idx_question_text    (question_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Hash generation** (normalized to catch common variations):
```php
function gmcq_generate_question_hash( string $question_text ): string {
    $text = strip_tags( $question_text );                      // Remove HTML
    $text = preg_replace( '/[^\w\s]/u', '', $text );           // Remove punctuation
    $text = preg_replace( '/\s+/', ' ', $text );               // Collapse multiple spaces
    $text = trim( $text );                                      // Trim whitespace
    $text = mb_strtolower( $text, 'UTF-8' );                   // Lowercase
    return md5( $text );
}
```

---

### 1.3 `gmcq_answers`

```sql
CREATE TABLE {prefix}gmcq_answers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id     BIGINT UNSIGNED NOT NULL,
    answer_text     TEXT NOT NULL,
    is_correct      TINYINT(1) DEFAULT 0,
    sort_order      INT DEFAULT 0,

    KEY idx_question              (question_id),
    KEY idx_question_correct      (question_id, is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**FULLTEXT on answer_text deferred to Phase 2** (Advanced Search).

---

### 1.4 `gmcq_quizzes_meta`

**Phase 1:** No `avg_score`, no `total_marks` — calculated on read. No `category_auto` — quiz category is purely manual metadata.

```sql
CREATE TABLE {prefix}gmcq_quizzes_meta (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id                 BIGINT UNSIGNED NOT NULL              COMMENT 'FK to wp_posts.ID (CPT)',
    category_id             BIGINT UNSIGNED DEFAULT NULL          COMMENT 'Manual metadata — set by admin; no auto-derive',
    time_limit              INT DEFAULT 0                         COMMENT 'Minutes; 0=unlimited',
    pass_percentage         DECIMAL(5,2) DEFAULT 40.00,
    max_attempts            INT DEFAULT 0                         COMMENT '0=unlimited',
    shuffle_questions       TINYINT(1) DEFAULT 1,
    shuffle_answers         TINYINT(1) DEFAULT 1,
    show_explanations       TINYINT(1) DEFAULT 1,
    show_correct_answers    TINYINT(1) DEFAULT 1,
    require_login           TINYINT(1) DEFAULT 0,
    questions_per_page      INT DEFAULT 20,
    default_marks           DECIMAL(5,2) DEFAULT 1.00,
    default_negative_marks  DECIMAL(5,2) DEFAULT 0.25,
    status                  ENUM('draft','published') DEFAULT 'draft',
    is_active               TINYINT(1) DEFAULT 1,
    question_count          INT DEFAULT 0                         COMMENT 'Denormalized; updated via hook + daily cron',
    attempt_count           INT DEFAULT 0                         COMMENT 'Denormalized; updated via hook + daily cron',
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME DEFAULT NULL,
    deleted_by              BIGINT UNSIGNED DEFAULT NULL,

    UNIQUE KEY idx_quiz_id        (quiz_id),
    KEY idx_category              (category_id),
    KEY idx_status                (status),
    KEY idx_status_active         (status, is_active),
    KEY idx_question_count        (question_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**avg_score** — calculated on read via `SELECT AVG(percentage) FROM gmcq_attempts WHERE quiz_id = %d AND status = 'completed' AND is_active = 1`
**total_marks** — calculated on read via `SELECT COALESCE(SUM(COALESCE(qm.marks, zm.default_marks)), 0) FROM gmcq_question_map qm JOIN gmcq_quizzes_meta zm ON zm.quiz_id = qm.quiz_id WHERE qm.quiz_id = %d`

---

### 1.5 `gmcq_question_map`

```sql
CREATE TABLE {prefix}gmcq_question_map (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         BIGINT UNSIGNED NOT NULL,
    question_id     BIGINT UNSIGNED NOT NULL,
    sort_order      INT DEFAULT 0,
    marks           DECIMAL(5,2) DEFAULT NULL                 COMMENT 'Override; NULL=use quiz default_marks',
    negative_marks  DECIMAL(5,2) DEFAULT NULL                 COMMENT 'Override; NULL=use quiz default_negative_marks',

    UNIQUE KEY idx_quiz_question  (quiz_id, question_id),
    KEY idx_quiz_order            (quiz_id, sort_order),
    KEY idx_question              (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 1.6 `gmcq_attempts`

**Phase 1:** No `original_category_id` (merge deferred). `is_active = 1` always for legitimate attempts (never soft-deleted). Fraudulent attempts can be hidden via `is_active = 0`.

```sql
CREATE TABLE {prefix}gmcq_attempts (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id             BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED DEFAULT NULL           COMMENT 'NULL for guest attempts',
    category_id         BIGINT UNSIGNED DEFAULT NULL           COMMENT 'Denormalized from first question at attempt start',
    score               DECIMAL(8,2) DEFAULT 0,
    max_score           DECIMAL(8,2) DEFAULT 0,
    percentage          DECIMAL(5,2) DEFAULT 0,
    total_questions     INT DEFAULT 0,
    correct_answers     INT DEFAULT 0,
    wrong_answers       INT DEFAULT 0,
    skipped_questions   INT DEFAULT 0,
    time_taken          INT DEFAULT 0                          COMMENT 'Seconds',
    status              ENUM('in_progress','completed') DEFAULT 'in_progress',
    passed              TINYINT(1) DEFAULT NULL,
    is_active           TINYINT(1) DEFAULT 1                  COMMENT '1=visible; 0=fraudulent/archived',
    ip_address          VARCHAR(45) DEFAULT NULL               COMMENT 'For spam detection (supports IPv6)',
    session_id          VARCHAR(64) DEFAULT NULL               COMMENT 'For guest tracking',
    started_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at        DATETIME DEFAULT NULL,

    KEY idx_quiz_id               (quiz_id),
    KEY idx_user_id               (user_id),
    KEY idx_status                (status),
    KEY idx_started_at            (started_at),
    KEY idx_quiz_user             (quiz_id, user_id),
    KEY idx_category_started      (category_id, started_at),
    KEY idx_quiz_status_date      (quiz_id, status, started_at),
    KEY idx_user_date             (user_id, started_at),
    KEY idx_ip_quiz_date          (ip_address, quiz_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**No quiz_title column** — retrieved via JOIN to `wp_posts.ID` on read.
**No original_category_id** — merge deferred to Phase 2.

---

### 1.7 `gmcq_attempt_answers`

```sql
CREATE TABLE {prefix}gmcq_attempt_answers (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id          BIGINT UNSIGNED NOT NULL,
    question_id         BIGINT UNSIGNED NOT NULL,
    selected_answer_id  BIGINT UNSIGNED DEFAULT NULL           COMMENT 'NULL if skipped',
    is_correct          TINYINT(1) DEFAULT 0,
    marks_obtained      DECIMAL(5,2) DEFAULT 0,
    time_spent          INT DEFAULT 0                          COMMENT 'Seconds on this question',

    KEY idx_attempt               (attempt_id),
    KEY idx_question              (question_id),
    KEY idx_attempt_question      (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.8 `gmcq_imports`

**Phase 1:** No `processed_rows`, no `temp_file_path` (resume deferred to Phase 2). Single-pass import only.

```sql
CREATE TABLE {prefix}gmcq_imports (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename            VARCHAR(255) NOT NULL,
    total_rows          INT DEFAULT 0,
    imported            INT DEFAULT 0,
    skipped_dupes       INT DEFAULT 0,
    skipped_errors      INT DEFAULT 0,
    status              ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    target_category_id  BIGINT UNSIGNED DEFAULT NULL,
    target_quiz_id      BIGINT UNSIGNED DEFAULT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    error_log           JSON DEFAULT NULL,
    started_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at        DATETIME DEFAULT NULL,

    KEY idx_status                (status),
    KEY idx_user                  (user_id),
    KEY idx_started               (started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Phase 2 Schema Additions (not created in Phase 1)

```sql
-- gmcq_activity_log — Added in Phase 2
-- gmcq_attempt_answers_archive — Added in Phase 2 (if archive system implemented)
-- gmcq_quizzes_meta: ALTER to add avg_score, total_marks — if denormalization needed
-- gmcq_imports: ALTER to add processed_rows, temp_file_path — for resume
```

---

## 2. Category Ownership Model

- **Questions own category_id** (source of truth)
- **Quiz category_id is purely manual metadata** — set by admin. No auto-derive logic.
- Subcategories are categories with `parent_id` set. No `subcategory_id` column needed.
- Questions always reference the **leaf** category. Parent derived via JOIN.
- Category deactivation (`is_active = 0`) does not cascade to questions — question `category_id` is preserved.
- Tree counts (parent + children) computed on read via `gmcq_get_category_tree_counts()`:

```php
function gmcq_get_category_tree_counts(): array {
    $cache_key = 'gmcq_category_tree_counts';
    $counts    = get_transient( $cache_key );
    if ( false !== $counts ) { return $counts; }

    global $wpdb;
    $p = $wpdb->prefix;
    $rows = $wpdb->get_results( "
        SELECT c.id, c.question_count AS direct_count,
               COALESCE(SUM(child.question_count), 0) AS sub_count,
               c.question_count + COALESCE(SUM(child.question_count), 0) AS total_count
        FROM {$p}gmcq_categories c
        LEFT JOIN {$p}gmcq_categories child ON child.parent_id = c.id AND child.is_active = 1
        WHERE c.is_active = 1
        GROUP BY c.id, c.question_count
    ", OBJECT_K );
    set_transient( $cache_key, $rows, 300 );
    return $rows ?: [];
}
```

---

## 3. Question Hashing & Search

### Hash Generation (Phase 1)

```php
function gmcq_generate_question_hash( string $question_text ): string {
    $text = strip_tags( $question_text );
    $text = preg_replace( '/[^\w\s]/u', '', $text );   // Remove punctuation
    $text = preg_replace( '/\s+/', ' ', $text );        // Collapse spaces
    $text = trim( $text );
    $text = mb_strtolower( $text, 'UTF-8' );
    return md5( $text );
}

// Hook into save:
add_action( 'gmcq_before_save_question', function ( array &$data ): void {
    $data['question_hash'] = gmcq_generate_question_hash( $data['question_text'] );
} );
```

### Search (Phase 1)

Phase 1 search: FULLTEXT on `question_text` + dropdown filters (category, difficulty, type).

```php
function gmcq_search_questions(
    string $query, array $filters = [], int $page = 1, int $per_page = 20
): array {
    global $wpdb;
    $p = $wpdb->prefix;

    // Safeguards: min query length (3), cap per_page (100), caching (5 min TTL)
    $min_length = (int) gmcq_get_setting( 'search_min_query_length', 3 );
    if ( mb_strlen( trim( $query ) ) < $min_length ) {
        return [ 'results' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page,
                 'error' => "Search query must be at least {$min_length} characters." ];
    }

    $per_page = min( $per_page, gmcq_get_setting( 'search_max_per_page', 100 ) );
    $cache_ttl = (int) gmcq_get_setting( 'search_cache_ttl', 300 );
    $cache_key = 'gmcq_search_' . md5( $query . serialize( $filters ) . $page . $per_page );
    $cached = get_transient( $cache_key );
    if ( false !== $cached ) { return $cached; }

    $where = "q.is_active = 1 AND q.deleted_at IS NULL";
    $join  = '';
    $query_escaped = $wpdb->esc_like( $query );
    $where .= $wpdb->prepare( " AND MATCH(q.question_text) AGAINST(%s IN BOOLEAN MODE)", '+' . $query_escaped . '*' );

    // Dropdown filters
    if ( ! empty( $filters['category_id'] ) ) {
        $where .= $wpdb->prepare( " AND q.category_id = %d", (int) $filters['category_id'] );
    }
    if ( ! empty( $filters['question_type'] ) ) {
        $where .= $wpdb->prepare( " AND q.question_type = %s", $filters['question_type'] );
    }
    if ( ! empty( $filters['difficulty'] ) ) {
        $where .= $wpdb->prepare( " AND q.difficulty = %s", $filters['difficulty'] );
    }
    if ( ! empty( $filters['import_id'] ) ) {
        $where .= $wpdb->prepare( " AND q.import_id = %d", (int) $filters['import_id'] );
    }

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions q {$join} WHERE {$where}" );
    $offset = max( 0, ( $page - 1 ) * $per_page );
    $results = $wpdb->get_results( $wpdb->prepare( "
        SELECT q.id, q.question_text, q.question_type, q.difficulty,
               q.marks, q.usage_count, q.created_at, c.name AS category_name
        FROM {$p}gmcq_questions q {$join}
        LEFT JOIN {$p}gmcq_categories c ON c.id = q.category_id
        WHERE {$where}
        ORDER BY q.usage_count DESC, q.id DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset ) );

    $result = [ 'results' => $results, 'total' => $total, 'page' => $page, 'per_page' => $per_page ];
    set_transient( $cache_key, $result, $cache_ttl );
    return $result;
}
```

**Deferred to Phase 2:** Search by answer text, text similarity duplicate analysis.

---

## 4. Quiz Count Maintenance

**Phase 1 denormalized columns:** `question_count`, `attempt_count`
**Calculated on read:** `total_marks`, `avg_score`

### Hook: Questions Changed

```php
add_action( 'gmcq_quiz_questions_changed', function ( int $quiz_id ): void {
    global $wpdb;
    $stats = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_question_map WHERE quiz_id = %d", $quiz_id
    ) );
    $wpdb->update(
        "{$wpdb->prefix}gmcq_quizzes_meta",
        [ 'question_count' => (int) $stats ],
        [ 'quiz_id' => $quiz_id ],
        [ '%d' ], [ '%d' ]
    );
} );
```

### Hook: Attempt Completed

```php
add_action( 'gmcq_attempt_completed', function ( int $quiz_id ): void {
    global $wpdb;
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->prefix}gmcq_quizzes_meta
         SET attempt_count = attempt_count + 1
         WHERE quiz_id = %d", $quiz_id
    ) );
} );

// Also ensure the `passed` flag is set for completed attempts based on quiz pass_percentage
$wpdb->query( $wpdb->prepare( 
    "UPDATE {$wpdb->prefix}gmcq_attempts a
     JOIN {$wpdb->prefix}gmcq_quizzes_meta z ON z.quiz_id = a.quiz_id
     SET a.passed = (CASE WHEN a.percentage >= z.pass_percentage THEN 1 ELSE 0 END)
     WHERE a.quiz_id = %d AND a.status = 'completed'", $quiz_id
 ) );
```

### Usage Count Maintenance

```php
add_action( 'gmcq_question_added_to_quiz', function ( int $question_id ): void {
    global $wpdb;
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->prefix}gmcq_questions SET usage_count = usage_count + 1 WHERE id = %d", $question_id
    ) );
} );

add_action( 'gmcq_question_removed_from_quiz', function ( int $question_id ): void {
    global $wpdb;
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->prefix}gmcq_questions SET usage_count = GREATEST(0, usage_count - 1) WHERE id = %d", $question_id
    ) );
} );
```

### Computed Values on Read (not denormalized)

```php
// When viewing quiz edit page:
function gmcq_get_quiz_total_marks( int $quiz_id ): float {
    global $wpdb;
    return (float) $wpdb->get_var( $wpdb->prepare( "
        SELECT COALESCE(SUM(COALESCE(qm.marks, zm.default_marks)), 0)
        FROM {$wpdb->prefix}gmcq_question_map qm
        JOIN {$wpdb->prefix}gmcq_quizzes_meta zm ON zm.quiz_id = qm.quiz_id
        WHERE qm.quiz_id = %d
    ", $quiz_id ) );
}

function gmcq_get_quiz_avg_score( int $quiz_id ): ?float {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare( "
        SELECT AVG(percentage) FROM {$wpdb->prefix}gmcq_attempts
        WHERE quiz_id = %d AND status = 'completed' AND is_active = 1
    ", $quiz_id ) );
}
```

---

## 5. Attempt System

### Start Attempt

```php
add_action( 'gmcq_attempt_started', function ( int $attempt_id, int $quiz_id ): void {
    global $wpdb;

    // Get category from first question (source of truth)
    $category_id = (int) $wpdb->get_var( $wpdb->prepare( "
        SELECT q.category_id FROM {$wpdb->prefix}gmcq_question_map qm
        JOIN {$wpdb->prefix}gmcq_questions q ON q.id = qm.question_id
        WHERE qm.quiz_id = %d AND q.category_id IS NOT NULL
        ORDER BY qm.sort_order ASC LIMIT 1
    ", $quiz_id ) );

    $wpdb->update(
        "{$wpdb->prefix}gmcq_attempts",
        [
            'category_id' => $category_id ?: null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'session_id'  => session_id() ?: md5( uniqid( '', true ) ),
        ],
        [ 'id' => $attempt_id ],
        [ '%d', '%s', '%s' ], [ '%d' ]
    );
}, 10, 2 );

### Resume Logic
| State | attempt_answers row | Behavior |
|-------|--------------------|----------|
| Not visited | No row exists | Resume should start at the first question with no `gmcq_attempt_answers` row (first NOT VISITED) |
| Skipped | EXISTS, selected_answer_id = NULL | Show as skipped; do NOT resume here |
| Answered | EXISTS, selected_answer_id = value | Show as answered (read-only) |

```

### Quiz Title on Read (via JOIN — not denormalized)

```php
function gmcq_get_attempt_quiz_title( int $quiz_id ): string {
    $cache_key = 'gmcq_quiz_title_' . $quiz_id;
    $title     = get_transient( $cache_key );
    if ( false !== $title ) { return $title; }

    $title = get_the_title( $quiz_id ) ?: 'Quiz #' . $quiz_id;
    set_transient( $cache_key, $title, 300 ); // 5 min cache
    return $title;
}
```

### Rate Limiting

```php
function gmcq_check_attempt_rate_limit( int $quiz_id ): bool|WP_Error {
    $ip          = $_SERVER['REMOTE_ADDR'] ?? '';
    $max_per_day = gmcq_get_setting( 'max_attempts_per_ip_per_day', 50 );
    if ( $max_per_day === 0 ) { return true; }

    global $wpdb;
    $count = (int) $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_attempts
        WHERE ip_address = %s AND quiz_id = %d
        AND DATE(started_at) = CURDATE()
    ", $ip, $quiz_id ) );

    if ( $count >= $max_per_day ) {
        return new WP_Error( 'rate_limited', 'Too many attempts. Please try again tomorrow.' );
    }
    return true;
}
```

---

## 6. Reports & CSV Export

**Phase 1:** Simple listing with pagination + chunked CSV export. No archive/compression.

### Summary Cards (cached per filter)

```php
function gmcq_get_reports_summary( array $filters ): array {
    $cache_key = 'gmcq_reports_summary_' . md5( serialize( $filters ) );
    $summary   = get_transient( $cache_key );
    if ( false !== $summary ) { return $summary; }

    global $wpdb;
    $where = gmcq_build_attempts_where( $filters );
    $summary = $wpdb->get_row( "
        SELECT COUNT(*) AS total_attempts,
               COALESCE(AVG(percentage), 0) AS avg_score,
               COALESCE(SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 0) AS pass_rate,
               COALESCE(AVG(time_taken), 0) AS avg_time
        FROM {$wpdb->prefix}gmcq_attempts
        WHERE status = 'completed' AND is_active = 1 {$where}
    ", ARRAY_A );

    $cache_ttl = gmcq_get_setting( 'reports_cache_ttl', 300 );
    set_transient( $cache_key, $summary, $cache_ttl );
    return $summary ?: [];
}
```

### Chunked CSV Export (Phase 1 — keep)

```php
function gmcq_export_attempts( array $filters ): void {
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=attempts-' . date( 'Y-m-d' ) . '.csv' );

    $output = fopen( 'php://output', 'w' );
    fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM
    fputcsv( $output, [ 'User', 'Email', 'Quiz', 'Category', 'Score', 'Max Score',
        'Percentage', 'Pass/Fail', 'Correct', 'Wrong', 'Skipped', 'Time Taken (s)', 'Date' ] );

    $offset = 0; $chunk_size = 1000;
    while ( true ) {
        $rows = gmcq_get_attempts_chunk( $filters, $offset, $chunk_size );
        if ( empty( $rows ) ) { break; }
        foreach ( $rows as $row ) {
            $quiz_title = gmcq_get_attempt_quiz_title( (int) $row->quiz_id );
            fputcsv( $output, [ $row->user_name, $row->user_email, $quiz_title,
                $row->category_name, $row->score, $row->max_score, $row->percentage . '%',
                $row->passed ? 'Pass' : 'Fail', $row->correct_answers, $row->wrong_answers,
                $row->skipped_questions, $row->time_taken, $row->started_at ] );
        }
        $offset += $chunk_size;
        if ( ob_get_level() ) { ob_flush(); } flush();
    }
    fclose( $output );
    exit;
}
```

**Deferred to Phase 2:** Attempt archival, answer compression, `gmcq_attempt_answers_archive` table.

---

## 7. CSV Import Flow

**Phase 1:** Single-pass import (no resume). `processed_rows` and `temp_file_path` not stored.

### Flow
1. Upload CSV → validate → preview
2. For each batch of N questions:
   - Generate hash via `gmcq_generate_question_hash()` (with punctuation+space normalization)
   - Check hash against `gmcq_questions.question_hash` (unique index)
   - Skip duplicates, insert new
   - Wrap in DB transaction
3. After import: recalculate `question_count` for affected categories
4. If assigned to quiz: update `question_count` via `gmcq_quiz_questions_changed`

### Backup Before Import

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

    // Index in wp_options
    $backups = get_option( 'gmcq_backup_index', [] );
    $backups[] = [ 'id' => uniqid(), 'type' => 'pre_import', 'file' => $filename,
                   'created' => current_time( 'mysql' ), 'import_id' => $import_id ];
    update_option( 'gmcq_backup_index', $backups );
}
```

### Post-Import Recalculation

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

## 8. Dashboard

**Phase 1:** No activity log section. Stats, health, integrity, top quizzes, recent quizzes, import summary.

### Stat Cards (cached, 5-min TTL)

```php
function gmcq_get_dashboard_stats(): array {
    $stats = get_transient( 'gmcq_dashboard_stats' );
    if ( false !== $stats ) { return $stats; }

    // Stampede protection
    if ( get_transient( 'gmcq_dashboard_stats_lock' ) ) {
        return [ '_rebuilding' => true ];
    }
    set_transient( 'gmcq_dashboard_stats_lock', true, 30 );

    global $wpdb; $p = $wpdb->prefix;
    $cache_ttl = gmcq_get_setting( 'dashboard_cache_ttl', 300 );

    $stats = [
        'top_level_categories' => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NULL AND is_active = 1" ),
        'child_categories'     => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NOT NULL AND is_active = 1" ),
        'published_quizzes'    => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta WHERE status = 'published' AND is_active = 1" ),
        'active_questions'     => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE is_active = 1" ),
        'total_attempts'       => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_attempts WHERE status = 'completed' AND is_active = 1" ),
        'last_updated'         => current_time( 'mysql' ),
    ];
    set_transient( 'gmcq_dashboard_stats', $stats, $cache_ttl );
    delete_transient( 'gmcq_dashboard_stats_lock' );
    return $stats;
}
```

### Data Integrity (cached, 15-min TTL)

```php
function gmcq_get_data_integrity(): array {
    $cache_ttl = gmcq_get_setting( 'integrity_cache_ttl', 900 );
    $integrity = get_transient( 'gmcq_data_integrity' );
    if ( false !== $integrity ) { return $integrity; }

    global $wpdb; $p = $wpdb->prefix;
    $integrity = [
        'unassigned_questions'          => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE usage_count = 0 AND is_active = 1" ),
        'questions_in_archived_quizzes' => (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT qm.question_id) FROM {$p}gmcq_question_map qm
            JOIN {$p}gmcq_quizzes_meta zm ON zm.quiz_id = qm.quiz_id WHERE zm.is_active = 0" ),
        'duplicate_questions'           => 0, // Always 0 in Phase 1 due to UNIQUE idx_question_hash constraint. Reserved for DB corruption detection. Phase 2 will introduce text-similarity-based detection.
        'potential_duplicates'          => null, // Not in V1 — requires text similarity
        'categories_no_children'        => (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$p}gmcq_categories c1 WHERE parent_id IS NULL AND is_active = 1
            AND NOT EXISTS (SELECT 1 FROM {$p}gmcq_categories c2 WHERE c2.parent_id = c1.id AND c2.is_active = 1)" ),
        'subcategories_no_questions'    => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NOT NULL AND is_active = 1 AND question_count = 0" ),
        'quizzes_no_questions'          => (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta WHERE status = 'published' AND is_active = 1 AND question_count = 0" ),
        'questions_in_inactive_categories' => (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$p}gmcq_questions q JOIN {$p}gmcq_categories c ON c.id = q.category_id
            WHERE q.is_active = 1 AND c.is_active = 0" ),
    ];
    set_transient( 'gmcq_data_integrity', $integrity, $cache_ttl );
    return $integrity;
}
```

### Filter URL Map (Phase 1)

| Dashboard Alert | Filter URL |
|---|---|
| Questions without category | `?page=gmcq-questions&filter=no_category` |
| Questions not in any quiz | `?page=gmcq-questions&filter=unassigned` |
| Draft quizzes | `?page=gmcq-quizzes&filter=draft` |
| Archived quizzes | `?page=gmcq-quizzes&filter=archived` |
| Failed imports | `?page=gmcq-import&filter=failed` |
| Inactive categories | `?page=gmcq-categories&filter=inactive` |
| Duplicate questions | `?page=gmcq-questions&filter=duplicates` — always 0 in Phase 1 due to UNIQUE hash constraint; retained for DB corruption detection |
| Potential duplicates | Not available in V1 — requires text similarity (Phase 2) |
| Categories no children | `?page=gmcq-categories&filter=no_children` |
| Subcategories no questions | `?page=gmcq-categories&filter=no_questions` |
| Quizzes no questions | `?page=gmcq-quizzes&filter=no_questions` |
| Questions in inactive categories | `?page=gmcq-questions&filter=inactive_category` |
| Questions in archived quizzes | `?page=gmcq-questions&filter=archived_quiz` |

### Cache Invalidation (selective)

```php
function gmcq_clear_dashboard_cache( string $entity_type = 'all' ): void {
    switch ( $entity_type ) {
        case 'category':
            delete_transient( 'gmcq_dashboard_stats' );
            delete_transient( 'gmcq_system_health' );
            delete_transient( 'gmcq_data_integrity' );
            delete_transient( 'gmcq_category_stats' );
            delete_transient( 'gmcq_category_tree_counts' );
            break;
        case 'question':
            delete_transient( 'gmcq_dashboard_stats' );
            delete_transient( 'gmcq_system_health' );
            delete_transient( 'gmcq_data_integrity' );
            delete_transient( 'gmcq_duplicate_count' );
            break;
        case 'quiz':
            delete_transient( 'gmcq_dashboard_stats' );
            delete_transient( 'gmcq_system_health' );
            delete_transient( 'gmcq_top_quizzes' );
            delete_transient( 'gmcq_recent_quizzes' );
            break;
        case 'import':
            delete_transient( 'gmcq_dashboard_stats' );
            delete_transient( 'gmcq_system_health' );
            break;
        default:
            delete_transient( 'gmcq_dashboard_stats' );
            delete_transient( 'gmcq_system_health' );
            delete_transient( 'gmcq_data_integrity' );
            delete_transient( 'gmcq_category_stats' );
            delete_transient( 'gmcq_category_tree_counts' );
            delete_transient( 'gmcq_duplicate_count' );
            delete_transient( 'gmcq_top_quizzes' );
            delete_transient( 'gmcq_recent_quizzes' );
            break;
    }
}
```

---

## 9. Settings & Backup

### Phase 1 Settings

```php
'activity_retention_days'     => 90,    // Reserved for Phase 2
'attempt_retention_days'      => 365,   // Reserved for Phase 2
'enable_auto_purge'           => 0,     // Reserved for Phase 2
'dashboard_cache_ttl'         => 300,   // 5 min
'health_cache_ttl'            => 600,   // 10 min
'integrity_cache_ttl'         => 900,   // 15 min
'reports_cache_ttl'           => 300,   // 5 min
'max_questions_per_quiz'      => 200,
'max_attempts_per_ip_per_day' => 50,
'search_min_query_length'     => 3,
'search_cache_ttl'            => 300,
'search_max_per_page'         => 100,
'backup_enabled'              => 1,     // Enable auto-backup
'backup_retention_days'       => 90,    // Auto-clean old backups
'max_backup_files'            => 50,    // Hard cap
'quiz_slug'                   => 'quiz',
'uninstall_behavior'          => 'keep',
'enable_question_tags'        => 0,     // Reserved for V2
```

### Backup System

**Backup events:** Before CSV import, before bulk delete/restore.
**Storage:** JSON files in `wp-content/uploads/gmcq-backups/`. Index in `wp_options 'gmcq_backup_index'`.
**Scope per event:**
- Pre-import: snapshot of existing questions + answers (all rows)
- Pre-bulk-delete: snapshot of the records being deleted
- Export All Data: full export of all tables

**Backup Management UI:** Settings page section showing backup history, download links, cleanup button.

```php
function gmcq_create_backup( string $type, string $entity_type = '', array $ids = [] ): string {
    $backup_dir = wp_upload_dir()['basedir'] . '/gmcq-backups';
    if ( ! file_exists( $backup_dir ) ) { wp_mkdir_p( $backup_dir ); }

    global $wpdb; $p = $wpdb->prefix;
    $data = [ 'type' => $type, 'entity' => $entity_type, 'timestamp' => current_time( 'mysql' ) ];

    switch ( $type . '_' . $entity_type ) {
        case 'pre_import_':
            $data['questions'] = $wpdb->get_results( "SELECT * FROM {$p}gmcq_questions" );
            $data['answers']   = $wpdb->get_results( "SELECT * FROM {$p}gmcq_answers" );
            break;
        case 'pre_bulk_question':
            $id_list = implode( ',', array_map( 'intval', $ids ) );
            $data['questions'] = $wpdb->get_results( "SELECT * FROM {$p}gmcq_questions WHERE id IN ({$id_list})" );
            $data['answers']   = $wpdb->get_results( "SELECT * FROM {$p}gmcq_answers WHERE question_id IN ({$id_list})" );
            break;
        case 'pre_bulk_quiz':
            $id_list = implode( ',', array_map( 'intval', $ids ) );
            $data['quizzes']  = $wpdb->get_results( "SELECT * FROM {$p}gmcq_quizzes_meta WHERE quiz_id IN ({$id_list})" );
            $data['map']      = $wpdb->get_results( "SELECT * FROM {$p}gmcq_question_map WHERE quiz_id IN ({$id_list})" );
            break;
    }

    $filename = 'gmcq-backup-' . $type . '-' . $entity_type . '-' . date( 'Y-m-d-His' ) . '.json';
    file_put_contents( $backup_dir . '/' . $filename, wp_json_encode( $data, JSON_PRETTY_PRINT ) );

    $backups = get_option( 'gmcq_backup_index', [] );
    $backups[] = [ 'id' => uniqid(), 'type' => $type, 'file' => $filename,
                   'created' => current_time( 'mysql' ), 'count' => count( $ids ) ];
    update_option( 'gmcq_backup_index', $backups );

    return $filename;
}
```

---

## 10. Plugin Bootstrap & Activation

### Design Notes

**Foreign Keys:** No Phase 1 tables define foreign key constraints. This is intentional and follows standard WordPress plugin conventions:
- WordPress core does not use FK constraints across the `wp_posts` ↔ plugin tables boundary
- FK constraints complicate plugin deactivation/uninstall workflows
- MySQL/InnoDB FK constraint mismatches can cause silent table creation failures during `dbDelta()`
- Consistency is enforced at the application layer via prepared statements and referential validation in CRUD functions

**Transaction Strategy:** All operations modifying more than one table should use explicit transactions:
- `$wpdb->query( 'START TRANSACTION' )` before multi-table writes
- `$wpdb->query( 'COMMIT' )` on success
- `$wpdb->query( 'ROLLBACK' )` on `$wpdb->last_error`

Operations requiring transactions: save question (question + answers), delete question (question_map + answers + question), import batch (questions + answers + quiz_map), bulk category operations (questions + counts). See `docs/reviews/Future-Improvements.md` §Transaction Strategy for details.

```php
<?php
/**
 * Plugin Name: GMCQ Quiz Engine
 * Description: MCQ Quiz management system for WordPress
 * Version:     1.0.0
 * Author:      GMCQ Team
 * Text Domain: gmcq
 */

defined( 'ABSPATH' ) || exit;

define( 'GMCQ_VERSION',    '1.0.0' );
define( 'GMCQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GMCQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GMCQ_DB_VERSION', '1' );

function gmcq_activate(): void {
    gmcq_create_tables();
    gmcq_schedule_cron_jobs();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'gmcq_activate' );

function gmcq_deactivate(): void {
    wp_clear_scheduled_hook( 'gmcq_daily_cron' );
    wp_clear_scheduled_hook( 'gmcq_weekly_cron' );
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gmcq_deactivate' );

function gmcq_uninstall(): void {
    if ( gmcq_get_setting( 'uninstall_behavior', 'keep' ) === 'delete' ) {
        gmcq_drop_tables();
    }
}
register_uninstall_hook( __FILE__, 'gmcq_uninstall' );

function gmcq_create_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    $p = $wpdb->prefix;

    $sqls = [
        gmcq_sql_categories( $p, $charset_collate ),
        gmcq_sql_questions( $p, $charset_collate ),
        gmcq_sql_answers( $p, $charset_collate ),
        gmcq_sql_quizzes_meta( $p, $charset_collate ),
        gmcq_sql_question_map( $p, $charset_collate ),
        gmcq_sql_attempts( $p, $charset_collate ),
        gmcq_sql_attempt_answers( $p, $charset_collate ),
        gmcq_sql_imports( $p, $charset_collate ),
    ];

    foreach ( $sqls as $sql ) { dbDelta( $sql ); }
    update_option( 'gmcq_db_version', GMCQ_DB_VERSION );
}

function gmcq_drop_tables(): void {
    global $wpdb; $p = $wpdb->prefix;
    $tables = [ 'gmcq_attempt_answers', 'gmcq_attempts', 'gmcq_question_map',
        'gmcq_quizzes_meta', 'gmcq_answers', 'gmcq_questions', 'gmcq_imports', 'gmcq_categories' ];
    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$p}{$table}" );
    }
    delete_option( 'gmcq_settings' );
    delete_option( 'gmcq_db_version' );
    delete_option( 'gmcq_old_quiz_slug' );
    delete_option( 'gmcq_backup_index' );
}

function gmcq_get_setting( string $key, $default = null ) {
    static $settings = null;
    if ( $settings === null ) { $settings = get_option( 'gmcq_settings', [] ); }
    return $settings[ $key ] ?? $default;
}
```

---

## 11. Cron Jobs

### Daily Cron

- `gmcq_recalculate_category_counts()` — Fix drift in `question_count`
- `gmcq_recalculate_usage_counts()` — Fix drift in `usage_count`
- `gmcq_recalculate_quiz_stats()` — Fix drift in `question_count`, `attempt_count` (no `avg_score`/`total_marks` in Phase 1)

```php
function gmcq_recalculate_category_counts(): void {
    global $wpdb;
    $wpdb->query( "
        UPDATE {$wpdb->prefix}gmcq_categories c
        SET question_count = (
            SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_questions q
            WHERE q.category_id = c.id AND q.is_active = 1
        ) WHERE c.is_active = 1
    " );
    delete_transient( 'gmcq_category_stats' );
    delete_transient( 'gmcq_category_tree_counts' );
}

function gmcq_recalculate_usage_counts(): void {
    global $wpdb;
    $wpdb->query( "
        UPDATE {$wpdb->prefix}gmcq_questions q
        SET usage_count = (
            SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_question_map qm WHERE qm.question_id = q.id
        ) WHERE q.is_active = 1
    " );
}

function gmcq_recalculate_quiz_stats(): void {
    global $wpdb;
    // Recalculate question_count only (avg_score, total_marks calculated on read)
    $wpdb->query( "
        UPDATE {$wpdb->prefix}gmcq_quizzes_meta zm
        SET question_count = (
            SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_question_map qm WHERE qm.quiz_id = zm.quiz_id
        ),
        attempt_count = (
            SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_attempts a
            WHERE a.quiz_id = zm.quiz_id AND a.status = 'completed' AND a.is_active = 1
        )
        WHERE zm.is_active = 1
    " );
}
```

### Weekly Cron (Phase 1)

- `gmcq_cleanup_old_backups()` — Remove backup files older than retention setting

```php
function gmcq_cleanup_old_backups(): void {
    $days = (int) gmcq_get_setting( 'backup_retention_days', 90 );
    if ( $days === 0 ) { return; }

    $cutoff = strtotime( "-{$days} days" );
    $backup_dir = wp_upload_dir()['basedir'] . '/gmcq-backups';
    $backups = get_option( 'gmcq_backup_index', [] );
    $remaining = [];

    foreach ( $backups as $backup ) {
        $created = strtotime( $backup['created'] );
        if ( $created < $cutoff ) {
            $filepath = $backup_dir . '/' . $backup['file'];
            if ( file_exists( $filepath ) ) { unlink( $filepath ); }
        } else {
            $remaining[] = $backup;
        }
    }
    update_option( 'gmcq_backup_index', $remaining );
}
```

**Deferred to Phase 2:** `gmcq_purge_activity_log()`, `gmcq_archive_old_attempts()`.

---

## 12. Phase 1 Execution Checklist

### Phase 1.1 — Database (Blocking)

- [ ] **Step 1:** Create `gmcq_categories` table (no `deleted_at`/`deleted_by` — deactivate only)
- [ ] **Step 2:** Create `gmcq_imports` table (no `processed_rows`/`temp_file_path` — resume deferred)
- [ ] **Step 3:** Create `gmcq_questions` table (with `question_hash`, `import_id`, `deleted_at`/`deleted_by`)
- [ ] **Step 4:** Create `gmcq_answers` table
- [ ] **Step 5:** Create `gmcq_quizzes_meta` table (no `category_auto`, no `avg_score`, no `total_marks`)
- [ ] **Step 6:** Create `gmcq_question_map` table
- [ ] **Step 7:** Create `gmcq_attempts` table (no `original_category_id`, no `quiz_title`)
- [ ] **Step 8:** Create `gmcq_attempt_answers` table
- [ ] **Verify:** No `category_auto`, no `sub_question_count`, no `avg_score`, no `total_marks`

### Phase 1.2 — Core Functions

- [ ] **Step 9:** Implement `gmcq_generate_question_hash()` with punctuation removal + space collapsing
- [ ] **Step 10:** Implement `gmcq_get_setting()` helper
- [ ] **Step 11:** Implement `gmcq_get_category_tree_counts()` — computed tree counts
- [ ] **Step 12:** Implement `gmcq_search_questions()` with FULLTEXT + category/difficulty/type/import_id filters
- [ ] **Step 13:** Implement `gmcq_get_quiz_total_marks()` and `gmcq_get_quiz_avg_score()` — computed on read
- [ ] **Step 14:** Implement `gmcq_get_attempt_quiz_title()` — cached JOIN
- [ ] **Step 15:** Implement `gmcq_clear_dashboard_cache()` with selective invalidation
- [ ] **Step 16:** Implement `gmcq_create_backup()` — JSON file backup system
- [ ] **Step 17:** Implement `gmcq_validate_question_category()` — leaf category validation

### Phase 1.3 — Hooks

- [ ] **Step 18:** Implement `gmcq_question_added_to_quiz` / `gmcq_question_removed_from_quiz` hooks (usage_count)
- [ ] **Step 19:** Implement `gmcq_quiz_questions_changed` hook — update `question_count` only
- [ ] **Step 20:** Implement `gmcq_attempt_started` hook — populate `category_id`, `ip_address`
- [ ] **Step 21:** Implement `gmcq_attempt_completed` hook — update `attempt_count` only
- [ ] **Step 22:** Implement `gmcq_import_completed` hook — recalculate counts
- [ ] **Step 23:** Implement `gmcq_check_attempt_rate_limit()` — spam protection
- [ ] **Step 24:** Implement `gmcq_quiz_saved` hook — sync CPT post_status
- [ ] **Step 25:** Implement `gmcq_quiz_slug_changed` hook — old slug redirect

### Phase 1.4 — Filters

- [ ] **Step 26:** Implement question filters: `active`, `inactive`, `no_category`, `unassigned`, `duplicates`, `inactive_category`, `archived_quiz`
- [ ] **Step 27:** Implement quiz filters: `published`, `draft`, `archived`, `no_questions`
- [ ] **Step 28:** Implement category filters: `all`, `active`, `inactive`, `no_children`, `no_questions`

### Phase 1.5 — Cron Jobs

- [ ] **Step 29:** Register `weekly` cron schedule
- [ ] **Step 30:** Implement `gmcq_recalculate_category_counts()` — daily
- [ ] **Step 31:** Implement `gmcq_recalculate_usage_counts()` — daily
- [ ] **Step 32:** Implement `gmcq_recalculate_quiz_stats()` — daily (question_count + attempt_count only)
- [ ] **Step 33:** Implement `gmcq_cleanup_old_backups()` — weekly
- [ ] **Step 34:** Schedule cron jobs on activation

### Phase 1.6 — Dashboard

- [ ] **Step 35:** Implement `gmcq_get_dashboard_stats()` with stampede protection
- [ ] **Step 36:** Implement `gmcq_get_system_health()` — 6 checks
- [ ] **Step 37:** Implement `gmcq_get_data_integrity()` — 8 checks (potential_duplicates = null)
- [ ] **Step 38:** Implement `gmcq_get_top_quizzes()` — no avg_score in Phase 1
- [ ] **Step 39:** Implement `gmcq_get_recent_quizzes()` — 5-min cache
- [ ] **Step 40:** Implement `gmcq_get_last_import()` — from imports table
- [ ] **Step 41:** Dashboard UI — stat cards, health, integrity, top/recent quizzes, import summary

### Phase 1.7 — CRUD & UI

- [ ] **Step 42:** Category CRUD — create, update, deactivate/activate, list with tree
- [ ] **Step 43:** Question CRUD — create, update, soft delete, restore, hard delete
- [ ] **Step 44:** Quiz CRUD — create, update, soft delete, restore, manage questions
- [ ] **Step 45:** Attempt CRUD — start, submit answer, complete, list with pagination
- [ ] **Step 46:** CSV Import — upload, validate, preview, import, history
- [ ] **Step 47:** Reports — summary cards, filterable list, chunked CSV export
- [ ] **Step 48:** Settings — all sections + backup management UI

---

## 13. Phase 2 & 3 Feature Backlog

### Phase 2 (Next)

| Feature | Dependencies | Key Changes |
|---------|-------------|-------------|
| Activity Logs | None | Create `gmcq_activity_log` table. Hook all CRUD. Activity page UI. Dashboard recent activity section. |
| Advanced Search | FULLTEXT index on answers | Search by answer text. Import batch filter. Inactive question filter. |
| Duplicate Analysis | Hash dedup in place | Text similarity algorithm. "Potential duplicates" UI in dashboard + questions page. |
| Category Merge | Categories CRUD stable | Merge endpoint. Move questions + children. Soft-delete source. |
| Import Resume | Imports table | Add `processed_rows`, `temp_file_path` columns. Batch progress persistence. |
| Archive System | Attempts table | `gmcq_attempt_answers_archive` table. Weekly cron for archive + compress. |

### Phase 3 (Future)

| Feature | Description |
|---------|-------------|
| Roles & Permissions | Custom capabilities beyond `manage_gmcq`. Role-based access to reports, settings, imports. |
| API | REST/JSON API for external quiz taking, result submission, question bank access. |
| Analytics | Dashboard charts, trend analysis, category performance breakdowns. |
| Multi-site Support | Network-wide quiz management via WordPress Multisite. Shared question banks. |

---

## VALIDATION CHECKLIST (Checkpoint 0)

| Check | Expected | Status |
|-------|----------|--------|
| No `avg_score` column | Not in quizzes_meta schema | ✅ |
| No `total_marks` column | Not in quizzes_meta schema | ✅ |
| No `sub_question_count` column | Not in categories schema | ✅ |
| No `category_auto` column | Not in quizzes_meta schema | ✅ |
| No `quiz_title` column | Not in attempts schema | ✅ |
| No `activity_log` table | Not in Phase 1 schema | ✅ |
| No import resume columns | Not in imports schema | ✅ |
| No archive table | Not in Phase 1 schema | ✅ |
| Categories: no soft delete cols | Not in categories schema | ✅ |
| Attempts: no original_category_id | Not in attempts schema (merge deferred) | ✅ |
| Hash includes punctuation removal | In gmcq_generate_question_hash() | ✅ |
| Backup system defined | JSON files + index in wp_options | ✅ |
| Ownership rule: Questions own categories | Questions.category_id = source of truth | ✅ |
| Ownership rule: Quiz category = manual | Quizzes.category_id = manual metadata | ✅ |
| Phase 1 scope: 8 tables only | 8 data tables defined | ✅ |

---

*Master Implementation Plan — Version 2.0 (Phase 1 Scoped)*  
*Status: Architecture Freeze — Ready for Development*  
*Git Tag: `v0-architecture-freeze`*