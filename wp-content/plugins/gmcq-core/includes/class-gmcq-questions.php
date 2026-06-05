<?php
/**
 * ============================================================
 * GMCQ Questions Module (Phase 1)
 * ============================================================
 *
 * Implements the Question Management panel as specified in
 * docs/Questions.md and docs/MasterImplementationPlan.md.
 *
 * - Soft delete with restore + permanent delete
 * - Question hash generation (duplicate detection)
 * - Usage count maintenance via hooks + daily cron
 * - 7 filter tabs (All / Active / No Category / Unassigned / Duplicates / Inactive / Inactive Category / Archived Quiz)
 * - DB transactions for multi-table writes
 * - 8 AJAX endpoints, 12 validation rules
 * - List page + Add/Edit form (with wp_editor for rich text)
 *
 * Phase 1 hook contract (fired by this module, consumed by class-gmcq-categories.php):
 *   - do_action( 'gmcq_before_save_question', $data )   // on create + update
 *   - do_action( 'gmcq_question_deleted', $question_id ) // on soft delete
 *   - do_action( 'gmcq_question_restored', $question_id ) // on restore
 *
 * Listened-to actions (other modules / cron may fire these):
 *   - gmcq_question_added_to_quiz     // from future Quiz module
 *   - gmcq_question_removed_from_quiz // from future Quiz module
 *   - gmcq_daily_cron                 // registered on plugin activation
 */

defined( 'ABSPATH' ) || exit;

// ========================================================================
// SECTION 1: CORE CRUD
// ========================================================================

/**
 * Create a new question with answers.
 *
 * Wraps question insert + answers insert in an explicit transaction.
 * Fires gmcq_before_save_question so the categories module can keep question_count in sync.
 *
 * @param array $data {
 *     @type int         $category_id    Optional. Leaf category ID.
 *     @type string      $question_text  Required. Question text (HTML allowed).
 *     @type string      $question_type  Optional. 'mcq_single' (default) | 'mcq_multiple' | 'true_false'.
 *     @type string      $explanation    Optional.
 *     @type string      $difficulty     Optional. 'easy' | 'medium' (default) | 'hard'.
 *     @type float       $marks          Optional. Default 1.00.
 *     @type float       $negative_marks Optional. Default 0.25.
 *     @type array       $answers        Required. Array of { answer_text, is_correct }.
 * }
 * @return int|\WP_Error Question ID on success, WP_Error on failure.
 */
function gmcq_create_question( array $data ) {
	global $wpdb;

	$validation = gmcq_validate_question_data( $data, 'create' );
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	// Generate hash and apply hook
	$question_hash = gmcq_generate_question_hash( $data['question_text'] );

	// Allow other modules to inject / modify data (e.g. set import_id)
	$data['question_hash'] = $question_hash;
	do_action( 'gmcq_before_save_question', $data );

	$p = $wpdb->prefix;

	$wpdb->query( 'START TRANSACTION' );

	try {
		$insert_data = array(
			'category_id'    => ! empty( $data['category_id'] ) ? (int) $data['category_id'] : null,
			'question_text'  => wp_kses_post( $data['question_text'] ),
			'question_hash'  => $question_hash,
			'question_type'  => $data['question_type'],
			'explanation'    => isset( $data['explanation'] ) ? wp_kses_post( $data['explanation'] ) : null,
			'difficulty'     => $data['difficulty'],
			'marks'          => $data['marks'],
			'negative_marks' => $data['negative_marks'],
			'is_active'      => 1,
			'usage_count'    => 0,
			'created_by'     => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
		);
		$insert_format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%d' );

		if ( ! empty( $data['import_id'] ) ) {
			$insert_data['import_id'] = (int) $data['import_id'];
			$insert_format[]          = '%d';
		}

		$inserted = $wpdb->insert( $p . 'gmcq_questions', $insert_data, $insert_format );
		if ( false === $inserted ) {
			throw new \Exception( $wpdb->last_error ?: 'Failed to insert question.' );
		}

		$question_id = (int) $wpdb->insert_id;
		if ( ! $question_id ) {
			throw new \Exception( 'Question insert returned no ID.' );
		}

		// Handle true_false: auto-generate True / False options
		$answers = $data['answers'];
		if ( 'true_false' === $data['question_type'] ) {
			$answers = array(
				array( 'answer_text' => 'True', 'is_correct' => ! empty( $data['true_is_correct'] ) ? 1 : 0 ),
				array( 'answer_text' => 'False', 'is_correct' => empty( $data['true_is_correct'] ) ? 1 : 0 ),
			);
		}

		$sort_order = 0;
		foreach ( $answers as $ans ) {
			$ans_inserted = $wpdb->insert(
				$p . 'gmcq_answers',
				array(
					'question_id' => $question_id,
					'answer_text' => sanitize_text_field( $ans['answer_text'] ),
					'is_correct'  => ! empty( $ans['is_correct'] ) ? 1 : 0,
					'sort_order'  => $sort_order++,
				),
				array( '%d', '%s', '%d', '%d' )
			);
			if ( false === $ans_inserted ) {
				throw new \Exception( $wpdb->last_error ?: 'Failed to insert answer.' );
			}
		}

		$wpdb->query( 'COMMIT' );
	} catch ( \Exception $e ) {
		$wpdb->query( 'ROLLBACK' );

		// Detect duplicate hash (UNIQUE constraint)
		if ( false !== strpos( $e->getMessage(), 'idx_question_hash' ) || false !== strpos( $e->getMessage(), 'Duplicate' ) ) {
			return new \WP_Error( 'duplicate_question', 'This question already exists (duplicate detected).' );
		}

		return new \WP_Error( 'db_error', 'Database error: ' . $e->getMessage() );
	}

	gmcq_clear_dashboard_cache( 'question' );

	return $question_id;
}

/**
 * Update an existing question and replace its answers.
 *
 * Wraps question update + answer delete/insert in an explicit transaction.
 * Regenerates question_hash if question_text changes.
 *
 * @param int   $question_id Question ID.
 * @param array $data        Same shape as gmcq_create_question().
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_update_question( int $question_id, array $data ) {
	global $wpdb;

	$existing = gmcq_get_question( $question_id );
	if ( ! $existing ) {
		return new \WP_Error( 'not_found', 'Question not found.' );
	}

	$validation = gmcq_validate_question_data( $data, 'update', $question_id );
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	// Regenerate hash if text changed
	$text_changed = wp_kses_post( $data['question_text'] ) !== $existing->question_text;
	$question_hash = $text_changed
		? gmcq_generate_question_hash( $data['question_text'] )
		: $existing->question_hash;

	$data['question_hash'] = $question_hash;
	do_action( 'gmcq_before_save_question', $data );

	$p = $wpdb->prefix;

	$wpdb->query( 'START TRANSACTION' );

	try {
		$update_data = array(
			'category_id'    => ! empty( $data['category_id'] ) ? (int) $data['category_id'] : null,
			'question_text'  => wp_kses_post( $data['question_text'] ),
			'question_hash'  => $question_hash,
			'question_type'  => $data['question_type'],
			'explanation'    => isset( $data['explanation'] ) ? wp_kses_post( $data['explanation'] ) : null,
			'difficulty'     => $data['difficulty'],
			'marks'          => $data['marks'],
			'negative_marks' => $data['negative_marks'],
		);
		$update_format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f' );

		$updated = $wpdb->update(
			$p . 'gmcq_questions',
			$update_data,
			array( 'id' => $question_id ),
			$update_format,
			array( '%d' )
		);
		if ( false === $updated ) {
			throw new \Exception( $wpdb->last_error ?: 'Failed to update question.' );
		}

		// Replace answers: delete all, re-insert
		$deleted = $wpdb->delete( $p . 'gmcq_answers', array( 'question_id' => $question_id ), array( '%d' ) );
		if ( false === $deleted ) {
			throw new \Exception( $wpdb->last_error ?: 'Failed to clear answers.' );
		}

		$answers = $data['answers'];
		if ( 'true_false' === $data['question_type'] ) {
			$answers = array(
				array( 'answer_text' => __( 'True', 'gmcq' ), 'is_correct' => ! empty( $data['true_is_correct'] ) ? 1 : 0 ),
				array( 'answer_text' => __( 'False', 'gmcq' ), 'is_correct' => empty( $data['true_is_correct'] ) ? 1 : 0 ),
			);
		}

		$sort_order = 0;
		foreach ( $answers as $ans ) {
			$ans_inserted = $wpdb->insert(
				$p . 'gmcq_answers',
				array(
					'question_id' => $question_id,
					'answer_text' => sanitize_text_field( $ans['answer_text'] ),
					'is_correct'  => ! empty( $ans['is_correct'] ) ? 1 : 0,
					'sort_order'  => $sort_order++,
				),
				array( '%d', '%s', '%d', '%d' )
			);
			if ( false === $ans_inserted ) {
				throw new \Exception( $wpdb->last_error ?: 'Failed to insert answer.' );
			}
		}

		$wpdb->query( 'COMMIT' );
	} catch ( \Exception $e ) {
		$wpdb->query( 'ROLLBACK' );

		if ( false !== strpos( $e->getMessage(), 'idx_question_hash' ) || false !== strpos( $e->getMessage(), 'Duplicate' ) ) {
			return new \WP_Error( 'duplicate_question', 'This question already exists (duplicate detected).' );
		}

		return new \WP_Error( 'db_error', 'Database error: ' . $e->getMessage() );
	}

	gmcq_clear_dashboard_cache( 'question' );

	return true;
}

/**
 * Soft delete a question (is_active=0, deleted_at=NOW, deleted_by=user).
 *
 * @param int $question_id Question ID.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_delete_question( int $question_id ) {
	global $wpdb;

	$existing = gmcq_get_question( $question_id );
	if ( ! $existing ) {
		return new \WP_Error( 'not_found', 'Question not found.' );
	}

	if ( 0 === (int) $existing->is_active ) {
		return true; // Already inactive
	}

	$updated = $wpdb->update(
		$wpdb->prefix . 'gmcq_questions',
		array(
			'is_active'  => 0,
			'deleted_at' => current_time( 'mysql' ),
			'deleted_by' => get_current_user_id(),
		),
		array( 'id' => $question_id ),
		array( '%d', '%s', '%d' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new \WP_Error( 'db_error', 'Database error: ' . $wpdb->last_error );
	}

	gmcq_clear_dashboard_cache( 'question' );

	do_action( 'gmcq_question_deleted', $question_id );

	return true;
}

/**
 * Restore a soft-deleted question.
 *
 * @param int $question_id Question ID.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_restore_question( int $question_id ) {
	global $wpdb;

	$existing = gmcq_get_question( $question_id );
	if ( ! $existing ) {
		return new \WP_Error( 'not_found', 'Question not found.' );
	}

	if ( 1 === (int) $existing->is_active ) {
		return true; // Already active
	}

	$updated = $wpdb->update(
		$wpdb->prefix . 'gmcq_questions',
		array(
			'is_active'  => 1,
			'deleted_at' => null,
			'deleted_by' => null,
		),
		array( 'id' => $question_id ),
		array( '%d', '%s', '%d' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new \WP_Error( 'db_error', 'Database error: ' . $wpdb->last_error );
	}

	gmcq_clear_dashboard_cache( 'question' );

	do_action( 'gmcq_question_restored', $question_id );

	return true;
}

/**
 * Permanently delete a question: remove from question_map, delete answers, delete question row.
 *
 * Creates a backup of the records being deleted before removal.
 *
 * @param int $question_id Question ID.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_delete_question_permanently( int $question_id ) {
	global $wpdb;

	$existing = gmcq_get_question( $question_id );
	if ( ! $existing ) {
		return new \WP_Error( 'not_found', 'Question not found.' );
	}

	// Only allowed on inactive questions (safety)
	if ( 1 === (int) $existing->is_active ) {
		return new \WP_Error( 'not_inactive', 'Only inactive (soft-deleted) questions can be permanently deleted.' );
	}

	// Backup before destructive action
	gmcq_create_backup( 'pre_bulk_question', 'question', array( $question_id ) );

	$p = $wpdb->prefix;

	$wpdb->query( 'START TRANSACTION' );

	try {
		// Remove from any quiz mappings
		$map_deleted = $wpdb->delete( $p . 'gmcq_question_map', array( 'question_id' => $question_id ), array( '%d' ) );
		if ( false === $map_deleted ) {
			throw new \Exception( $wpdb->last_error ?: 'Failed to remove from question_map.' );
		}

		// Delete answers
		$ans_deleted = $wpdb->delete( $p . 'gmcq_answers', array( 'question_id' => $question_id ), array( '%d' ) );
		if ( false === $ans_deleted ) {
			throw new \Exception( $wpdb->last_error ?: 'Failed to delete answers.' );
		}

		// Delete the question row
		$q_deleted = $wpdb->delete( $p . 'gmcq_questions', array( 'id' => $question_id ), array( '%d' ) );
		if ( false === $q_deleted ) {
			throw new \Exception( $wpdb->last_error ?: 'Failed to delete question.' );
		}

		$wpdb->query( 'COMMIT' );
	} catch ( \Exception $e ) {
		$wpdb->query( 'ROLLBACK' );
		return new \WP_Error( 'db_error', 'Database error: ' . $e->getMessage() );
	}

	// Recalculate usage counts for affected quizzes (those that had this question)
	$affected_quizzes = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT quiz_id FROM {$p}gmcq_quizzes_meta WHERE is_active = 1"
		)
	);
	// Note: We just do a global recalc; cheap with idx.
	gmcq_recalculate_usage_counts();

	foreach ( $affected_quizzes as $quiz_id ) {
		do_action( 'gmcq_quiz_questions_changed', (int) $quiz_id );
	}

	gmcq_clear_dashboard_cache( 'question' );

	return true;
}

/**
 * Get a single question with its answers.
 *
 * @param int $question_id Question ID.
 * @return object|null Question row object with 'answers' property, or null.
 */
function gmcq_get_question( int $question_id ) {
	global $wpdb;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_questions WHERE id = %d",
			$question_id
		)
	);

	if ( ! $row ) {
		return null;
	}

	$row->answers = gmcq_get_question_answers( $question_id );
	return $row;
}

/**
 * Get answers for a question.
 *
 * @param int $question_id Question ID.
 * @return array Array of answer rows.
 */
function gmcq_get_question_answers( int $question_id ): array {
	global $wpdb;

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_answers WHERE question_id = %d ORDER BY sort_order ASC, id ASC",
			$question_id
		)
	) ?: array();
}

/**
 * Bulk operations on questions.
 *
 * @param string $action 'delete' | 'restore' | 'change_category'.
 * @param array  $ids    Array of question IDs.
 * @param array  $extra  Optional. Extra data per action. For 'change_category' pass ['category_id' => int].
 * @return array { success: int, errors: array }
 */
function gmcq_bulk_questions( string $action, array $ids, array $extra = array() ): array {
	$success = 0;
	$errors  = array();

	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			continue;
		}

		switch ( $action ) {
			case 'delete':
				$result = gmcq_delete_question( $id );
				break;
			case 'restore':
				$result = gmcq_restore_question( $id );
				break;
			case 'change_category':
				if ( empty( $extra['category_id'] ) ) {
					$result = new \WP_Error( 'missing_category', 'Category ID is required for change_category.' );
				} else {
					$existing = gmcq_get_question( $id );
					if ( ! $existing ) {
						$result = new \WP_Error( 'not_found', 'Question not found.' );
					} else {
						global $wpdb;
						$updated = $wpdb->update(
							$wpdb->prefix . 'gmcq_questions',
							array( 'category_id' => (int) $extra['category_id'] ),
							array( 'id' => $id ),
							array( '%d' ),
							array( '%d' )
						);
						$result = ( false === $updated ) ? new \WP_Error( 'db_error', $wpdb->last_error ) : true;
						if ( true === $result ) {
							gmcq_clear_dashboard_cache( 'question' );
						}
					}
				}
				break;
			default:
				$result = new \WP_Error( 'invalid_action', 'Invalid bulk action: ' . $action );
				break;
		}

		if ( true === $result ) {
			$success++;
		} else {
			$errors[] = $result;
		}
	}

	return array(
		'success' => $success,
		'errors'  => $errors,
	);
}

/**
 * Batch save questions (for CSV import).
 *
 * Each row in $rows is the same shape as gmcq_create_question() expects.
 * Continues past individual row errors; returns aggregate results.
 *
 * @param array $rows Array of question data arrays.
 * @return array { imported: int, skipped: int, errors: array }
 */
function gmcq_batch_save_questions( array $rows ): array {
	$imported = 0;
	$skipped  = 0;
	$errors   = array();

	foreach ( $rows as $idx => $row ) {
		$result = gmcq_create_question( $row );
		if ( is_wp_error( $result ) ) {
			$errors[] = array(
				'row'     => $idx,
				'message' => $result->get_error_message(),
			);
			if ( 'duplicate_question' === $result->get_error_code() ) {
				$skipped++;
			}
		} else {
			$imported++;
		}
	}

	return array(
		'imported' => $imported,
		'skipped'  => $skipped,
		'errors'   => $errors,
	);
}

// ========================================================================
// SECTION 2: SEARCH & FILTER (list page)
// ========================================================================

/**
 * Search and filter questions for the list page.
 *
 * Supported filter values (matches Questions.md Filter Tabs table):
 *   - all              : both active and inactive
 *   - active           : is_active=1, deleted_at IS NULL
 *   - no_category      : category_id IS NULL
 *   - unassigned       : usage_count=0
 *   - duplicates       : question_hash with >1 rows
 *   - inactive         : is_active=0, deleted_at IS NOT NULL
 *   - inactive_category: category is_active=0
 *   - archived_quiz    : question is in an archived quiz
 *
 * @param array $args {
 *     @type string $query         Search text (FULLTEXT on question_text).
 *     @type string $filter        Filter key (default 'active').
 *     @type int    $category_id   Optional category filter.
 *     @type string $difficulty    Optional 'easy'|'medium'|'hard'.
 *     @type string $question_type Optional 'mcq_single'|'mcq_multiple'|'true_false'.
 *     @type int    $page          Page number (default 1).
 *     @type int    $per_page      Results per page (default 20, max 100).
 * }
 * @return array { results: array, total: int, page: int, per_page: int }
 */
function gmcq_search_questions( array $args = array() ): array {
	global $wpdb;

	$defaults = array(
		'query'         => '',
		'filter'        => 'active',
		'category_id'   => 0,
		'difficulty'    => '',
		'question_type' => '',
		'page'          => 1,
		'per_page'      => 20,
	);
	$args    = wp_parse_args( $args, $defaults );
	$p       = $wpdb->prefix;
	$page    = max( 1, (int) $args['page'] );
	$per_page = min( 100, max( 1, (int) $args['per_page'] ) );

	$where  = array( '1=1' );
	$join   = " LEFT JOIN {$p}gmcq_categories c ON c.id = q.category_id";
	$prepare = array();

	switch ( $args['filter'] ) {
		case 'all':
			// All (active + inactive)
			break;
		case 'active':
			$where[] = 'q.is_active = 1';
			break;
		case 'no_category':
			$where[] = 'q.category_id IS NULL';
			break;
		case 'unassigned':
			$where[] = 'q.usage_count = 0';
			break;
		case 'duplicates':
			$where[] = 'q.question_hash IN (SELECT question_hash FROM ' . $p . 'gmcq_questions GROUP BY question_hash HAVING COUNT(*) > 1)';
			break;
		case 'inactive':
			$where[] = 'q.is_active = 0';
			break;
		case 'inactive_category':
			$where[] = 'c.is_active = 0';
			break;
		case 'archived_quiz':
			$where[] = "EXISTS (SELECT 1 FROM {$p}gmcq_question_map qm JOIN {$p}gmcq_quizzes_meta zm ON zm.quiz_id = qm.quiz_id WHERE qm.question_id = q.id AND zm.is_active = 0)";
			break;
	}

	// FULLTEXT search
	if ( ! empty( $args['query'] ) && mb_strlen( trim( $args['query'] ) ) >= 3 ) {
		$like = '%' . $wpdb->esc_like( $args['query'] ) . '%';
		$where[]  = $wpdb->prepare( '(q.question_text LIKE %s OR q.explanation LIKE %s)', $like, $like );
	}

	// Dropdown filters
	if ( ! empty( $args['category_id'] ) ) {
		$where[]  = $wpdb->prepare( 'q.category_id = %d', (int) $args['category_id'] );
	}
	if ( ! empty( $args['difficulty'] ) ) {
		$where[]  = $wpdb->prepare( 'q.difficulty = %s', $args['difficulty'] );
	}
	if ( ! empty( $args['question_type'] ) ) {
		$where[]  = $wpdb->prepare( 'q.question_type = %s', $args['question_type'] );
	}

	$where_clause = implode( ' AND ', $where );

	// Count
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$p}gmcq_questions q {$join} WHERE {$where_clause}"
	);

	// Page
	$offset = ( $page - 1 ) * $per_page;

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT q.id, q.category_id, q.question_text, q.question_hash, q.question_type, q.difficulty,
			        q.marks, q.negative_marks, q.is_active, q.usage_count, q.deleted_at,
			        q.created_at, c.name AS category_name
			 FROM {$p}gmcq_questions q
			 {$join}
			 WHERE {$where_clause}
			 ORDER BY q.id DESC
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);

	return array(
		'results'  => $results ?: array(),
		'total'    => $total,
		'page'     => $page,
		'per_page' => $per_page,
	);
}

/**
 * Get counts for each filter tab (cached 5 min).
 *
 * @return array { all: int, active: int, no_category: int, unassigned: int, duplicates: int, inactive: int, inactive_category: int, archived_quiz: int }
 */
function gmcq_get_question_filter_counts(): array {
	$cache_key = 'gmcq_question_filter_counts';
	$counts    = get_transient( $cache_key );
	if ( false !== $counts ) {
		return $counts;
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$counts = array(
		'all'              => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions" ),
		'active'           => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE is_active = 1" ),
		'no_category'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE category_id IS NULL" ),
		'unassigned'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE usage_count = 0 AND is_active = 1" ),
		'duplicates'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT question_hash FROM {$p}gmcq_questions GROUP BY question_hash HAVING COUNT(*) > 1) t" ),
		'inactive'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE is_active = 0" ),
		'inactive_category'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions q JOIN {$p}gmcq_categories c ON c.id = q.category_id WHERE c.is_active = 0" ),
		'archived_quiz'    => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT q.id) FROM {$p}gmcq_questions q JOIN {$p}gmcq_question_map qm ON qm.question_id = q.id JOIN {$p}gmcq_quizzes_meta zm ON zm.quiz_id = qm.quiz_id WHERE zm.is_active = 0" ),
	);

	set_transient( $cache_key, $counts, 300 );

	return $counts;
}

// ========================================================================
// SECTION 3: VALIDATION
// ========================================================================

/**
 * Validate question data.
 *
 * @param array  $data         Question data.
 * @param string $context      'create' or 'update'.
 * @param int    $question_id  Optional. Question ID for update context (used for hash uniqueness check exclusion).
 * @return true|\WP_Error True on success, WP_Error with first error message.
 */
function gmcq_validate_question_data( array $data, string $context = 'create', int $question_id = 0 ) {
	// Question text required
	if ( empty( $data['question_text'] ) || '' === trim( wp_strip_all_tags( (string) $data['question_text'] ) ) ) {
		return new \WP_Error( 'question_text_empty', 'Question text cannot be empty.' );
	}

	// Question type validation
	$valid_types = array( 'mcq_single', 'mcq_multiple', 'true_false' );
	$question_type = isset( $data['question_type'] ) ? sanitize_key( $data['question_type'] ) : 'mcq_single';
	if ( ! in_array( $question_type, $valid_types, true ) ) {
		return new \WP_Error( 'invalid_type', 'Invalid question type.' );
	}

	// Category validation
	if ( empty( $data['category_id'] ) ) {
		return new \WP_Error( 'category_required', 'Please select a category.' );
	}
	$cat_check = gmcq_validate_question_category( (int) $data['category_id'] );
	if ( is_wp_error( $cat_check ) ) {
		return $cat_check;
	}

	// Difficulty validation
	$valid_difficulty = array( 'easy', 'medium', 'hard' );
	$difficulty = isset( $data['difficulty'] ) ? sanitize_key( $data['difficulty'] ) : 'medium';
	if ( ! in_array( $difficulty, $valid_difficulty, true ) ) {
		$difficulty = 'medium';
	}

	// Marks / negative marks validation
	$marks          = isset( $data['marks'] ) ? (float) $data['marks'] : 1.00;
	$negative_marks = isset( $data['negative_marks'] ) ? (float) $data['negative_marks'] : 0.25;
	if ( $marks < 0 ) {
		return new \WP_Error( 'marks_negative', 'Marks cannot be negative.' );
	}
	if ( $negative_marks < 0 ) {
		return new \WP_Error( 'negative_marks_negative', 'Negative marks cannot be negative.' );
	}

	// Answer validation (skip for true_false since we auto-generate)
	if ( 'true_false' === $question_type ) {
		// No answer validation needed - auto-generated
	} else {
		if ( empty( $data['answers'] ) || ! is_array( $data['answers'] ) ) {
			return new \WP_Error( 'no_answers', 'At least 2 answer options are required.' );
		}
		$answers = $data['answers'];

		// Filter empty
		$answers = array_values( array_filter( $answers, function( $a ) {
			return is_array( $a ) && isset( $a['answer_text'] ) && '' !== trim( (string) $a['answer_text'] );
		} ) );

		$count = count( $answers );
		if ( $count < 2 ) {
			return new \WP_Error( 'min_answers', 'At least 2 answer options are required.' );
		}
		if ( $count > 6 ) {
			return new \WP_Error( 'max_answers', 'Maximum 6 answer options allowed.' );
		}

		$correct_count = 0;
		foreach ( $answers as $ans ) {
			if ( empty( $ans['answer_text'] ) || '' === trim( (string) $ans['answer_text'] ) ) {
				return new \WP_Error( 'answer_text_empty', 'All answer options must have text.' );
			}
			if ( ! empty( $ans['is_correct'] ) ) {
				$correct_count++;
			}
		}

		if ( $correct_count < 1 ) {
			return new \WP_Error( 'no_correct', 'At least one answer must be marked correct.' );
		}
		if ( 'mcq_single' === $question_type && $correct_count !== 1 ) {
			return new \WP_Error( 'single_must_have_one', 'Single answer type must have exactly 1 correct answer.' );
		}
	}

	// Return normalized data by writing back to $data
	$data['question_type']  = $question_type;
	$data['difficulty']     = $difficulty;
	$data['marks']          = $marks;
	$data['negative_marks'] = $negative_marks;
	if ( isset( $answers ) ) {
		$data['answers'] = $answers;
	}

	return true;
}

// ========================================================================
// SECTION 4: HOOKS
// ========================================================================

/**
 * Register all question-related hooks.
 */
function gmcq_register_question_hooks(): void {
	// Usage count maintenance (Master Plan §4, Questions.md Hooks)
	add_action( 'gmcq_question_added_to_quiz', 'gmcq_handle_question_added_to_quiz', 10, 1 );
	add_action( 'gmcq_question_removed_from_quiz', 'gmcq_handle_question_removed_from_quiz', 10, 1 );

	// Daily cron recalculation
	add_action( 'gmcq_daily_cron', 'gmcq_recalculate_usage_counts' );
}

/**
 * Increment usage_count when a question is added to a quiz.
 *
 * @param int $question_id Question ID.
 */
function gmcq_handle_question_added_to_quiz( int $question_id ): void {
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}gmcq_questions SET usage_count = usage_count + 1 WHERE id = %d",
			$question_id
		)
	);
	gmcq_clear_dashboard_cache( 'question' );
}

/**
 * Decrement usage_count when a question is removed from a quiz (floored at 0).
 *
 * @param int $question_id Question ID.
 */
function gmcq_handle_question_removed_from_quiz( int $question_id ): void {
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}gmcq_questions SET usage_count = GREATEST(0, usage_count - 1) WHERE id = %d",
			$question_id
		)
	);
	gmcq_clear_dashboard_cache( 'question' );
}

/**
 * Recalculate usage_count for all questions (fixes drift).
 *
 * Called from gmcq_daily_cron.
 */
function gmcq_recalculate_usage_counts(): void {
	global $wpdb;
	$wpdb->query(
		"UPDATE {$wpdb->prefix}gmcq_questions q
		 SET q.usage_count = (
		     SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_question_map qm WHERE qm.question_id = q.id
		 )
		 WHERE q.is_active = 1"
	);
}

// ========================================================================
// SECTION 5: AJAX HANDLERS
// ========================================================================

/**
 * Register all question AJAX endpoints.
 */
function gmcq_register_question_ajax_handlers(): void {
	add_action( 'wp_ajax_gmcq_save_question',                'gmcq_ajax_save_question' );
	add_action( 'wp_ajax_gmcq_delete_question',              'gmcq_ajax_delete_question' );
	add_action( 'wp_ajax_gmcq_restore_question',             'gmcq_ajax_restore_question' );
	add_action( 'wp_ajax_gmcq_delete_question_permanently',  'gmcq_ajax_delete_question_permanently' );
	add_action( 'wp_ajax_gmcq_bulk_questions',               'gmcq_ajax_bulk_questions' );
	add_action( 'wp_ajax_gmcq_search_questions',             'gmcq_ajax_search_questions' );
	add_action( 'wp_ajax_gmcq_get_question',                 'gmcq_ajax_get_question' );
	add_action( 'wp_ajax_gmcq_batch_save_questions',         'gmcq_ajax_batch_save_questions' );
}

/**
 * Helper: read answers from $_POST (form-encoded array).
 *
 * @return array Normalized answers array.
 */
function gmcq_read_post_answers(): array {
	if ( ! isset( $_POST['answers'] ) || ! is_array( $_POST['answers'] ) ) {
		return array();
	}

	$raw = $_POST['answers'];

	// Detect FLAT HTML form structure (the actual form rendering uses this):
	//   answers[answer_text][] = A, B, C, D
	//   answers[is_correct]    = 1   (for mcq_single, single value)
	//   answers[is_correct][]  = ['1', '', '', '']   (for mcq_multiple, indexed array)
	// In PHP, $_POST['answers'] looks like: ['answer_text' => [...], 'is_correct' => ...]
	if ( isset( $raw['answer_text'] ) || isset( $raw['is_correct'] ) ) {
		$texts    = isset( $raw['answer_text'] ) ? (array) $raw['answer_text'] : array();
		$corrects = isset( $raw['is_correct'] ) ? $raw['is_correct'] : array();

		$out = array();
		foreach ( $texts as $i => $text ) {
			$is_correct = 0;
			// Handle MCQ Multiple (array of indices) vs MCQ Single (single index value)
			if ( is_array( $corrects ) && isset( $corrects[ $i ] ) ) {
				$is_correct = 1;
			} elseif ( ! is_array( $corrects ) && (int) $corrects === $i ) {
				$is_correct = 1;
			}
			$out[] = array(
				'answer_text' => wp_kses_post( $text ),
				'is_correct'  => $is_correct,
			);
		}
		return $out;
	}

	// Detect NESTED structure (used by AJAX/CSV import batch_save):
	//   answers: [ { answer_text: 'A', is_correct: 1 }, { ... } ]
	$out = array();
	foreach ( $raw as $ans ) {
		$ans = (array) $ans;
		$out[] = array(
			'answer_text' => isset( $ans['answer_text'] ) ? wp_kses_post( $ans['answer_text'] ) : '',
			'is_correct'  => ! empty( $ans['is_correct'] ) ? 1 : 0,
		);
	}
	return $out;
}

/**
 * AJAX: Save (create or update) a question.
 */
function gmcq_ajax_save_question(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		if ( ob_get_length() ) { ob_clean(); }
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$question_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

	$data = array(
		'category_id'    => isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0,
		'question_text'  => isset( $_POST['question_text'] ) ? wp_kses_post( wp_unslash( $_POST['question_text'] ) ) : '',
		'question_type'  => isset( $_POST['question_type'] ) ? sanitize_key( $_POST['question_type'] ) : 'mcq_single',
		'explanation'    => isset( $_POST['explanation'] ) ? wp_kses_post( wp_unslash( $_POST['explanation'] ) ) : '',
		'difficulty'     => isset( $_POST['difficulty'] ) ? sanitize_key( $_POST['difficulty'] ) : 'medium',
		'marks'          => isset( $_POST['marks'] ) ? (float) $_POST['marks'] : 1.00,
		'negative_marks' => isset( $_POST['negative_marks'] ) ? (float) $_POST['negative_marks'] : 0.25,
		'true_is_correct'=> isset( $_POST['true_is_correct'] ) ? (int) $_POST['true_is_correct'] : 1,
		'answers'        => gmcq_read_post_answers(),
	);

	if ( $question_id > 0 ) {
		$result = gmcq_update_question( $question_id, $data );
		$message = __( 'Question updated successfully.', 'gmcq' );
	} else {
		$result = gmcq_create_question( $data );
		$message = __( 'Question created successfully.', 'gmcq' );
	}

	if ( is_wp_error( $result ) ) {
		if ( ob_get_length() ) { ob_clean(); }
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	if ( ob_get_length() ) { ob_clean(); }
	wp_send_json_success( array(
		'message'     => $message,
		'question_id' => (int) $result,
	) );
}

/**
 * AJAX: Soft delete a question.
 */
function gmcq_ajax_delete_question(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid question ID.', 'gmcq' ) ) );
	}

	$result = gmcq_delete_question( $id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => __( 'Question moved to inactive.', 'gmcq' ) ) );
}

/**
 * AJAX: Restore a question.
 */
function gmcq_ajax_restore_question(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid question ID.', 'gmcq' ) ) );
	}

	$result = gmcq_restore_question( $id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => __( 'Question restored.', 'gmcq' ) ) );
}

/**
 * AJAX: Permanently delete a question.
 */
function gmcq_ajax_delete_question_permanently(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid question ID.', 'gmcq' ) ) );
	}

	$result = gmcq_delete_question_permanently( $id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => __( 'Question permanently deleted.', 'gmcq' ) ) );
}

/**
 * AJAX: Bulk operations.
 */
function gmcq_ajax_bulk_questions(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
	$ids    = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
	$extra  = array();
	if ( 'change_category' === $action && isset( $_POST['category_id'] ) ) {
		$extra['category_id'] = (int) $_POST['category_id'];
	}

	if ( empty( $action ) || empty( $ids ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'gmcq' ) ) );
	}

	// Backup before any bulk destructive action
	if ( 'delete' === $action ) {
		gmcq_create_backup( 'pre_bulk_question', 'question', $ids );
	}

	$result = gmcq_bulk_questions( $action, $ids, $extra );

	wp_send_json_success( $result );
}

/**
 * AJAX: Search/list questions with filters.
 */
function gmcq_ajax_search_questions(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$args = array(
		'query'         => isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '',
		'filter'        => isset( $_REQUEST['filter'] ) ? sanitize_key( $_REQUEST['filter'] ) : 'active',
		'category_id'   => isset( $_REQUEST['category_id'] ) ? (int) $_REQUEST['category_id'] : 0,
		'difficulty'    => isset( $_REQUEST['difficulty'] ) ? sanitize_key( $_REQUEST['difficulty'] ) : '',
		'question_type' => isset( $_REQUEST['question_type'] ) ? sanitize_key( $_REQUEST['question_type'] ) : '',
		'page'          => isset( $_REQUEST['page'] ) ? max( 1, (int) $_REQUEST['page'] ) : 1,
		'per_page'      => isset( $_REQUEST['per_page'] ) ? (int) $_REQUEST['per_page'] : 20,
	);

	$result = gmcq_search_questions( $args );

	wp_send_json_success( $result );
}

/**
 * AJAX: Get a single question (for edit form).
 */
function gmcq_ajax_get_question(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid question ID.', 'gmcq' ) ) );
	}

	$question = gmcq_get_question( $id );
	if ( ! $question ) {
		wp_send_json_error( array( 'message' => __( 'Question not found.', 'gmcq' ) ) );
	}

	wp_send_json_success( array( 'question' => $question ) );
}

/**
 * AJAX: Batch save questions (CSV import endpoint).
 */
function gmcq_ajax_batch_save_questions(): void {
	check_ajax_referer( 'gmcq_question_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$rows = isset( $_POST['rows'] ) ? (array) $_POST['rows'] : array();
	if ( empty( $rows ) ) {
		wp_send_json_error( array( 'message' => __( 'No rows provided.', 'gmcq' ) ) );
	}

	// Normalize each row
	$normalized = array();
	foreach ( $rows as $row ) {
		$row = (array) $row;
		$normalized[] = array(
			'category_id'    => isset( $row['category_id'] ) ? (int) $row['category_id'] : 0,
			'question_text'  => isset( $row['question_text'] ) ? wp_kses_post( $row['question_text'] ) : '',
			'question_type'  => isset( $row['question_type'] ) ? sanitize_key( $row['question_type'] ) : 'mcq_single',
			'explanation'    => isset( $row['explanation'] ) ? wp_kses_post( $row['explanation'] ) : '',
			'difficulty'     => isset( $row['difficulty'] ) ? sanitize_key( $row['difficulty'] ) : 'medium',
			'marks'          => isset( $row['marks'] ) ? (float) $row['marks'] : 1.00,
			'negative_marks' => isset( $row['negative_marks'] ) ? (float) $row['negative_marks'] : 0.25,
			'import_id'      => isset( $row['import_id'] ) ? (int) $row['import_id'] : 0,
			'answers'        => isset( $row['answers'] ) ? (array) $row['answers'] : array(),
		);
	}

	$result = gmcq_batch_save_questions( $normalized );

	wp_send_json_success( $result );
}

// ========================================================================
// SECTION 6: ADMIN PAGE RENDERERS
// ========================================================================

/**
 * Render the Questions list page.
 */
function gmcq_render_questions_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	if ( 'add' === $action ) {
		gmcq_render_question_add_form();
		return;
	}
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		gmcq_render_question_edit_form( (int) $_GET['id'] );
		return;
	}

	$filter        = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'active';
	$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$category_id   = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
	$difficulty    = isset( $_GET['difficulty'] ) ? sanitize_key( $_GET['difficulty'] ) : '';
	$question_type = isset( $_GET['question_type'] ) ? sanitize_key( $_GET['question_type'] ) : '';
	$page          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

	$search_result = gmcq_search_questions( array(
		'query'         => $search,
		'filter'        => $filter,
		'category_id'   => $category_id,
		'difficulty'    => $difficulty,
		'question_type' => $question_type,
		'page'          => $page,
		'per_page'      => 20,
	) );
	$counts = gmcq_get_question_filter_counts();
	$cats   = gmcq_get_categories( array( 'parent_only' => true, 'filter' => 'active' ) );
	$cat_list = $cats['categories'];

	$list_url   = admin_url( 'admin.php?page=gmcq-questions' );
	$total      = (int) $search_result['total'];
	$total_pages = (int) ceil( $total / 20 );
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1>
			<?php printf( '<a href="%s">%s</a> &rsaquo; %s', esc_url( admin_url( 'admin.php?page=gmcq-dashboard' ) ), esc_html__( 'GMCQ', 'gmcq' ), esc_html__( 'Questions', 'gmcq' ) ); ?>
		</h1>
		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Question Management', 'gmcq' ); ?></h2>
			<p>
				<?php printf( esc_html__( 'Centralized question bank. %s to get started.', 'gmcq' ), '<a href="' . esc_url( $list_url . '&action=add' ) . '">' . esc_html__( 'Add New Question', 'gmcq' ) . '</a>' ); ?>
			</p>
			<div class="gmcq-filter-tabs">
				<?php
				$tabs = array(
					'all'               => __( 'All', 'gmcq' ),
					'active'            => __( 'Active', 'gmcq' ),
					'no_category'       => __( 'No Category', 'gmcq' ),
					'unassigned'        => __( 'Unassigned', 'gmcq' ),
					'duplicates'        => __( 'Duplicates', 'gmcq' ),
					'inactive'          => __( 'Inactive', 'gmcq' ),
					'inactive_category' => __( 'Inactive Category', 'gmcq' ),
					'archived_quiz'     => __( 'Archived Quiz', 'gmcq' ),
				);
				foreach ( $tabs as $key => $label ) {
					$c = ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 );
					$cls = ( $key === $filter ) ? 'current' : '';
					printf(
						'<a href="%s" class="%s">%s (%d)</a>',
						esc_url( $list_url . '&filter=' . $key ),
						esc_attr( $cls ),
						esc_html( $label ),
						$c
					);
				}
				?>
			</div>
			<form method="get" class="gmcq-search-box" action="">
				<input type="hidden" name="page" value="gmcq-questions">
				<input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
				<select name="category_id">
					<option value="0"><?php esc_html_e( 'All Categories', 'gmcq' ); ?></option>
					<?php foreach ( $cat_list as $c ) : ?>
						<option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $category_id, $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="difficulty">
					<option value=""><?php esc_html_e( 'All Difficulty', 'gmcq' ); ?></option>
					<option value="easy"   <?php selected( $difficulty, 'easy' ); ?>><?php esc_html_e( 'Easy', 'gmcq' ); ?></option>
					<option value="medium" <?php selected( $difficulty, 'medium' ); ?>><?php esc_html_e( 'Medium', 'gmcq' ); ?></option>
					<option value="hard"   <?php selected( $difficulty, 'hard' ); ?>><?php esc_html_e( 'Hard', 'gmcq' ); ?></option>
				</select>
				<select name="question_type">
					<option value=""><?php esc_html_e( 'All Types', 'gmcq' ); ?></option>
					<option value="mcq_single"   <?php selected( $question_type, 'mcq_single' ); ?>><?php esc_html_e( 'MCQ Single', 'gmcq' ); ?></option>
					<option value="mcq_multiple" <?php selected( $question_type, 'mcq_multiple' ); ?>><?php esc_html_e( 'MCQ Multiple', 'gmcq' ); ?></option>
					<option value="true_false"   <?php selected( $question_type, 'true_false' ); ?>><?php esc_html_e( 'True/False', 'gmcq' ); ?></option>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search questions...', 'gmcq' ); ?>" style="width:200px">
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'gmcq' ); ?></button>
				<a href="<?php echo esc_url( $list_url . '&filter=' . $filter ); ?>" class="button"><?php esc_html_e( 'Reset', 'gmcq' ); ?></a>
			</form>
			<form id="gmcq-bulk-form" method="post">
				<?php wp_nonce_field( 'gmcq_question_nonce' ); ?>
				<table class="wp-list-table widefat fixed striped" style="margin-top:15px">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" id="gmcq-cb-all"></td>
							<th><?php esc_html_e( 'Question', 'gmcq' ); ?></th>
							<th style="width:140px"><?php esc_html_e( 'Category', 'gmcq' ); ?></th>
							<th style="width:120px"><?php esc_html_e( 'Type', 'gmcq' ); ?></th>
							<th style="width:90px"><?php esc_html_e( 'Difficulty', 'gmcq' ); ?></th>
							<th style="width:60px"><?php esc_html_e( 'Used In', 'gmcq' ); ?></th>
						</tr>
					</thead>
					<tbody id="gmcq-questions-tbody">
						<?php if ( empty( $search_result['results'] ) ) : ?>
							<tr><td colspan="6" style="text-align:center;padding:30px;color:#666"><?php esc_html_e( 'No questions found.', 'gmcq' ); ?></td></tr>
						<?php else : foreach ( $search_result['results'] as $q ) :
							$diff_color = array( 'easy' => '#46b450', 'medium' => '#ffb900', 'hard' => '#dc3232' );
							$type_label = array( 'mcq_single' => 'MCQ Single', 'mcq_multiple' => 'MCQ Multiple', 'true_false' => 'True/False' );
							?>
							<tr data-id="<?php echo (int) $q->id; ?>">
								<th scope="row" class="check-column"><input type="checkbox" class="gmcq-cb" value="<?php echo (int) $q->id; ?>"></th>
								<td>
									<strong><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $q->question_text ), 20, '&hellip;' ) ); ?></strong>
									<?php if ( 0 === (int) $q->is_active ) : ?>
										<span class="gmcq-status-inactive" style="margin-left:6px"><?php esc_html_e( 'Inactive', 'gmcq' ); ?></span>
									<?php endif; ?>
									<div class="row-actions" style="font-size:13px">
										<span class="edit"><a href="<?php echo esc_url( $list_url . '&action=edit&id=' . $q->id ); ?>"><?php esc_html_e( 'Edit', 'gmcq' ); ?></a></span>
										<?php if ( 1 === (int) $q->is_active ) : ?>
											| <span class="trash"><a href="#" class="gmcq-delete-q" data-id="<?php echo (int) $q->id; ?>" style="color:#dc3232"><?php esc_html_e( 'Delete', 'gmcq' ); ?></a></span>
										<?php else : ?>
											| <span class="restore"><a href="#" class="gmcq-restore-q" data-id="<?php echo (int) $q->id; ?>" style="color:#46b450"><?php esc_html_e( 'Restore', 'gmcq' ); ?></a></span>
											| <span class="delete-perm"><a href="#" class="gmcq-delete-perm-q" data-id="<?php echo (int) $q->id; ?>" style="color:#a00"><?php esc_html_e( 'Delete Permanently', 'gmcq' ); ?></a></span>
										<?php endif; ?>
									</div>
								</td>
								<td><?php echo $q->category_name ? esc_html( $q->category_name ) : '<em style="color:#999">' . esc_html__( 'None', 'gmcq' ) . '</em>'; ?></td>
								<td><?php echo esc_html( $type_label[ $q->question_type ] ?? $q->question_type ); ?></td>
								<td><span style="color:<?php echo esc_attr( $diff_color[ $q->difficulty ] ?? '#666' ); ?>;font-weight:600"><?php echo esc_html( ucfirst( $q->difficulty ) ); ?></span></td>
								<td><?php echo (int) $q->usage_count; ?></td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
				<div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center">
					<div>
						<select name="bulk_action" id="gmcq-bulk-action">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'gmcq' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'gmcq' ); ?></option>
							<option value="restore"><?php esc_html_e( 'Restore', 'gmcq' ); ?></option>
						</select>
						<button type="button" class="button" id="gmcq-bulk-apply"><?php esc_html_e( 'Apply', 'gmcq' ); ?></button>
					</div>
					<div>
						<?php printf( esc_html__( 'Showing page %1$d of %2$d (%3$d total)', 'gmcq' ), (int) $page, max( 1, (int) $total_pages ), (int) $total ); ?>
					</div>
				</div>
			</form>
		</div>
	</div>
	<div id="gmcq-notice-area" role="alert" aria-live="polite" style="display:none;position:fixed;top:50px;right:20px;z-index:10000;max-width:400px;padding:12px 20px;border-left:4px solid #46b450;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.15)"></div>
	<script>
	jQuery(document).ready(function($){
		// Read the QUESTION nonce from the bulk form's hidden field (set by wp_nonce_field('gmcq_question_nonce')).
		// gmcqAdmin.nonce is the CATEGORY nonce, so don't use it here — would cause nonce verification to fail silently.
		var nonce = $('#gmcq-bulk-form input[name="_ajax_nonce"]').val() || '';
		var $n = $('#gmcq-notice-area');
		function notice(msg, isError){
			$n.css('border-color', isError ? '#dc3232' : '#46b450').text(msg).fadeIn(300).delay(isError ? 5000 : 2000).fadeOut(600);
		}
		$('#gmcq-cb-all').on('change', function(){ $('.gmcq-cb').prop('checked', this.checked); });
		$('#gmcq-questions-tbody').on('click', 'a.gmcq-delete-q, a.gmcq-restore-q, a.gmcq-delete-perm-q', function(e){
			e.preventDefault();
			var $l = $(this), id = parseInt($l.data('id'));
			if ($l.hasClass('gmcq-delete-q')) {
				if (!confirm('<?php echo esc_js( __( 'Move this question to inactive?', 'gmcq' ) ); ?>')) return;
				$.post(gmcqAdmin.ajaxUrl, {action: 'gmcq_delete_question', id: id, _ajax_nonce: nonce}, function(r){
					if (r.success) { notice(r.data.message); setTimeout(function(){ location.reload(); }, 800); }
					else { notice(r.data.message || 'Error', true); }
				}).fail(function(){ notice('Server error', true); });
			} else if ($l.hasClass('gmcq-restore-q')) {
				$.post(gmcqAdmin.ajaxUrl, {action: 'gmcq_restore_question', id: id, _ajax_nonce: nonce}, function(r){
					if (r.success) { notice(r.data.message); setTimeout(function(){ location.reload(); }, 800); }
					else { notice(r.data.message || 'Error', true); }
				}).fail(function(){ notice('Server error', true); });
			} else if ($l.hasClass('gmcq-delete-perm-q')) {
				if (!confirm('<?php echo esc_js( __( 'PERMANENTLY delete this question and remove from all quizzes? This cannot be undone.', 'gmcq' ) ); ?>')) return;
				$.post(gmcqAdmin.ajaxUrl, {action: 'gmcq_delete_question_permanently', id: id, _ajax_nonce: nonce}, function(r){
					if (r.success) { notice(r.data.message); setTimeout(function(){ location.reload(); }, 800); }
					else { notice(r.data.message || 'Error', true); }
				}).fail(function(){ notice('Server error', true); });
			}
		});
		$('#gmcq-bulk-apply').on('click', function(){
			var ids = $('.gmcq-cb:checked').map(function(){ return parseInt(this.value); }).get();
			var action = $('#gmcq-bulk-action').val();
			if (!action || ids.length === 0) { notice('Please select an action and at least one question', true); return; }
			if (action === 'delete' && !confirm(<?php echo wp_json_encode( __( 'Soft-delete %d selected question(s)?', 'gmcq' ) ); ?>.replace('%d', String(ids.length)))) return;
			$.post(gmcqAdmin.ajaxUrl, {action: 'gmcq_bulk_questions', bulk_action: action, ids: ids, _ajax_nonce: nonce}, function(r){
				if (r.success) { notice('Done: ' + r.data.success + ' succeeded'); setTimeout(function(){ location.reload(); }, 1000); }
				else { notice(r.data.message || 'Error', true); }
			}).fail(function(){ notice('Server error', true); });
		});
	});
	</script>
	<?php
}

/**
 * Render the Add Question form.
 */
function gmcq_render_question_add_form(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}
	gmcq_render_question_form( 0 );
}

/**
 * Render the Edit Question form.
 *
 * @param int $question_id Question ID.
 */
function gmcq_render_question_edit_form( int $question_id ): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}
	$q = gmcq_get_question( $question_id );
	if ( ! $q ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Question not found.', 'gmcq' ) . '</p></div>';
		return;
	}
	gmcq_render_question_form( $question_id, $q );
}

/**
 * Render the Add/Edit question form (shared).
 *
 * @param int        $question_id 0 for new, >0 for edit.
 * @param object|null $q           Existing question object (null for new).
 */
function gmcq_render_question_form( int $question_id, $q = null ): void {
	$is_edit = ( $question_id > 0 && $q );
	$cats    = gmcq_get_categories( array( 'parent_only' => false, 'filter' => 'active', 'per_page' => -1 ) );
	$cat_list = $cats['categories'];
	$list_url = admin_url( 'admin.php?page=gmcq-questions' );

	// Pre-fill values
	$q_text      = $is_edit ? $q->question_text : '';
	$q_type      = $is_edit ? $q->question_type : 'mcq_single';
	$q_diff      = $is_edit ? $q->difficulty : 'medium';
	$q_marks     = $is_edit ? $q->marks : 1.00;
	$q_neg_marks = $is_edit ? $q->negative_marks : 0.25;
	$q_cat       = $is_edit ? (int) $q->category_id : 0;
	$q_expl      = $is_edit ? $q->explanation : '';
	$answers     = $is_edit ? (array) $q->answers : array(
		array( 'answer_text' => '', 'is_correct' => 0 ),
		array( 'answer_text' => '', 'is_correct' => 0 ),
		array( 'answer_text' => '', 'is_correct' => 0 ),
		array( 'answer_text' => '', 'is_correct' => 0 ),
	);
	if ( count( $answers ) < 2 ) {
		while ( count( $answers ) < 2 ) {
			$answers[] = array( 'answer_text' => '', 'is_correct' => 0 );
		}
	}

	// Enqueue rich editor
	wp_enqueue_editor();
	wp_enqueue_script( 'wp-tinymce' );

	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1>
			<?php
			printf(
				'<a href="%s">%s</a> &rsaquo; <a href="%s">%s</a> &rsaquo; %s',
				esc_url( admin_url( 'admin.php?page=gmcq-dashboard' ) ),
				esc_html__( 'GMCQ', 'gmcq' ),
				esc_url( $list_url ),
				esc_html__( 'Questions', 'gmcq' ),
				esc_html( $is_edit ? __( 'Edit Question', 'gmcq' ) : __( 'Add New Question', 'gmcq' ) )
			);
			?>
		</h1>
		<div class="gmcq-card" style="max-width:900px">
			<form id="gmcq-question-form" method="post">
				<input type="hidden" name="id" value="<?php echo (int) $question_id; ?>">
				<?php wp_nonce_field( 'gmcq_question_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gmcq-q-category"><?php esc_html_e( 'Category', 'gmcq' ); ?> <span style="color:red">*</span></label></th>
						<td>
							<select name="category_id" id="gmcq-q-category" required>
								<option value="0"><?php esc_html_e( '— Select —', 'gmcq' ); ?></option>
								<?php foreach ( $cat_list as $c ) : ?>
									<option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $q_cat, $c->id ); ?>>
										<?php
										$prefix = ! empty( $c->parent_id ) ? '&mdash; ' : '';
										echo esc_html( $prefix . $c->name );
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-q-type"><?php esc_html_e( 'Type', 'gmcq' ); ?> <span style="color:red">*</span></label></th>
						<td>
							<select name="question_type" id="gmcq-q-type" required>
								<option value="mcq_single"   <?php selected( $q_type, 'mcq_single' ); ?>><?php esc_html_e( 'MCQ Single Answer', 'gmcq' ); ?></option>
								<option value="mcq_multiple" <?php selected( $q_type, 'mcq_multiple' ); ?>><?php esc_html_e( 'MCQ Multiple Answer', 'gmcq' ); ?></option>
								<option value="true_false"   <?php selected( $q_type, 'true_false' ); ?>><?php esc_html_e( 'True/False', 'gmcq' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-q-difficulty"><?php esc_html_e( 'Difficulty', 'gmcq' ); ?></label></th>
						<td>
							<select name="difficulty" id="gmcq-q-difficulty">
								<option value="easy"   <?php selected( $q_diff, 'easy' ); ?>><?php esc_html_e( 'Easy', 'gmcq' ); ?></option>
								<option value="medium" <?php selected( $q_diff, 'medium' ); ?>><?php esc_html_e( 'Medium', 'gmcq' ); ?></option>
								<option value="hard"   <?php selected( $q_diff, 'hard' ); ?>><?php esc_html_e( 'Hard', 'gmcq' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-q-text"><?php esc_html_e( 'Question Text', 'gmcq' ); ?> <span style="color:red">*</span></label></th>
						<td>
							<?php
							wp_editor(
								$q_text,
								'gmcq_q_text',
								array(
									'textarea_name' => 'question_text',
									'textarea_rows' => 8,
									'media_buttons' => true,
									'teeny'         => false,
									'quicktags'     => true,
								)
							);
							?>
						</td>
					</tr>
					<tr id="gmcq-answers-row">
						<th scope="row"><?php esc_html_e( 'Answer Options', 'gmcq' ); ?> <span style="color:red">*</span></th>
						<td>
							<table class="widefat" id="gmcq-answers-table" style="max-width:600px">
								<thead><tr>
									<th style="width:40px"><?php esc_html_e( 'Correct', 'gmcq' ); ?></th>
									<th><?php esc_html_e( 'Answer Text', 'gmcq' ); ?></th>
									<th style="width:80px"><?php esc_html_e( 'Action', 'gmcq' ); ?></th>
								</tr></thead>
								<tbody>
									<?php
									$ai = 0;
									foreach ( $answers as $ans ) :
										$input_type = ( 'mcq_single' === $q_type ) ? 'radio' : 'checkbox';
										$input_name = ( 'mcq_single' === $q_type ) ? 'answers[is_correct]' : "answers[is_correct][{$ai}]";
										$input_val  = ( 'mcq_single' === $q_type ) ? $ai : '1';
										?>
										<tr class="gmcq-answer-row" data-idx="<?php echo $ai; ?>">
											<td><input type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $input_val ); ?>" class="gmcq-correct-input" <?php checked( ! empty( $ans->is_correct ) ); ?>></td>
											<td><input type="text" name="answers[answer_text][]" value="<?php echo esc_attr( $ans->answer_text ?? '' ); ?>" class="gmcq-answer-text large-text" style="width:100%"></td>
											<td><button type="button" class="button gmcq-remove-answer"><?php esc_html_e( 'Delete', 'gmcq' ); ?></button></td>
										</tr>
									<?php $ai++; endforeach; ?>
								</tbody>
							</table>
							<button type="button" class="button" id="gmcq-add-answer" style="margin-top:8px">+ <?php esc_html_e( 'Add Option', 'gmcq' ); ?></button>
							<p class="description"><?php esc_html_e( 'Min 2, max 6 answer options.', 'gmcq' ); ?></p>
							<input type="hidden" id="gmcq-answer-count" value="<?php echo (int) $ai; ?>">
						</td>
					</tr>
					<tr id="gmcq-tf-row" style="display:none">
						<th scope="row"><?php esc_html_e( 'True/False Answer', 'gmcq' ); ?></th>
						<td>
							<label><input type="radio" name="true_is_correct" value="1" checked> <?php esc_html_e( 'True', 'gmcq' ); ?></label>
							<label style="margin-left:20px"><input type="radio" name="true_is_correct" value="0"> <?php esc_html_e( 'False', 'gmcq' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-q-explanation"><?php esc_html_e( 'Explanation', 'gmcq' ); ?></label></th>
						<td>
							<?php
							wp_editor(
								$q_expl,
								'gmcq_q_explanation',
								array(
									'textarea_name' => 'explanation',
									'textarea_rows' => 4,
									'media_buttons' => false,
									'teeny'         => true,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Shown after quiz submission.', 'gmcq' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-q-marks"><?php esc_html_e( 'Marks', 'gmcq' ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" name="marks" id="gmcq-q-marks" value="<?php echo esc_attr( $q_marks ); ?>" style="width:100px">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-q-neg-marks"><?php esc_html_e( 'Negative Marks', 'gmcq' ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" name="negative_marks" id="gmcq-q-neg-marks" value="<?php echo esc_attr( $q_neg_marks ); ?>" style="width:100px">
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary" id="gmcq-save-question"><?php echo $is_edit ? esc_html__( 'Update Question', 'gmcq' ) : esc_html__( 'Save Question', 'gmcq' ); ?></button>
					<button type="submit" class="button" id="gmcq-save-add-another" style="display:none"><?php esc_html_e( 'Save & Add Another', 'gmcq' ); ?></button>
					<a href="<?php echo esc_url( $list_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Cancel', 'gmcq' ); ?></a>
				</p>
				<div id="gmcq-form-response" role="alert" aria-live="polite" style="margin-top:15px;padding:10px;display:none;border-left:4px solid transparent"></div>
			</form>
		</div>
	</div>
	<script>
	jQuery(document).ready(function($){
		function updateAnswerInputs(){
			var type = $('#gmcq-q-type').val();
			if (type === 'true_false') {
				$('#gmcq-answers-row, #gmcq-add-answer').hide();
				$('#gmcq-tf-row').show();
				return;
			}
			$('#gmcq-answers-row, #gmcq-add-answer').show();
			$('#gmcq-tf-row').hide();
			var inputType = (type === 'mcq_single') ? 'radio' : 'checkbox';
			$('.gmcq-answer-row').each(function(i){
				var $correct = $(this).find('input.gmcq-correct-input');
				var rowName = (type === 'mcq_single') ? 'answers[is_correct]' : 'answers[is_correct]['+i+']';
				var rowVal = (type === 'mcq_single') ? i : '1';
				$correct.attr('name', rowName).attr('type', inputType).val(rowVal);
			});
		}
		$('#gmcq-q-type').on('change', updateAnswerInputs);
		updateAnswerInputs();
		$('#gmcq-add-answer').on('click', function(){
			var count = parseInt($('#gmcq-answer-count').val());
			if (count >= 6) { alert('<?php echo esc_js( __( 'Maximum 6 answer options.', 'gmcq' ) ); ?>'); return; }
			var type = $('#gmcq-q-type').val();
			var inputType = (type === 'mcq_single') ? 'radio' : 'checkbox';
			var name = (type === 'mcq_single') ? 'answers[is_correct]' : 'answers[is_correct]['+count+']';
			var val = (type === 'mcq_single') ? count : '1';
			var row = '<tr class="gmcq-answer-row" data-idx="' + count + '">' +
				'<td><input type="' + inputType + '" name="' + name + '" value="' + val + '" class="gmcq-correct-input"></td>' +
				'<td><input type="text" name="answers[answer_text][]" class="gmcq-answer-text large-text" style="width:100%"></td>' +
				'<td><button type="button" class="button gmcq-remove-answer"><?php echo esc_js( __( 'Delete', 'gmcq' ) ); ?></button></td>' +
				'</tr>';
			$('#gmcq-answers-table tbody').append(row);
			$('#gmcq-answer-count').val(count + 1);
			if (type === 'mcq_single') updateAnswerInputs(); // Re-sync values
		});
		$('#gmcq-answers-table').on('click', '.gmcq-remove-answer', function(){
			var count = parseInt($('#gmcq-answer-count').val());
			if (count <= 2) { alert('<?php echo esc_js( __( 'Minimum 2 answer options required.', 'gmcq' ) ); ?>'); return; }
			$(this).closest('tr').remove();
			$('#gmcq-answer-count').val(count - 1);
			updateAnswerInputs(); // Re-index everything
		});
		$('#gmcq-question-form').on('submit', function(e){
			e.preventDefault();
			var $b = $(this).find('button[type="submit"]').first().prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'gmcq' ) ); ?>');
			// Serialize answers correctly
			var formData = $(this).serializeArray();
			// wp_editor fields need to be picked up from tinyMCE
			if (typeof tinyMCE !== 'undefined') {
				var qEd = tinyMCE.get('gmcq_q_text');
				if (qEd) formData.push({name: 'question_text', value: qEd.getContent()});
				var eEd = tinyMCE.get('gmcq_q_explanation');
				if (eEd) formData.push({name: 'explanation', value: eEd.getContent()});
			}
			// The form's hidden _ajax_nonce field (from wp_nonce_field) already provides the question nonce.
			// Do NOT append &_ajax_nonce= from gmcqAdmin.nonce here, since gmcqAdmin.nonce is the CATEGORY nonce
			// and would override the question nonce (causing a stuck "Saving..." state).
			$.post(gmcqAdmin.ajaxUrl, $.param(formData) + '&action=gmcq_save_question', function(r){
				if (r.success) {
					$('#gmcq-form-response').css('border-color', '#46b450').html('<p>' + r.data.message + '</p>').fadeIn();
					setTimeout(function(){ window.location.href = '<?php echo esc_js( $list_url ); ?>'; }, 1000);
				} else {
					$('#gmcq-form-response').css('border-color', '#dc3232').html('<p>' + (r.data.message || 'Error') + '</p>').fadeIn();
					$b.prop('disabled', false).text('<?php echo esc_js( $is_edit ? __( 'Update Question', 'gmcq' ) : __( 'Save Question', 'gmcq' ) ); ?>');
				}
			}).fail(function(){
				$('#gmcq-form-response').css('border-color', '#dc3232').html('<p>Server error</p>').fadeIn();
				$b.prop('disabled', false).text('<?php echo esc_js( $is_edit ? __( 'Update Question', 'gmcq' ) : __( 'Save Question', 'gmcq' ) ); ?>');
			});
		});
	});
	</script>
	<?php
}

// ========================================================================
// SECTION 7: AUTO-INIT
// ========================================================================

// Register AJAX handlers and hooks when this file is loaded
gmcq_register_question_ajax_handlers();
gmcq_register_question_hooks();

	
		
