# Quizzes Management — Version 2 Spec (Phase 1)

## Purpose
Create and manage quizzes, assign questions from the question bank, and configure quiz settings. Each quiz is stored as a WordPress Custom Post Type for SEO-friendly URLs.

**Phase 1 Scope:** CRUD, soft delete, question assignment. No `avg_score`/`total_marks` denormalization (calculated on read). No activity logging.

## Data Ownership
Quiz `category_id` is purely manual metadata — set by admin. Question `category_id` is the actual classification source of truth. No auto-derive logic.

---

## Database Schema (Phase 1)

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

**Phase 1 notes:**
- No `category_auto` — quiz category is purely manual metadata
- No `avg_score` — calculated on read via `SELECT AVG(percentage) FROM gmcq_attempts`
- No `total_marks` — calculated on read via `SELECT SUM(COALESCE(qm.marks, zm.default_marks))`
- Soft delete supported via `deleted_at`, `deleted_by`

---

## Count Maintenance (Phase 1)

Only `question_count` and `attempt_count` are denormalized. `total_marks` and `avg_score` are computed on read.

```php
// Hook: update question_count when questions change
add_action( 'gmcq_quiz_questions_changed', function ( int $quiz_id ): void {
    global $wpdb;
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_question_map WHERE quiz_id = %d", $quiz_id
    ) );
    $wpdb->update(
        "{$wpdb->prefix}gmcq_quizzes_meta",
        [ 'question_count' => $count ],
        [ 'quiz_id' => $quiz_id ],
        [ '%d' ], [ '%d' ]
    );
} );

// Hook: increment attempt_count on attempt completion
add_action( 'gmcq_attempt_completed', function ( int $quiz_id ): void {
    global $wpdb;
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->prefix}gmcq_quizzes_meta SET attempt_count = attempt_count + 1 WHERE quiz_id = %d", $quiz_id
    ) );
} );
```

### Computed Values on Read

```php
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

## UI Layout (Phase 1)

### Quiz List

```
Filter Tabs: [All] [Published] [Draft] [Archived] [No Questions]

┌───┬────────────────────┬──────────┬────┬──────┬────────┐
│ ☐ │ Title              │ Category │ Qs │ Att  │ Status │
├───┼────────────────────┼──────────┼────┼──────┼────────┤
│ ☐ │ SSC CGL Math Set 1 │ SSC CGL  │ 30 │ 1240 │Publish │
│   │ [Edit][Questions]  │          │    │      │        │
│   │ [Delete][View]     │          │    │      │        │
└───┴────────────────────┴──────────┴────┴──────┴────────┘
```

**Phase 1 columns:** Title, Category, Qs (question_count), Att (attempt_count), Status. No avg_score or total_marks columns.

### Quiz Edit Form

```
Category: [▼ SSC CGL    ] *required
(Manual — set by admin. Question category is the source of truth.)

Status:   [▼ Published  ]
Time Limit:      [30] minutes (0 = no limit)
Pass Percentage: [40] %
Max Attempts:   [0] (0 = unlimited)
Default Marks:  [1.00]
Default Neg Marks:[0.25]
☑ Shuffle Questions
☑ Shuffle Answers
☑ Show Explanations
☑ Show Correct Answers
☐ Require Login
Questions Per Page: [20]
```

---

## Soft Delete Workflow

```
Delete:  is_active=0, deleted_at=NOW(), sync wp_posts to draft
Restore: is_active=1, deleted_at=NULL, restore wp_posts status
```

---

## Technical Notes (Phase 1)

- No `category_auto` — quiz category is manual metadata only
- No `avg_score`/`total_marks` denormalization — computed on read
- No activity logging in Phase 1 (added in Phase 2)
- `question_count` and `attempt_count` updated via hooks + daily cron recalibration
- Daily cron recalculates both counts to fix drift

---

*Version: 2.1 — Quizzes Management (Phase 1)*