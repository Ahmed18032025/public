# Phase 1: MVP — GMCQ Quiz Engine

**Status:** ✅ Ready for Development

---

## Scope

- Categories
- Questions
- Quizzes
- Quiz Attempts
- Reports
- CSV Import
- Settings
- Dashboard

## Tables (8)

1. `gmcq_categories`
2. `gmcq_questions`
3. `gmcq_answers`
4. `gmcq_quizzes_meta`
5. `gmcq_question_map`
6. `gmcq_attempts`
7. `gmcq_attempt_answers`
8. `gmcq_imports`

---

## Major Rules

### Categories
- Deactivate only
- No soft delete

### Questions
- Soft delete
- Category is source of truth
- Hash duplicate detection

### Quizzes
- Manual category metadata
- Soft delete

### Attempts
- Never deleted
- Resumable while `in_progress`
- Pass if: `percentage >= pass_percentage`

### mcq_multiple

Storage:
```
Question 1
Selected A, B, C

attempt_answers:
Q1-A
Q1-B
Q1-C
```

Statistics: use `COUNT(DISTINCT question_id)` not `COUNT(*)` to avoid counting one question multiple times when multiple answers are selected.

### Rate Limiting
```
DATE(started_at) = CURDATE()
```

Maximum: 50 attempts per IP per quiz per calendar day.

### Backup
Before: import, bulk delete, restore.
Store: `wp-content/uploads/gmcq-backups/`

### Cron
Daily: category counts, usage counts, quiz stats.
Weekly: backup cleanup.

### Performance Targets
| Metric | Target |
|--------|--------|
| Dashboard | < 3s |
| Search | < 1s |
| Reports | < 2s |
| Import 1k | < 30s |
| Export 10k | < 10s |

---

## Schema Freeze Rules

### RULE 1 — No new tables within a phase.
The 8 tables above are the complete set for Phase 1. Future phases may introduce new tables through documented migrations.

### RULE 2 — No new columns.
Each table's columns are frozen for Phase 1.

### RULE 3 — No column removals.
Existing columns must stay.

### RULE 4 — No function renames.
Once a function is committed, its name is permanent.

### RULE 5 — No schema redesign.
The schema (indexes, types, defaults, constraints) is frozen as documented.

### RULE 6 — Better designs: document, don't implement.
Write improvements in `/docs/reviews/Future-Improvements.md`. Do not deviate from the frozen design.

### RULE 7 — Changes require: spec update, migration, audit pass.
If a rule must be broken (critical bug fix only): update all specs, create a migration, pass audit, get approval.

---

## Database Schema

### `gmcq_categories`

```sql
CREATE TABLE {prefix}gmcq_categories (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id           BIGINT UNSIGNED DEFAULT NULL,
    name                VARCHAR(255) NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    description         TEXT DEFAULT NULL,
    question_count      INT DEFAULT 0,
    sort_order          INT DEFAULT 0,
    is_active           TINYINT(1) DEFAULT 1,
    created_by          BIGINT UNSIGNED DEFAULT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_slug (slug),
    KEY idx_parent_active (parent_id, is_active),
    KEY idx_active_created (is_active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `gmcq_questions`

```sql
CREATE TABLE {prefix}gmcq_questions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     BIGINT UNSIGNED DEFAULT NULL,
    question_text   TEXT NOT NULL,
    question_hash   CHAR(32) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `gmcq_answers`

```sql
CREATE TABLE {prefix}gmcq_answers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id     BIGINT UNSIGNED NOT NULL,
    answer_text     TEXT NOT NULL,
    is_correct      TINYINT(1) DEFAULT 0,
    sort_order      INT DEFAULT 0,
    KEY idx_question (question_id),
    KEY idx_question_correct (question_id, is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `gmcq_quizzes_meta`

```sql
CREATE TABLE {prefix}gmcq_quizzes_meta (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id                 BIGINT UNSIGNED NOT NULL,
    category_id             BIGINT UNSIGNED DEFAULT NULL,
    time_limit              INT DEFAULT 0,
    pass_percentage         DECIMAL(5,2) DEFAULT 40.00,
    max_attempts            INT DEFAULT 0,
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
    question_count          INT DEFAULT 0,
    attempt_count           INT DEFAULT 0,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME DEFAULT NULL,
    deleted_by              BIGINT UNSIGNED DEFAULT NULL,
    UNIQUE KEY idx_quiz_id (quiz_id),
    KEY idx_category (category_id),
    KEY idx_status (status),
    KEY idx_status_active (status, is_active),
    KEY idx_question_count (question_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `gmcq_question_map`

```sql
CREATE TABLE {prefix}gmcq_question_map (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         BIGINT UNSIGNED NOT NULL,
    question_id     BIGINT UNSIGNED NOT NULL,
    sort_order      INT DEFAULT 0,
    marks           DECIMAL(5,2) DEFAULT NULL,
    negative_marks  DECIMAL(5,2) DEFAULT NULL,
    UNIQUE KEY idx_quiz_question (quiz_id, question_id),
    KEY idx_quiz_order (quiz_id, sort_order),
    KEY idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `gmcq_attempts`

```sql
CREATE TABLE {prefix}gmcq_attempts (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id             BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED DEFAULT NULL,
    category_id         BIGINT UNSIGNED DEFAULT NULL,
    score               DECIMAL(8,2) DEFAULT 0,
    max_score           DECIMAL(8,2) DEFAULT 0,
    percentage          DECIMAL(5,2) DEFAULT 0,
    total_questions     INT DEFAULT 0,
    correct_answers     INT DEFAULT 0,
    wrong_answers       INT DEFAULT 0,
    skipped_questions   INT DEFAULT 0,
    time_taken          INT DEFAULT 0,
    status              ENUM('in_progress','completed') DEFAULT 'in_progress',
    passed              TINYINT(1) DEFAULT NULL,
    is_active           TINYINT(1) DEFAULT 1,
    ip_address          VARCHAR(45) DEFAULT NULL,
    session_id          VARCHAR(64) DEFAULT NULL,
    started_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at        DATETIME DEFAULT NULL,
    KEY idx_quiz_id (quiz_id),
    KEY idx_user_id (user_id),
    KEY idx_status (status),
    KEY idx_started_at (started_at),
    KEY idx_quiz_user (quiz_id, user_id),
    KEY idx_category_started (category_id, started_at),
    KEY idx_quiz_status_date (quiz_id, status, started_at),
    KEY idx_user_date (user_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `gmcq_attempt_answers`

**mcq_multiple:** One row per selected answer. Use `COUNT(DISTINCT question_id)` for question counts (not `COUNT(*)`). Score is per question, not per selected answer.

```
Single-answer (mcq_single, true_false): 1 row per question
Multiple-answer (mcq_multiple):         N rows per question (one per selected answer)
Skipped:                                1 row with selected_answer_id = NULL
Not visited:                            No row exists
```

```sql
CREATE TABLE {prefix}gmcq_attempt_answers (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id          BIGINT UNSIGNED NOT NULL,
    question_id         BIGINT UNSIGNED NOT NULL,
    selected_answer_id  BIGINT UNSIGNED DEFAULT NULL     COMMENT 'NULL if skipped. For mcq_multiple: one row per selected answer.',
    is_correct          TINYINT(1) DEFAULT 0,
    marks_obtained      DECIMAL(5,2) DEFAULT 0,
    time_spent          INT DEFAULT 0,
    KEY idx_attempt (attempt_id),
    KEY idx_question (question_id),
    KEY idx_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `gmcq_imports`

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
    error_log           JSON DEFAULT NULL     COMMENT 'First 100 errors only',
    started_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at        DATETIME DEFAULT NULL,
    KEY idx_status (status),
    KEY idx_user (user_id),
    KEY idx_started (started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Key Behaviors

### Pass/Fail
```
percentage >= pass_percentage  → passed = 1
percentage <  pass_percentage  → passed = 0
```

### Resume Logic
| State | attempt_answers row | Behavior |
|-------|--------------------|----------|
| Not visited | No row exists | First unvisited = resume point |
| Skipped | EXISTS, selected_answer_id = NULL | Show as skipped, do not resume here |
| Answered | EXISTS, selected_answer_id = value | Show as answered (read-only) |

### Orphan Cleanup
- Never delete attempts
- Report orphan attempts (count only)
- Delete only orphan child records (answers, question_map, attempt_answers)

### Category Merge Reporting
- Historical attempts retain original `category_id`
- Reports use stored `category_id` from attempt record (never updated)

### Rate Limiting
```sql
DATE(started_at) = CURDATE()
```
Maximum: 50 attempts per IP per quiz per calendar day.

---

## Security Checklist

- [ ] All AJAX endpoints check `current_user_can('manage_gmcq')`
- [ ] All admin pages check `current_user_can('manage_gmcq')`
- [ ] Public endpoints use nonce + session validation
- [ ] `manage_gmcq` registered on activation via `$role->add_cap()`
- [ ] All AJAX requests include WordPress nonce
- [ ] All handlers verify via `check_ajax_referer()`
- [ ] String inputs: `sanitize_text_field()`
- [ ] Rich text: `wp_kses_post()`
- [ ] Numeric inputs: `(int)` or `absint()`
- [ ] All SQL uses `$wpdb->prepare()` with `%s`/`%d` placeholders
- [ ] All output: `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] CSV import uses `wp_handle_upload()`
- [ ] Backup directory created with `wp_mkdir_p()`

---

*Phase 1 — MVP*  
*Git Tag: `v0-architecture-freeze`*