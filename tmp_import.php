<?php
/**
 * GMCQ CSV Import — upload, validate, preview, import, history.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_parse_csv_row( array $row, int $category_id, int $import_id = 0 ): array|WP_Error {
	if ( empty( $row['question'] ) ) {
		return new WP_Error( 'empty_question', __( 'Question text is empty.', 'gmcq' ) );
	}

	$answers = array();
	for ( $i = 1; $i <= 6; $i++ ) {
		$key = 'option_' . $i;
		if ( ! empty( $row[ $key ] ) ) {
			$answers[] = array(
				'answer_text' => sanitize_text_field( $row[ $key ] ),
				'is_correct'  => ! empty( $row[ 'correct_' . $i ] ) ? 1 : 0,
			);
		}
	}

	if ( count( $answers ) < 2 && ( $row['type'] ?? 'mcq_single' ) !== 'true_false' ) {
		return new WP_Error( 'min_answers', __( 'At least 2 options required.', 'gmcq' ) );
	}

	return array(
		'category_id'    => $category_id,
		'question_text'  => wp_kses_post( $row['question'] ),
		'question_type'  => sanitize_key( $row['type'] ?? 'mcq_single' ),
		'explanation'    => isset( $row['explanation'] ) ? wp_kses_post( $row['explanation'] ) : '',
		'difficulty'     => sanitize_key( $row['difficulty'] ?? 'medium' ),
		'marks'          => isset( $row['marks'] ) ? (float) $row['marks'] : 1.00,
		'negative_marks' => isset( $row['negative_marks'] ) ? (float) $row['negative_marks'] : 0.25,
		'import_id'      => $import_id,
		'answers'        => $answers,
	);
}

function gmcq_run_csv_import( string $filepath, int $category_id, int $target_quiz_id = 0, string $filename = '' ): array|WP_Error {
	if ( ! file_exists( $filepath ) ) {
		return new WP_Error( 'file_missing', __( 'Import file not found.', 'gmcq' ) );
	}

	global $wpdb;
	$user_id = get_current_user_id();

	$wpdb->insert(
		$wpdb->prefix . 'gmcq_imports',
		array(
			'filename'           => $filename ?: basename( $filepath ),
			'status'             => 'processing',
			'target_category_id' => $category_id,
			'target_quiz_id'     => $target_quiz_id ?: null,
			'user_id'            => $user_id,
		),
		array( '%s', '%s', '%d', '%d', '%d' )
	);
	$import_id = (int) $wpdb->insert_id;

	if ( gmcq_get_setting( 'backup_enabled', 1 ) ) {
		gmcq_create_backup( 'pre_import', '', array() );
	}

	$handle = fopen( $filepath, 'r' );
	if ( ! $handle ) {
		return new WP_Error( 'file_read', __( 'Could not read CSV file.', 'gmcq' ) );
	}

	$header = fgetcsv( $handle );
	if ( ! $header ) {
		fclose( $handle );
		return new WP_Error( 'empty_csv', __( 'CSV file is empty.', 'gmcq' ) );
	}

	$header = array_map( 'strtolower', array_map( 'trim', $header ) );
	$map    = array_flip( $header );

	$imported = 0;
	$dupes    = 0;
	$errors   = 0;
	$error_log = array();
	$total    = 0;
	$new_question_ids = array();

	while ( ( $line = fgetcsv( $handle ) ) !== false ) {
		$total++;
		$row = array();
		foreach ( $map as $col => $idx ) {
			$row[ $col ] = $line[ $idx ] ?? '';
		}

		$parsed = gmcq_parse_csv_row( $row, $category_id, $import_id );
		if ( is_wp_error( $parsed ) ) {
			$errors++;
			if ( count( $error_log ) < 100 ) {
				$error_log[] = array( 'row' => $total, 'message' => $parsed->get_error_message() );
			}
			continue;
		}

		$result = gmcq_create_question( $parsed );
		if ( is_wp_error( $result ) ) {
			if ( 'duplicate_question' === $result->get_error_code() ) {
				$dupes++;
			} else {
				$errors++;
				if ( count( $error_log ) < 100 ) {
					$error_log[] = array( 'row' => $total, 'message' => $result->get_error_message() );
				}
			}
			continue;
		}

		$imported++;
		$new_question_ids[] = (int) $result;
	}
	fclose( $handle );

	if ( $target_quiz_id && ! empty( $new_question_ids ) ) {
		$existing = wp_list_pluck( gmcq_get_quiz_questions( $target_quiz_id ), 'question_id' );
		gmcq_set_quiz_questions( $target_quiz_id, array_merge( $existing, $new_question_ids ) );
	}

	$status = $errors > 0 && 0 === $imported ? 'failed' : 'completed';

	$wpdb->update(
		$wpdb->prefix . 'gmcq_imports',
		array(
			'total_rows'      => $total,
			'imported'        => $imported,
			'skipped_dupes'   => $dupes,
			'skipped_errors'  => $errors,
			'status'          => $status,
			'error_log'       => wp_json_encode( $error_log ),
			'completed_at'    => current_time( 'mysql' ),
		),
		array( 'id' => $import_id ),
		array( '%d', '%d', '%d', '%d', '%s', '%s', '%s' ),
		array( '%d' )
	);

	do_action( 'gmcq_import_completed', $import_id );
	gmcq_clear_dashboard_cache( 'import' );

	return array(
		'import_id'  => $import_id,
		'total'      => $total,
		'imported'   => $imported,
		'dupes'      => $dupes,
		'errors'     => $errors,
		'error_log'  => $error_log,
	);
}

function gmcq_register_import_completed_hook(): void {
	add_action(
		'gmcq_import_completed',
		static function ( int $import_id ): void {
			global $wpdb;
			$import = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gmcq_imports WHERE id = %d",
					$import_id
				)
			);
			gmcq_recalculate_category_counts();
			gmcq_recalculate_usage_counts();
			if ( $import && $import->target_quiz_id ) {
				do_action( 'gmcq_quiz_questions_changed', (int) $import->target_quiz_id );
			}
		}
	);
}
gmcq_register_import_completed_hook();

function gmcq_get_imports( array $args = array() ): array {
	global $wpdb;
	$filter = isset( $args['filter'] ) ? sanitize_key( $args['filter'] ) : '';
	$where  = '1=1';
	if ( 'failed' === $filter ) {
		$where = "status = 'failed'";
	}
	return $wpdb->get_results(
		"SELECT * FROM {$wpdb->prefix}gmcq_imports WHERE {$where} ORDER BY started_at DESC LIMIT 50"
	) ?: array();
}

function gmcq_register_import_ajax_handlers(): void {
	add_action( 'wp_ajax_gmcq_run_import', 'gmcq_ajax_run_import' );
}

function gmcq_ajax_run_import(): void {
	check_ajax_referer( 'gmcq_import_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	if ( empty( $_FILES['csv_file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'gmcq' ) ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$upload = wp_handle_upload(
		$_FILES['csv_file'],
		array( 'test_form' => false, 'mimes' => array( 'csv' => 'text/csv' ) )
	);

	if ( ! empty( $upload['error'] ) ) {
		wp_send_json_error( array( 'message' => $upload['error'] ) );
	}

	$category_id    = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$target_quiz_id = isset( $_POST['target_quiz_id'] ) ? (int) $_POST['target_quiz_id'] : 0;

	if ( $category_id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Target category is required.', 'gmcq' ) ) );
	}

	$result = gmcq_run_csv_import( $upload['file'], $category_id, $target_quiz_id, basename( $_FILES['csv_file']['name'] ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( $result );
}

function gmcq_render_import_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : '';
	$imports = gmcq_get_imports( array( 'filter' => $filter ) );
	$cats = gmcq_get_categories( array( 'filter' => 'active', 'per_page' => -1 ) );
	$quizzes = gmcq_get_quizzes( array( 'filter' => 'all', 'per_page' => 100 ) );
	$nonce = wp_create_nonce( 'gmcq_import_nonce' );
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php esc_html_e( 'CSV Import', 'gmcq' ); ?></h1>
		<div class="gmcq-card" style="max-width:800px">
			<h2><?php esc_html_e( 'Upload CSV', 'gmcq' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Expected columns: question, option_1..option_6, correct_1..correct_6, type, difficulty, explanation, marks, negative_marks', 'gmcq' ); ?></p>
			<form id="gmcq-import-form" enctype="multipart/form-data">
				<table class="form-table">
					<tr><th><?php esc_html_e( 'CSV File', 'gmcq' ); ?></th>
					<td><input type="file" name="csv_file" accept=".csv" required></td></tr>
					<tr><th><?php esc_html_e( 'Target Category', 'gmcq' ); ?></th>
					<td><select name="category_id" required><option value=""><?php esc_html_e( 'Select', 'gmcq' ); ?></option>
					<?php foreach ( $cats['categories'] as $c ) : ?>
						<option value="<?php echo (int) $c->id; ?>"><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Add to Quiz (optional)', 'gmcq' ); ?></th>
					<td><select name="target_quiz_id"><option value="0"><?php esc_html_e( 'None', 'gmcq' ); ?></option>
					<?php foreach ( $quizzes['quizzes'] as $q ) : ?>
						<option value="<?php echo (int) $q->quiz_id; ?>"><?php echo esc_html( $q->post_title ); ?></option>
					<?php endforeach; ?></select></td></tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'gmcq' ); ?></button></p>
			</form>
			<div id="gmcq-import-result"></div>
		</div>
		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Import History', 'gmcq' ); ?></h2>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-import&filter=failed' ) ); ?>"><?php esc_html_e( 'Failed imports', 'gmcq' ); ?></a></p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'File', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Status', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Imported', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Dupes', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Errors', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Date', 'gmcq' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $imports ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No imports yet.', 'gmcq' ); ?></td></tr>
				<?php else : foreach ( $imports as $imp ) : ?>
					<tr>
						<td><?php echo esc_html( $imp->filename ); ?></td>
						<td><?php echo esc_html( $imp->status ); ?></td>
						<td><?php echo (int) $imp->imported; ?>/<?php echo (int) $imp->total_rows; ?></td>
						<td><?php echo (int) $imp->skipped_dupes; ?></td>
						<td><?php echo (int) $imp->skipped_errors; ?></td>
						<td><?php echo esc_html( $imp->started_at ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<script>
	jQuery(function($){
		$('#gmcq-import-form').on('submit', function(e){
			e.preventDefault();
			var fd = new FormData(this);
			fd.append('action', 'gmcq_run_import');
			fd.append('_ajax_nonce', '<?php echo esc_js( $nonce ); ?>');
			$.ajax({url: gmcqAdmin.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false, success: function(r){
				if (r.success) {
					$('#gmcq-import-result').html('<p>Imported: '+r.data.imported+', Dupes: '+r.data.dupes+', Errors: '+r.data.errors+'</p>');
					setTimeout(function(){ location.reload(); }, 1500);
				} else {
					$('#gmcq-import-result').text(r.data.message || 'Error');
				}
			}});
		});
	});
	</script>
	<?php
}

gmcq_register_import_ajax_handlers();
