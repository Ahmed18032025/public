<?php
/**
 * GMCQ CSV Import — upload, validate, preview, import, history.
 *
 * Phase 1 scope:
 * - Single-pass import only; no resume/progress persistence columns.
 * - Duplicate detection through normalized question_hash.
 * - Pre-import JSON backup.
 * - Optional assignment to a quiz through gmcq_question_map.
 */
defined( 'ABSPATH' ) || exit;

// ========================================================================
// CONCURRENT IMPORT LOCK
// ========================================================================

function gmcq_acquire_import_lock(): bool|WP_Error {
	$lock_key = 'gmcq_import_lock';
	if ( get_transient( $lock_key ) ) {
		return new WP_Error( 'import_locked', __( 'Another import is currently running. Please wait.', 'gmcq' ) );
	}
	set_transient( $lock_key, get_current_user_id(), 300 );
	return true;
}

function gmcq_release_import_lock(): void {
	delete_transient( 'gmcq_import_lock' );
}

function gmcq_backup_before_import( int $import_id ): string {
	$filename = gmcq_create_backup( 'pre_import', '', array() );

	$backups = get_option( 'gmcq_backup_index', array() );
	$last    = array_key_last( $backups );
	if ( null !== $last ) {
		$backups[ $last ]['import_id'] = $import_id;
		update_option( 'gmcq_backup_index', $backups );
	}

	return $filename;
}

// ========================================================================
// CSV PARSING + VALIDATION
// ========================================================================

function gmcq_csv_required_columns(): array {
	return array( 'question_text', 'option_a', 'option_b', 'correct_answer' );
}

function gmcq_csv_optional_columns(): array {
	return array(
		'option_c',
		'option_d',
		'explanation',
		'difficulty',
		'marks',
		'negative_marks',
		'question_type',
		'category_slug',
	);
}

function gmcq_normalize_csv_header( string $header ): string {
	$header = strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', $header ) ) );
	$header = preg_replace( '/[^a-z0-9]+/', '_', $header );
	return trim( (string) $header, '_' );
}

function gmcq_parse_csv_file( string $filepath ): array|WP_Error {
	if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
		return new WP_Error( 'file_unreadable', __( 'Uploaded CSV file could not be read.', 'gmcq' ) );
	}

	$handle = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $handle ) {
		return new WP_Error( 'file_open_failed', __( 'Unable to open uploaded CSV file.', 'gmcq' ) );
	}

	$headers = fgetcsv( $handle );
	if ( empty( $headers ) || ! is_array( $headers ) ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'missing_header', __( 'CSV header row is missing.', 'gmcq' ) );
	}

	$headers = array_map( 'gmcq_normalize_csv_header', $headers );
	$missing = array_diff( gmcq_csv_required_columns(), $headers );
	if ( ! empty( $missing ) ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error(
			'missing_columns',
			sprintf( __( 'Missing required column(s): %s', 'gmcq' ), implode( ', ', $missing ) )
		);
	}

	$rows       = array();
	$row_number = 1;
	while ( false !== ( $data = fgetcsv( $handle ) ) ) {
		$row_number++;

		if ( 1 === count( $data ) && '' === trim( (string) $data[0] ) ) {
			continue;
		}

		$row = array();
		foreach ( $headers as $index => $header ) {
			$row[ $header ] = isset( $data[ $index ] ) ? trim( (string) $data[ $index ] ) : '';
		}
		$row['_row_number'] = $row_number;
		$rows[]             = $row;
	}

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return array(
		'headers' => $headers,
		'rows'    => $rows,
	);
}

function gmcq_resolve_import_category( string $category_slug, int $fallback_category_id ) {
	$category_slug = trim( $category_slug );
	if ( '' === $category_slug ) {
		return $fallback_category_id > 0 ? $fallback_category_id : new WP_Error( 'category_required', __( 'No category provided for this row.', 'gmcq' ) );
	}

	global $wpdb;
	$p     = $wpdb->prefix;
	$parts = array_values( array_filter( array_map( 'trim', explode( '/', $category_slug ) ) ) );

	if ( empty( $parts ) ) {
		return $fallback_category_id > 0 ? $fallback_category_id : new WP_Error( 'category_required', __( 'No category provided for this row.', 'gmcq' ) );
	}

	if ( count( $parts ) > 2 ) {
		return new WP_Error( 'category_depth', __( 'Category paths support only parent/child depth in Phase 1.', 'gmcq' ) );
	}

	if ( 1 === count( $parts ) ) {
		$slug = sanitize_title( $parts[0] );
		$id   = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$p}gmcq_categories WHERE slug = %s AND is_active = 1 LIMIT 1", $slug )
		);
		return $id > 0 ? $id : new WP_Error( 'category_not_found', sprintf( __( 'Category slug not found: %s', 'gmcq' ), $slug ) );
	}

	$parent_slug = sanitize_title( $parts[0] );
	$child_slug  = sanitize_title( $parts[1] );
	$full_slug   = $parent_slug . '-' . $child_slug;

	$id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT child.id
			 FROM {$p}gmcq_categories child
			 JOIN {$p}gmcq_categories parent ON parent.id = child.parent_id
			 WHERE parent.slug = %s AND child.slug IN (%s, %s)
			 AND parent.is_active = 1 AND child.is_active = 1
			 LIMIT 1",
			$parent_slug,
			$child_slug,
			$full_slug
		)
	);

	return $id > 0 ? $id : new WP_Error( 'category_not_found', sprintf( __( 'Category path not found: %s', 'gmcq' ), $category_slug ) );
}

function gmcq_csv_correct_letters( string $correct_answer ): array {
	$letters = preg_split( '/\s*,\s*/', strtoupper( trim( $correct_answer ) ) );
	$letters = array_values( array_filter( array_unique( $letters ), static function ( $letter ) {
		return in_array( $letter, array( 'A', 'B', 'C', 'D' ), true );
	} ) );
	return $letters;
}

function gmcq_csv_row_to_question_data( array $row, int $target_category_id, int $import_id = 0 ) {
	$category_id = gmcq_resolve_import_category( $row['category_slug'] ?? '', $target_category_id );
	if ( is_wp_error( $category_id ) ) {
		return $category_id;
	}

	$question_type = ! empty( $row['question_type'] ) ? sanitize_key( $row['question_type'] ) : 'mcq_single';
	if ( ! in_array( $question_type, array( 'mcq_single', 'mcq_multiple', 'true_false' ), true ) ) {
		$question_type = 'mcq_single';
	}

	$correct_letters = gmcq_csv_correct_letters( $row['correct_answer'] ?? '' );
	if ( empty( $correct_letters ) ) {
		return new WP_Error( 'invalid_correct_answer', __( 'Correct answer must be A, B, C, D, or comma-separated letters.', 'gmcq' ) );
	}

	if ( count( $correct_letters ) > 1 ) {
		$question_type = 'mcq_multiple';
	}

	$answers = array();
	foreach ( array( 'A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d' ) as $letter => $column ) {
		$text = isset( $row[ $column ] ) ? trim( (string) $row[ $column ] ) : '';
		if ( '' === $text ) {
			continue;
		}
		$answers[] = array(
			'answer_text' => $text,
			'is_correct'  => in_array( $letter, $correct_letters, true ) ? 1 : 0,
		);
	}

	$data = array(
		'category_id'    => (int) $category_id,
		'question_text'  => $row['question_text'] ?? '',
		'question_type'  => $question_type,
		'explanation'    => $row['explanation'] ?? '',
		'difficulty'     => ! empty( $row['difficulty'] ) ? sanitize_key( $row['difficulty'] ) : 'medium',
		'marks'          => isset( $row['marks'] ) && '' !== $row['marks'] ? (float) $row['marks'] : 1.00,
		'negative_marks' => isset( $row['negative_marks'] ) && '' !== $row['negative_marks'] ? (float) $row['negative_marks'] : 0.25,
		'answers'        => $answers,
		'import_id'      => $import_id,
	);

	if ( 'true_false' === $question_type ) {
		$first = $correct_letters[0] ?? 'A';
		$data['true_is_correct'] = in_array( $first, array( 'A', 'TRUE', 'T', '1' ), true ) ? 1 : 0;
	}

	return $data;
}

function gmcq_validate_import_rows( array $rows, int $target_category_id ): array {
	global $wpdb;
	$p = $wpdb->prefix;

	$seen_hashes = array();
	$valid       = 0;
	$dupes       = 0;
	$errors      = 0;
	$preview     = array();
	$normalized  = array();

	foreach ( $rows as $index => $row ) {
		$row_number = (int) ( $row['_row_number'] ?? ( $index + 2 ) );
		$status     = 'valid';
		$message    = __( 'Ready to import.', 'gmcq' );
		$data       = gmcq_csv_row_to_question_data( $row, $target_category_id, 0 );

		if ( is_wp_error( $data ) ) {
			$status  = 'error';
			$message = $data->get_error_message();
		} else {
			$validation = gmcq_validate_question_data( $data, 'create' );
			if ( is_wp_error( $validation ) ) {
				$status  = 'error';
				$message = $validation->get_error_message();
			} else {
				$hash = gmcq_generate_question_hash( $data['question_text'] );
				$exists = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE question_hash = %s", $hash )
				);

				if ( $exists > 0 || isset( $seen_hashes[ $hash ] ) ) {
					$status  = 'duplicate';
					$message = __( 'Duplicate question hash; this row will be skipped.', 'gmcq' );
				} else {
					$seen_hashes[ $hash ] = true;
					$data['_row_number']  = $row_number;
					$normalized[]         = $data;
				}
			}
		}

		if ( 'valid' === $status ) {
			$valid++;
		} elseif ( 'duplicate' === $status ) {
			$dupes++;
		} else {
			$errors++;
		}

		if ( count( $preview ) < 5 ) {
			$preview[] = array(
				'row'           => $row_number,
				'question_text' => $row['question_text'] ?? '',
				'status'        => $status,
				'message'       => $message,
			);
		}
	}

	return array(
		'total_rows' => count( $rows ),
		'valid'      => $valid,
		'duplicates' => $dupes,
		'errors'     => $errors,
		'preview'    => $preview,
		'rows'       => $normalized,
	);
}

// ========================================================================
// IMPORT EXECUTION
// ========================================================================

function gmcq_create_import_record( string $filename, int $total_rows, int $target_category_id, int $target_quiz_id ) {
	global $wpdb;
	$inserted = $wpdb->insert(
		$wpdb->prefix . 'gmcq_imports',
		array(
			'filename'           => sanitize_file_name( $filename ),
			'total_rows'         => $total_rows,
			'status'             => 'pending',
			'target_category_id' => $target_category_id > 0 ? $target_category_id : null,
			'target_quiz_id'     => $target_quiz_id > 0 ? $target_quiz_id : null,
			'user_id'            => get_current_user_id(),
		),
		array( '%s', '%d', '%s', '%d', '%d', '%d' )
	);

	if ( false === $inserted ) {
		return new WP_Error( 'import_record_failed', $wpdb->last_error ?: __( 'Failed to create import record.', 'gmcq' ) );
	}

	return (int) $wpdb->insert_id;
}

function gmcq_assign_imported_question_to_quiz( int $question_id, int $quiz_id ): bool|WP_Error {
	if ( $question_id <= 0 || $quiz_id <= 0 ) {
		return true;
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$sort_order = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$p}gmcq_question_map WHERE quiz_id = %d", $quiz_id )
	);

	$inserted = $wpdb->insert(
		$p . 'gmcq_question_map',
		array(
			'quiz_id'     => $quiz_id,
			'question_id' => $question_id,
			'sort_order'  => $sort_order,
		),
		array( '%d', '%d', '%d' )
	);

	if ( false === $inserted ) {
		if ( false !== stripos( $wpdb->last_error, 'Duplicate' ) ) {
			return true;
		}
		return new WP_Error( 'quiz_map_failed', $wpdb->last_error ?: __( 'Failed to assign question to quiz.', 'gmcq' ) );
	}

	do_action( 'gmcq_question_added_to_quiz', $question_id, $quiz_id );
	return true;
}

function gmcq_run_csv_import( array $payload ): array|WP_Error {
	global $wpdb;
	$p = $wpdb->prefix;

	$lock = gmcq_acquire_import_lock();
	if ( is_wp_error( $lock ) ) {
		return $lock;
	}

	$import_id       = 0;
	$imported        = 0;
	$skipped_dupes   = (int) ( $payload['duplicates'] ?? 0 );
	$skipped_errors  = (int) ( $payload['errors'] ?? 0 );
	$error_log       = array();
	$target_quiz_id  = (int) ( $payload['target_quiz_id'] ?? 0 );
	$target_category = (int) ( $payload['target_category_id'] ?? 0 );

	try {
		$import_id = gmcq_create_import_record(
			$payload['filename'] ?? 'import.csv',
			(int) ( $payload['total_rows'] ?? 0 ),
			$target_category,
			$target_quiz_id
		);

		if ( is_wp_error( $import_id ) ) {
			throw new Exception( $import_id->get_error_message() );
		}

		if ( (int) gmcq_get_setting( 'backup_enabled', 1 ) ) {
			gmcq_backup_before_import( (int) $import_id );
		}

		$wpdb->update(
			$p . 'gmcq_imports',
			array( 'status' => 'processing' ),
			array( 'id' => (int) $import_id ),
			array( '%s' ),
			array( '%d' )
		);

		foreach ( (array) ( $payload['rows'] ?? array() ) as $row ) {
			$row['_row_number'] = (int) ( $row['_row_number'] ?? 0 );
			$row['import_id']   = (int) $import_id;

			$result = gmcq_create_question( $row );
			if ( is_wp_error( $result ) ) {
				if ( 'duplicate_question' === $result->get_error_code() ) {
					$skipped_dupes++;
				} else {
					$skipped_errors++;
				}
				$error_log[] = array(
					'row'     => $row['_row_number'],
					'message' => $result->get_error_message(),
				);
				continue;
			}

			$question_id = (int) $result;
			if ( $target_quiz_id > 0 ) {
				$map = gmcq_assign_imported_question_to_quiz( $question_id, $target_quiz_id );
				if ( is_wp_error( $map ) ) {
					$error_log[] = array(
						'row'     => $row['_row_number'],
						'message' => $map->get_error_message(),
					);
				}
			}

			$imported++;
		}

		$wpdb->update(
			$p . 'gmcq_imports',
			array(
				'imported'       => $imported,
				'skipped_dupes'  => $skipped_dupes,
				'skipped_errors' => $skipped_errors,
				'status'         => 'completed',
				'error_log'      => wp_json_encode( $error_log ),
				'completed_at'   => current_time( 'mysql' ),
			),
			array( 'id' => (int) $import_id ),
			array( '%d', '%d', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'gmcq_import_completed', (int) $import_id );
		gmcq_clear_dashboard_cache( 'import' );

		return array(
			'import_id'       => (int) $import_id,
			'imported'        => $imported,
			'skipped_dupes'   => $skipped_dupes,
			'skipped_errors'  => $skipped_errors,
			'errors'          => $error_log,
		);
	} catch ( Exception $e ) {
		if ( $import_id > 0 ) {
			$wpdb->update(
				$p . 'gmcq_imports',
				array(
					'status'       => 'failed',
					'error_log'    => wp_json_encode( array( array( 'message' => $e->getMessage() ) ) ),
					'completed_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $import_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
		return new WP_Error( 'import_failed', $e->getMessage() );
	} finally {
		gmcq_release_import_lock();
	}
}

function gmcq_get_import_history( int $limit = 20 ): array {
	global $wpdb;
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT i.*, u.display_name
			 FROM {$wpdb->prefix}gmcq_imports i
			 LEFT JOIN {$wpdb->users} u ON u.ID = i.user_id
			 ORDER BY i.started_at DESC
			 LIMIT %d",
			$limit
		)
	) ?: array();
}

// ========================================================================
// POST HANDLERS
// ========================================================================

function gmcq_handle_import_upload(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'gmcq' ) );
	}

	check_admin_referer( 'gmcq_import_upload' );

	if ( empty( $_FILES['gmcq_csv_file']['tmp_name'] ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'gmcq-import', 'gmcq_error' => rawurlencode( __( 'Please choose a CSV file.', 'gmcq' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$file = $_FILES['gmcq_csv_file'];
	$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : 'import.csv';

	if ( ! empty( $file['error'] ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'gmcq-import', 'gmcq_error' => rawurlencode( __( 'File upload failed.', 'gmcq' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( 'csv' !== $ext ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'gmcq-import', 'gmcq_error' => rawurlencode( __( 'Only CSV files are allowed.', 'gmcq' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$target_category_id = isset( $_POST['target_category_id'] ) ? (int) $_POST['target_category_id'] : 0;
	$target_quiz_id     = isset( $_POST['target_quiz_id'] ) ? (int) $_POST['target_quiz_id'] : 0;

	$parsed = gmcq_parse_csv_file( $file['tmp_name'] );
	if ( is_wp_error( $parsed ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'gmcq-import', 'gmcq_error' => rawurlencode( $parsed->get_error_message() ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$validation = gmcq_validate_import_rows( $parsed['rows'], $target_category_id );
	$token      = wp_generate_password( 24, false, false );
	$payload    = array(
		'filename'           => $name,
		'target_category_id' => $target_category_id,
		'target_quiz_id'     => $target_quiz_id,
		'total_rows'         => $validation['total_rows'],
		'valid'              => $validation['valid'],
		'duplicates'         => $validation['duplicates'],
		'errors'             => $validation['errors'],
		'preview'            => $validation['preview'],
		'rows'               => $validation['rows'],
	);

	set_transient( 'gmcq_import_preview_' . $token, $payload, 30 * MINUTE_IN_SECONDS );

	wp_safe_redirect( add_query_arg( array( 'page' => 'gmcq-import', 'preview' => $token ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_gmcq_import_upload', 'gmcq_handle_import_upload' );

function gmcq_handle_import_confirm(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'gmcq' ) );
	}

	check_admin_referer( 'gmcq_import_confirm' );

	$token   = isset( $_POST['preview_token'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_token'] ) ) : '';
	$payload = $token ? get_transient( 'gmcq_import_preview_' . $token ) : false;

	if ( false === $payload || ! is_array( $payload ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'gmcq-import', 'gmcq_error' => rawurlencode( __( 'Import preview expired. Please upload the CSV again.', 'gmcq' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$result = gmcq_run_csv_import( $payload );
	delete_transient( 'gmcq_import_preview_' . $token );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'gmcq-import', 'gmcq_error' => rawurlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'      => 'gmcq-import',
				'import_id' => (int) $result['import_id'],
				'imported'  => (int) $result['imported'],
				'dupes'     => (int) $result['skipped_dupes'],
				'errors'    => (int) $result['skipped_errors'],
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_gmcq_import_confirm', 'gmcq_handle_import_confirm' );

add_action(
	'gmcq_import_completed',
	static function ( int $import_id ): void {
		global $wpdb;
		$import = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gmcq_imports WHERE id = %d", $import_id )
		);

		if ( function_exists( 'gmcq_recalculate_category_counts' ) ) {
			gmcq_recalculate_category_counts();
		}
		if ( function_exists( 'gmcq_recalculate_usage_counts' ) ) {
			gmcq_recalculate_usage_counts();
		}
		if ( $import && ! empty( $import->target_quiz_id ) ) {
			do_action( 'gmcq_quiz_questions_changed', (int) $import->target_quiz_id );
		}
	},
	10,
	1
);

// ========================================================================
// ADMIN UI
// ========================================================================

function gmcq_get_import_quiz_options(): array {
	return get_posts(
		array(
			'post_type'      => 'gmcq_quiz',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	) ?: array();
}

function gmcq_render_import_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	$preview_token = isset( $_GET['preview'] ) ? sanitize_text_field( wp_unslash( $_GET['preview'] ) ) : '';
	$preview       = $preview_token ? get_transient( 'gmcq_import_preview_' . $preview_token ) : false;
	$cats          = gmcq_get_categories( array( 'filter' => 'active', 'per_page' => -1 ) );
	$quizzes       = gmcq_get_import_quiz_options();
	$history       = gmcq_get_import_history( 20 );
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php printf( '<a href="%s">%s</a> &rsaquo; %s', esc_url( admin_url( 'admin.php?page=gmcq-dashboard' ) ), esc_html__( 'GMCQ', 'gmcq' ), esc_html__( 'CSV Import', 'gmcq' ) ); ?></h1>

		<?php if ( isset( $_GET['gmcq_error'] ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['gmcq_error'] ) ) ); ?></p></div>
		<?php endif; ?>

		<?php if ( isset( $_GET['import_id'] ) ) : ?>
			<div class="notice notice-success"><p>
				<?php
				printf(
					esc_html__( 'Import #%1$d completed. Imported: %2$d, duplicates skipped: %3$d, errors skipped: %4$d.', 'gmcq' ),
					(int) $_GET['import_id'],
					isset( $_GET['imported'] ) ? (int) $_GET['imported'] : 0,
					isset( $_GET['dupes'] ) ? (int) $_GET['dupes'] : 0,
					isset( $_GET['errors'] ) ? (int) $_GET['errors'] : 0
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-questions' ) ); ?>"><?php esc_html_e( 'View Questions', 'gmcq' ); ?></a>
			</p></div>
		<?php endif; ?>

		<?php if ( is_array( $preview ) ) : ?>
			<?php gmcq_render_import_preview( $preview_token, $preview ); ?>
		<?php else : ?>
			<div class="gmcq-card" style="max-width:900px">
				<h2><?php esc_html_e( 'Upload CSV', 'gmcq' ); ?></h2>
				<p><?php esc_html_e( 'Upload a CSV file to validate and preview before importing. Required columns: question_text, option_a, option_b, correct_answer.', 'gmcq' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="gmcq_import_upload">
					<?php wp_nonce_field( 'gmcq_import_upload' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="gmcq_csv_file"><?php esc_html_e( 'CSV File', 'gmcq' ); ?> <span style="color:red">*</span></label></th>
							<td><input type="file" name="gmcq_csv_file" id="gmcq_csv_file" accept=".csv,text/csv" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="target_category_id"><?php esc_html_e( 'Target Category', 'gmcq' ); ?></label></th>
							<td>
								<select name="target_category_id" id="target_category_id">
									<option value="0"><?php esc_html_e( '— Select if CSV rows do not use category_slug —', 'gmcq' ); ?></option>
									<?php foreach ( $cats['categories'] as $cat ) : ?>
										<option value="<?php echo (int) $cat->id; ?>"><?php echo esc_html( ( ! empty( $cat->parent_id ) ? '— ' : '' ) . $cat->name . ' (' . $cat->slug . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Questions must resolve to a leaf category. Row-level category_slug overrides this value.', 'gmcq' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="target_quiz_id"><?php esc_html_e( 'Assign to Quiz', 'gmcq' ); ?></label></th>
							<td>
								<select name="target_quiz_id" id="target_quiz_id">
									<option value="0"><?php esc_html_e( 'Do not assign to quiz', 'gmcq' ); ?></option>
									<?php foreach ( $quizzes as $quiz ) : ?>
										<option value="<?php echo (int) $quiz->ID; ?>"><?php echo esc_html( $quiz->post_title ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Validate & Preview', 'gmcq' ); ?></button></p>
				</form>
			</div>
		<?php endif; ?>

		<?php gmcq_render_import_format_help(); ?>
		<?php gmcq_render_import_history( $history ); ?>
	</div>
	<?php
}

function gmcq_render_import_preview( string $token, array $preview ): void {
	?>
	<div class="gmcq-card" style="max-width:1000px">
		<h2><?php esc_html_e( 'Preview & Validate', 'gmcq' ); ?></h2>
		<p><strong><?php echo esc_html( $preview['filename'] ?? '' ); ?></strong></p>
		<ul style="display:flex;gap:20px;list-style:none;margin-left:0">
			<li><strong><?php echo (int) $preview['total_rows']; ?></strong> <?php esc_html_e( 'total rows', 'gmcq' ); ?></li>
			<li><strong style="color:#46b450"><?php echo (int) $preview['valid']; ?></strong> <?php esc_html_e( 'valid', 'gmcq' ); ?></li>
			<li><strong style="color:#ffb900"><?php echo (int) $preview['duplicates']; ?></strong> <?php esc_html_e( 'duplicates', 'gmcq' ); ?></li>
			<li><strong style="color:#dc3232"><?php echo (int) $preview['errors']; ?></strong> <?php esc_html_e( 'errors', 'gmcq' ); ?></li>
		</ul>

		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th style="width:80px"><?php esc_html_e( 'Row', 'gmcq' ); ?></th><th><?php esc_html_e( 'Question', 'gmcq' ); ?></th><th style="width:120px"><?php esc_html_e( 'Status', 'gmcq' ); ?></th><th><?php esc_html_e( 'Message', 'gmcq' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( (array) $preview['preview'] as $row ) : ?>
					<tr>
						<td><?php echo (int) $row['row']; ?></td>
						<td><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $row['question_text'] ), 18 ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $row['status'] ) ); ?></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:15px">
			<input type="hidden" name="action" value="gmcq_import_confirm">
			<input type="hidden" name="preview_token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( 'gmcq_import_confirm' ); ?>
			<button type="submit" class="button button-primary" <?php disabled( empty( $preview['valid'] ) ); ?>><?php esc_html_e( 'Import Valid Rows', 'gmcq' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-import' ) ); ?>"><?php esc_html_e( 'Cancel', 'gmcq' ); ?></a>
		</form>
	</div>
	<?php
}

function gmcq_render_import_format_help(): void {
	?>
	<div class="gmcq-card" style="max-width:1000px">
		<h2><?php esc_html_e( 'CSV Format', 'gmcq' ); ?></h2>
		<p><code>question_text,option_a,option_b,option_c,option_d,correct_answer,explanation,difficulty,marks,negative_marks,question_type,category_slug</code></p>
		<p class="description"><?php esc_html_e( 'correct_answer accepts A, B, C, D, or comma-separated values like A,C. category_slug can be a slug or parent/child path.', 'gmcq' ); ?></p>
	</div>
	<?php
}

function gmcq_render_import_history( array $history ): void {
	?>
	<div class="gmcq-card" style="max-width:1000px">
		<h2><?php esc_html_e( 'Import History', 'gmcq' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'File', 'gmcq' ); ?></th><th><?php esc_html_e( 'Status', 'gmcq' ); ?></th><th><?php esc_html_e( 'Rows', 'gmcq' ); ?></th><th><?php esc_html_e( 'Imported', 'gmcq' ); ?></th><th><?php esc_html_e( 'Skipped', 'gmcq' ); ?></th><th><?php esc_html_e( 'User', 'gmcq' ); ?></th><th><?php esc_html_e( 'Started', 'gmcq' ); ?></th></tr></thead>
			<tbody>
				<?php if ( empty( $history ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No imports yet.', 'gmcq' ); ?></td></tr>
				<?php else : foreach ( $history as $import ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $import->filename ); ?></strong><br><span class="description">#<?php echo (int) $import->id; ?></span></td>
						<td><?php echo esc_html( ucfirst( $import->status ) ); ?></td>
						<td><?php echo (int) $import->total_rows; ?></td>
						<td><?php echo (int) $import->imported; ?></td>
						<td><?php echo (int) $import->skipped_dupes; ?> <?php esc_html_e( 'dupes', 'gmcq' ); ?> / <?php echo (int) $import->skipped_errors; ?> <?php esc_html_e( 'errors', 'gmcq' ); ?></td>
						<td><?php echo esc_html( $import->display_name ?: __( 'Unknown', 'gmcq' ) ); ?></td>
						<td><?php echo esc_html( $import->started_at ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
