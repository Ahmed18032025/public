# Reports — GMCQ Quiz Engine v2 (Phase 1)

## Purpose
View, filter, and export quiz attempt data. Phase 1 focuses on simple listing with pagination and chunked CSV export.

**Phase 1 Scope:** List attempts, filter by quiz/category/user/status/date, summary cards (cached), chunked CSV export. No archive/compression system.

---

## Issues Resolved

| Issue | Change |
|-------|--------|
| R1 | `category_id` denormalized into attempts (from first question) for fast filtering |
| R2 | `quiz_title` NOT denormalized — retrieved via cached JOIN to `wp_posts.ID` |
| R3 | Summary cards cached per filter combination (5-min TTL) |
| R4 | `is_active` on attempts for hiding fraudulent entries |
| R5 | `ip_address`, `session_id` for spam detection |
| R6 | Chunked CSV export for large datasets |
| R7 | Composite indexes for common queries |

**Deferred to Phase 2:** R8 (archive system), answer compression, `gmcq_attempt_answers_archive` table.

---

## Database Schema

See MasterImplementationPlan.md Section 1.6 (`gmcq_attempts`) and 1.7 (`gmcq_attempt_answers`).

**Phase 1 notes:**
- No `original_category_id` — merge deferred to Phase 2
- No `quiz_title` — retrieved via JOIN on read
- No `attempt_answers_archive` table

---

## Summary Cards (Phase 1)

Cached per filter combination, configurable TTL.

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

---

## Chunked CSV Export (Phase 1 — Keep)

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

---

## Quiz Title Helper (via JOIN)

```php
function gmcq_get_attempt_quiz_title( int $quiz_id ): string {
    $cache_key = 'gmcq_quiz_title_' . $quiz_id;
    $title     = get_transient( $cache_key );
    if ( false !== $title ) { return $title; }
    $title = get_the_title( $quiz_id ) ?: 'Quiz #' . $quiz_id;
    set_transient( $cache_key, $title, 300 );
    return $title;
}
```

---

## Rate Limiting

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

## Indexes (Phase 1)

- `idx_category_started (category_id, started_at)` — category filter
- `idx_quiz_status_date (quiz_id, status, started_at)` — quiz + status + date filter
- `idx_user_date (user_id, started_at)` — user attempt history

**Deferred to Phase 2:** `idx_status_completed` (for data retention purge).

---

## Technical Notes (Phase 1)

- `category_id` denormalized from first question for fast filtering
- `quiz_title` NOT denormalized — use `gmcq_get_attempt_quiz_title()` via cached JOIN (prevents stale titles)
- No archive/compression system in Phase 1 (deferred to Phase 2)
- Summary cards cached per filter combination (5-min TTL)
- Chunked CSV export: 1000 rows per chunk, streaming output

---

*Version: 2.1 — Reports (Phase 1)*