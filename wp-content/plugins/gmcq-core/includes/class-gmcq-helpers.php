<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Minimal wp_strip_all_tags() fallback for non-WordPress environments.
	 *
	 * @param string $text The string to strip tags from.
	 * @return string Stripped string.
	 */
	function wp_strip_all_tags( string $text ): string {
		return preg_replace( '#<[^>]+>#', '', $text );
	}
}

function gmcq_get_default_settings(): array {
	return array(
		'activity_retention_days'     => 90,
		'attempt_retention_days'      => 365,
		'enable_auto_purge'           => 0,
		'dashboard_cache_ttl'         => 300,
		'health_cache_ttl'            => 600,
		'integrity_cache_ttl'         => 900,
		'reports_cache_ttl'           => 300,
		'max_questions_per_quiz'      => 200,
		'max_attempts_per_ip_per_day' => 50,
		'search_min_query_length'     => 3,
		'search_cache_ttl'            => 300,
		'search_max_per_page'         => 100,
		'backup_enabled'              => 1,
		'backup_retention_days'       => 90,
		'max_backup_files'            => 50,
		'quiz_slug'                   => 'quiz',
		'uninstall_behavior'          => 'keep',
		'enable_question_tags'        => 0,
	);
}

global $gmcq_settings_cache;
$gmcq_settings_cache = null;

function gmcq_get_setting( string $key, $fallback = null ) {
	global $gmcq_settings_cache;

	if ( null === $gmcq_settings_cache ) {
		$gmcq_settings_cache = wp_parse_args(
			get_option( 'gmcq_settings', array() ),
			gmcq_get_default_settings()
		);
	}

	return $gmcq_settings_cache[ $key ] ?? $fallback;
}

function gmcq_reset_settings_cache(): void {
	global $gmcq_settings_cache;
	$gmcq_settings_cache = null;
}

/**
 * Generate a normalized hash for a question to detect duplicates.
 *
 * Normalization steps:
 * 1. Strip HTML tags
 * 2. Remove punctuation
 * 3. Collapse multiple spaces to single space
 * 4. Trim whitespace
 * 5. Convert to lowercase
 * 6. Generate MD5 hash
 *
 * @param string $question_text The question text to hash.
 * @return string MD5 hash of normalized question text.
 */
function gmcq_generate_question_hash( string $question_text ): string {
	// Strip HTML tags
	$text = wp_strip_all_tags( $question_text );

	// Remove punctuation, keeping only word characters and spaces
	$text = preg_replace( '/[^\w\s]/u', '', $text );

	// Collapse multiple spaces to single space
	$text = preg_replace( '/\s+/', ' ', $text );

	// Trim whitespace
	$text = trim( $text );

	// Convert to lowercase
	$text = mb_strtolower( $text, 'UTF-8' );

	// Generate MD5 hash
	return md5( $text );
}

/**
 * Clear dashboard-related caches selectively by entity type.
 *
 * Entity types:
 * - 'category': Clears category-related caches
 * - 'question': Clears question-related caches
 * - 'quiz': Clears quiz-related caches
 * - 'import': Clears import-related caches
 * - 'all': Clears all dashboard caches (default)
 *
 * @param string $entity_type The entity type to clear cache for.
 * @return void
 */
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
			delete_transient( 'gmcq_question_filter_counts' );
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
			delete_transient( 'gmcq_question_filter_counts' );
			break;

		default:
			// Clear all dashboard caches
			delete_transient( 'gmcq_dashboard_stats' );
			delete_transient( 'gmcq_system_health' );
			delete_transient( 'gmcq_data_integrity' );
			delete_transient( 'gmcq_category_stats' );
			delete_transient( 'gmcq_category_tree_counts' );
			delete_transient( 'gmcq_duplicate_count' );
			delete_transient( 'gmcq_question_filter_counts' );
			delete_transient( 'gmcq_top_quizzes' );
			delete_transient( 'gmcq_recent_quizzes' );
			break;
	}
}

/**
 * Create a backup of questions, answers, or quizzes data.
 *
 * Backup types:
 * - 'pre_import': Backs up all existing questions and answers before import
 * - 'pre_bulk_question': Backs up specific questions and their answers
 * - 'pre_bulk_quiz': Backs up specific quizzes and their question mappings
 *
 * Backups are stored as JSON files in wp-content/uploads/gmcq-backups/
 * An index is maintained in wp_options for tracking and cleanup.
 *
 * @param string $type The type of backup ('pre_import', 'pre_bulk_question', 'pre_bulk_quiz').
 * @param string $entity_type The entity type being backed up ('', 'question', 'quiz').
 * @param array  $ids Optional array of entity IDs to back up (used for pre_bulk_* types).
 * @return string The filename of the created backup.
 */
function gmcq_create_backup( string $type, string $entity_type = '', array $ids = array() ): string {
	global $wpdb;
	$p = $wpdb->prefix;

	// Ensure backup directory exists
	$backup_dir = wp_upload_dir()['basedir'] . '/gmcq-backups';
	if ( ! file_exists( $backup_dir ) ) {
		wp_mkdir_p( $backup_dir );
	}

	// Initialize backup data structure
	$data = array(
		'type'      => $type,
		'entity'    => $entity_type,
		'timestamp' => current_time( 'mysql' ),
	);

	// Gather backup data based on type and entity
	switch ( $type ) {
		case 'pre_import':
			// Backup all existing questions and answers before import
			$data['questions'] = $wpdb->get_results( "SELECT * FROM {$p}gmcq_questions" );
			$data['answers']   = $wpdb->get_results( "SELECT * FROM {$p}gmcq_answers" );
			break;

		case 'pre_bulk_question':
			// Backup specific questions and their answers
			$data['questions'] = array();
			$data['answers']   = array();
			if ( ! empty( $ids ) ) {
				$id_list           = implode( ',', array_map( 'intval', $ids ) );
				$data['questions'] = $wpdb->get_results( "SELECT * FROM {$p}gmcq_questions WHERE id IN ({$id_list})" );
				$data['answers']   = $wpdb->get_results( "SELECT * FROM {$p}gmcq_answers WHERE question_id IN ({$id_list})" );
			}
			break;

		case 'pre_bulk_quiz':
			// Backup specific quizzes and their question mappings
			$data['quizzes'] = array();
			$data['map']     = array();
			if ( ! empty( $ids ) ) {
				$id_list         = implode( ',', array_map( 'intval', $ids ) );
				$data['quizzes'] = $wpdb->get_results( "SELECT * FROM {$p}gmcq_quizzes_meta WHERE quiz_id IN ({$id_list})" );
				$data['map']     = $wpdb->get_results( "SELECT * FROM {$p}gmcq_question_map WHERE quiz_id IN ({$id_list})" );
			}
			break;
	}

	// Generate unique filename
	$filename = 'gmcq-backup-' . $type . ( $entity_type ? '-' . $entity_type : '' ) . '-' . gmdate( 'Y-m-d-His' ) . '.json';

	// Write backup to file
	$filepath = $backup_dir . '/' . $filename;
	file_put_contents( $filepath, wp_json_encode( $data, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	// Update backup index in wp_options for tracking and cleanup
	$backups   = get_option( 'gmcq_backup_index', array() );
	$backups[] = array(
		'id'      => uniqid(),
		'type'    => $type,
		'entity'  => $entity_type,
		'file'    => $filename,
		'created' => current_time( 'mysql' ),
		'count'   => count( $ids ),
	);
	update_option( 'gmcq_backup_index', $backups );

	return $filename;
}

/**
 * Validate that a category ID is a leaf category (has no children).
 *
 * In the GMCQ system, questions must reference leaf categories.
 * This function verifies that a category has no children.
 *
 * @param int $category_id The category ID to validate.
 * @return bool|WP_Error True if valid leaf category, WP_Error if invalid.
 */
function gmcq_validate_question_category( int $category_id ): bool|\WP_Error {
	// Reject invalid or missing category IDs
	if ( 0 === $category_id || 0 > $category_id ) {
		return new \WP_Error( 'invalid_category_id', 'Invalid category ID.' );
	}

	global $wpdb;
	$p = $wpdb->prefix;

	// Check if category exists and is active
	$category = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, parent_id, is_active FROM {$p}gmcq_categories WHERE id = %d",
			$category_id
		)
	);

	if ( ! $category ) {
		return new \WP_Error( 'category_not_found', 'Category does not exist.' );
	}

	if ( 1 !== (int) $category->is_active ) {
		return new \WP_Error( 'category_inactive', 'Category is inactive and cannot be used for questions.' );
	}

	$has_children = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id = %d",
			$category_id
		)
	);

	if ( 0 < $has_children ) {
		return new \WP_Error( 'category_not_leaf', 'Questions must be assigned to leaf categories only.' );
	}

	return true;
}
