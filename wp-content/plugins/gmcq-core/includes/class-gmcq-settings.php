<?php
/**
 * GMCQ Settings — plugin options + backup management UI.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_validate_settings( array $input ): array {
	$errors = array();
	$valid  = array();
	$defaults = gmcq_get_default_settings();

	$bool_keys = array(
		'default_shuffle_questions',
		'default_shuffle_answers',
		'default_show_explanations',
		'default_show_correct_answers',
		'default_require_login',
		'allow_guest_attempts',
		'show_timer',
		'show_navigation',
		'allow_answer_change',
		'enable_auto_purge',
		'enable_question_tags',
		'backup_enabled',
	);

	foreach ( $defaults as $key => $default ) {
		if ( ! array_key_exists( $key, $input ) ) {
			if ( in_array( $key, $bool_keys, true ) ) {
				$valid[ $key ] = 0;
			}
			continue;
		}

		$value = $input[ $key ];

		switch ( $key ) {
			case 'quiz_slug':
				$slug = sanitize_title( $value );
				if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
					$errors['quiz_slug'] = __( 'Quiz slug can only contain letters, numbers, and hyphens', 'gmcq' );
				} else {
					$valid['quiz_slug'] = $slug;
				}
				break;

			case 'uninstall_behavior':
				$valid['uninstall_behavior'] = in_array( $value, array( 'keep', 'delete' ), true ) ? $value : 'keep';
				break;

			case 'results_display':
				$valid['results_display'] = in_array( $value, array( 'immediately', 'after_quiz', 'manual' ), true ) ? $value : 'immediately';
				break;

			case 'default_marks':
				$valid['default_marks'] = (float) $value;
				if ( $valid['default_marks'] <= 0 ) {
					$errors['default_marks'] = __( 'Default marks must be greater than 0', 'gmcq' );
				}
				break;

			case 'default_negative_marks':
				$valid['default_negative_marks'] = (float) $value;
				if ( $valid['default_negative_marks'] < 0 ) {
					$errors['default_negative_marks'] = __( 'Negative marks cannot be negative', 'gmcq' );
				}
				break;

			case 'default_time_limit':
				$valid['default_time_limit'] = (int) $value;
				if ( $valid['default_time_limit'] < 0 ) {
					$errors['default_time_limit'] = __( 'Time limit cannot be negative', 'gmcq' );
				}
				break;

			case 'default_pass_percentage':
				$valid['default_pass_percentage'] = (int) $value;
				if ( $valid['default_pass_percentage'] < 1 || $valid['default_pass_percentage'] > 100 ) {
					$errors['default_pass_percentage'] = __( 'Pass percentage must be between 1 and 100', 'gmcq' );
				}
				break;

			case 'default_questions_per_page':
				$valid['default_questions_per_page'] = (int) $value;
				if ( $valid['default_questions_per_page'] < 1 ) {
					$errors['default_questions_per_page'] = __( 'Questions per page must be at least 1', 'gmcq' );
				}
				break;

			case 'max_questions_per_quiz':
				$valid['max_questions_per_quiz'] = (int) $value;
				if ( $valid['max_questions_per_quiz'] < 10 ) {
					$errors['max_questions_per_quiz'] = __( 'Max questions per quiz must be at least 10', 'gmcq' );
				}
				break;

			case 'max_csv_size_mb':
				$valid['max_csv_size_mb'] = (int) $value;
				if ( $valid['max_csv_size_mb'] < 1 ) {
					$errors['max_csv_size_mb'] = __( 'Max CSV size must be at least 1 MB', 'gmcq' );
				}
				break;

			case 'max_import_rows':
				$valid['max_import_rows'] = (int) $value;
				if ( $valid['max_import_rows'] < 100 ) {
					$errors['max_import_rows'] = __( 'Max import rows must be at least 100', 'gmcq' );
				}
				break;

			case 'import_batch_size':
				$valid['import_batch_size'] = (int) $value;
				if ( $valid['import_batch_size'] < 10 ) {
					$errors['import_batch_size'] = __( 'Batch size must be at least 10', 'gmcq' );
				} elseif ( $valid['import_batch_size'] > 500 ) {
					$errors['import_batch_size'] = __( 'Batch size cannot exceed 500', 'gmcq' );
				}
				break;

			case 'search_min_query_length':
				$valid['search_min_query_length'] = (int) $value;
				if ( $valid['search_min_query_length'] < 1 ) {
					$errors['search_min_query_length'] = __( 'Minimum query length must be at least 1', 'gmcq' );
				}
				break;

			case 'search_max_per_page':
				$valid['search_max_per_page'] = (int) $value;
				if ( $valid['search_max_per_page'] < 10 ) {
					$errors['search_max_per_page'] = __( 'Max results per page must be at least 10', 'gmcq' );
				}
				break;

			case 'dashboard_cache_ttl':
			case 'health_cache_ttl':
			case 'integrity_cache_ttl':
			case 'reports_cache_ttl':
			case 'search_cache_ttl':
				$valid[ $key ] = (int) $value;
				if ( $valid[ $key ] < 60 ) {
					$errors[ $key ] = __( 'Cache TTL must be at least 60 seconds', 'gmcq' );
				}
				break;

			case 'max_attempts_per_ip_per_day':
				$valid['max_attempts_per_ip_per_day'] = (int) $value;
				if ( $valid['max_attempts_per_ip_per_day'] < 0 ) {
					$errors['max_attempts_per_ip_per_day'] = __( 'Max attempts per day cannot be negative', 'gmcq' );
				}
				break;

			case 'activity_retention_days':
			case 'attempt_retention_days':
			case 'backup_retention_days':
			case 'max_backup_files':
				$valid[ $key ] = (int) $value;
				break;

			default:
				$valid[ $key ] = in_array( $key, $bool_keys, true ) ? (int) ( '1' === $value || 1 === $value ) : ( is_numeric( $default ) ? (int) $value : sanitize_text_field( $value ) );
				break;
		}
	}

	return array( 'valid' => $valid, 'errors' => $errors );
}

function gmcq_save_settings( array $input ): bool {
	$result = gmcq_validate_settings( $input );

	if ( ! empty( $result['errors'] ) ) {
		set_transient( 'gmcq_settings_errors', $result['errors'], 30 );
		return false;
	}

	$valid = $result['valid'];
	$existing = get_option( 'gmcq_settings', array() );

	$old_slug = $existing['quiz_slug'] ?? 'quiz';
	update_option( 'gmcq_settings', array_merge( $existing, $valid ) );
	gmcq_reset_settings_cache();

	if ( isset( $valid['quiz_slug'] ) && $valid['quiz_slug'] !== $old_slug ) {
		update_option( 'gmcq_old_quiz_slug', $old_slug );
		do_action( 'gmcq_quiz_slug_changed', $old_slug, $valid['quiz_slug'] );
		flush_rewrite_rules();
	}

	delete_transient( 'gmcq_settings_errors' );
	return true;
}

function gmcq_reset_settings(): bool {
	update_option( 'gmcq_settings', gmcq_get_default_settings() );
	gmcq_reset_settings_cache();
	return true;
}

function gmcq_delete_backup_file( string $filename ): bool {
	$filename   = basename( $filename );
	$backup_dir = wp_upload_dir()['basedir'] . '/gmcq-backups';
	$filepath   = $backup_dir . '/' . $filename;

	if ( file_exists( $filepath ) ) {
		wp_delete_file( $filepath );
	}

	$backups   = get_option( 'gmcq_backup_index', array() );
	$remaining = array_filter(
		$backups,
		static function ( $b ) use ( $filename ) {
			return ( $b['file'] ?? '' ) !== $filename;
		}
	);
	update_option( 'gmcq_backup_index', array_values( $remaining ) );

	return true;
}

function gmcq_register_settings_ajax_handlers(): void {
	add_action( 'wp_ajax_gmcq_save_settings', 'gmcq_ajax_save_settings' );
	add_action( 'wp_ajax_gmcq_delete_backup', 'gmcq_ajax_delete_backup' );
	add_action( 'wp_ajax_gmcq_cleanup_backups', 'gmcq_ajax_cleanup_backups' );
	add_action( 'wp_ajax_gmcq_reset_settings', 'gmcq_ajax_reset_settings' );
	add_action( 'wp_ajax_gmcq_export_data', 'gmcq_ajax_export_data' );
}

function gmcq_ajax_save_settings(): void {
	check_ajax_referer( 'gmcq_settings_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$success = gmcq_save_settings( wp_unslash( $_POST ) );

	if ( ! $success ) {
		$errors = get_transient( 'gmcq_settings_errors', array() );
		wp_send_json_error( array( 'message' => __( 'Validation failed.', 'gmcq' ), 'errors' => $errors ) );
	}

	$message = __( 'Settings saved.', 'gmcq' );
	if ( ! empty( $_POST['quiz_slug'] ) && sanitize_title( wp_unslash( $_POST['quiz_slug'] ) ) !== gmcq_get_setting( 'quiz_slug', 'quiz' ) ) {
		$message = __( 'Quiz URL slug updated. Old URLs will redirect to new slug.', 'gmcq' );
	}

	wp_send_json_success( array( 'message' => $message ) );
}

function gmcq_ajax_reset_settings(): void {
	check_ajax_referer( 'gmcq_settings_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	gmcq_reset_settings();
	wp_send_json_success( array( 'message' => __( 'Settings reset to defaults.', 'gmcq' ) ) );
}

function gmcq_ajax_export_data(): void {
	check_ajax_referer( 'gmcq_settings_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( __( 'Permission denied.', 'gmcq' ), 403 );
	}

	gmcq_export_all_data();
}

function gmcq_ajax_delete_backup(): void {
	check_ajax_referer( 'gmcq_settings_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	$file = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
	gmcq_delete_backup_file( $file );
	wp_send_json_success( array( 'message' => __( 'Backup deleted.', 'gmcq' ) ) );
}

function gmcq_ajax_cleanup_backups(): void {
	check_ajax_referer( 'gmcq_settings_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	gmcq_cleanup_old_backups();
	wp_send_json_success( array( 'message' => __( 'Old backups cleaned up.', 'gmcq' ) ) );
}

function gmcq_render_settings_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	$settings       = wp_parse_args( get_option( 'gmcq_settings', array() ), gmcq_get_default_settings() );
	$backups        = array_reverse( get_option( 'gmcq_backup_index', array() ) );
	$nonce          = wp_create_nonce( 'gmcq_settings_nonce' );
	$backup_url_base = wp_upload_dir()['baseurl'] . '/gmcq-backups/';
	$errors         = get_transient( 'gmcq_settings_errors', array() );
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php esc_html_e( 'Settings', 'gmcq' ); ?></h1>

		<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error" role="alert" style="margin-top:10px">
			<p><?php esc_html_e( 'Please fix the following errors:', 'gmcq' ); ?></p>
			<ul>
				<?php foreach ( $errors as $field => $msg ) : ?>
					<li><strong><?php echo esc_html( $field ); ?>:</strong> <?php echo esc_html( $msg ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<form id="gmcq-settings-form">
			<?php wp_nonce_field( 'gmcq_settings_nonce' ); ?>

			<div class="gmcq-card" style="max-width:900px">
				<h2><?php esc_html_e( 'Default Quiz Settings', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="gmcq-default-marks"><?php esc_html_e( 'Default Marks per Question', 'gmcq' ); ?></label></th>
					<td><input type="number" step="0.01" min="0.01" name="default_marks" id="gmcq-default-marks" value="<?php echo esc_attr( $settings['default_marks'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-default-negative-marks"><?php esc_html_e( 'Default Negative Marks', 'gmcq' ); ?></label></th>
					<td><input type="number" step="0.01" min="0" name="default_negative_marks" id="gmcq-default-negative-marks" value="<?php echo esc_attr( $settings['default_negative_marks'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-default-time-limit"><?php esc_html_e( 'Default Time Limit (minutes)', 'gmcq' ); ?></label></th>
					<td><input type="number" min="0" name="default_time_limit" id="gmcq-default-time-limit" value="<?php echo esc_attr( $settings['default_time_limit'] ); ?>"> <span class="description"><?php esc_html_e( '0 = none', 'gmcq' ); ?></span></td></tr>
					<tr><th><label for="gmcq-default-pass-percentage"><?php esc_html_e( 'Default Pass Percentage', 'gmcq' ); ?></label></th>
					<td><input type="number" min="1" max="100" name="default_pass_percentage" id="gmcq-default-pass-percentage" value="<?php echo esc_attr( $settings['default_pass_percentage'] ); ?>"> <span class="description">%</span></td></tr>
					<tr><th><label for="gmcq-default-questions-per-page"><?php esc_html_e( 'Default Questions Per Page', 'gmcq' ); ?></label></th>
					<td><input type="number" min="1" name="default_questions_per_page" id="gmcq-default-questions-per-page" value="<?php echo esc_attr( $settings['default_questions_per_page'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-max-questions-per-quiz"><?php esc_html_e( 'Max Questions Per Quiz', 'gmcq' ); ?></label></th>
					<td><input type="number" min="10" name="max_questions_per_quiz" id="gmcq-max-questions-per-quiz" value="<?php echo esc_attr( $settings['max_questions_per_quiz'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Defaults', 'gmcq' ); ?></th>
					<td>
						<label><input type="checkbox" name="default_shuffle_questions" value="1" <?php checked( $settings['default_shuffle_questions'], 1 ); ?>> <?php esc_html_e( 'Shuffle Questions by Default', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="default_shuffle_answers" value="1" <?php checked( $settings['default_shuffle_answers'], 1 ); ?>> <?php esc_html_e( 'Shuffle Answers by Default', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="default_show_explanations" value="1" <?php checked( $settings['default_show_explanations'], 1 ); ?>> <?php esc_html_e( 'Show Explanations After Quiz', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="default_show_correct_answers" value="1" <?php checked( $settings['default_show_correct_answers'], 1 ); ?>> <?php esc_html_e( 'Show Correct Answers After Quiz', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="default_require_login" value="1" <?php checked( $settings['default_require_login'], 1 ); ?>> <?php esc_html_e( 'Require Login to Attempt Quizzes', 'gmcq' ); ?></label>
					</td></tr>
				</table>
			</div>

			<div class="gmcq-card" style="max-width:900px;margin-top:20px">
				<h2><?php esc_html_e( 'Frontend Settings', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="gmcq-quiz-slug"><?php esc_html_e( 'Quiz Page Slug', 'gmcq' ); ?></label></th>
					<td><input type="text" name="quiz_slug" id="gmcq-quiz-slug" value="<?php echo esc_attr( $settings['quiz_slug'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="gmcq-results-display"><?php esc_html_e( 'Results Display', 'gmcq' ); ?></label></th>
					<td><select name="results_display" id="gmcq-results-display">
						<option value="immediately" <?php selected( $settings['results_display'], 'immediately' ); ?>><?php esc_html_e( 'Immediately After Submission', 'gmcq' ); ?></option>
						<option value="after_quiz" <?php selected( $settings['results_display'], 'after_quiz' ); ?>><?php esc_html_e( 'After Quiz Completes', 'gmcq' ); ?></option>
						<option value="manual" <?php selected( $settings['results_display'], 'manual' ); ?>><?php esc_html_e( 'Manual (Admin Only)', 'gmcq' ); ?></option>
					</select></td></tr>
					<tr><th><?php esc_html_e( 'Frontend Behavior', 'gmcq' ); ?></th>
					<td>
						<label><input type="checkbox" name="allow_guest_attempts" value="1" <?php checked( $settings['allow_guest_attempts'], 1 ); ?>> <?php esc_html_e( 'Allow Guest Attempts (no login required)', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="show_timer" value="1" <?php checked( $settings['show_timer'], 1 ); ?>> <?php esc_html_e( 'Show Timer During Quiz', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="show_navigation" value="1" <?php checked( $settings['show_navigation'], 1 ); ?>> <?php esc_html_e( 'Show Question Navigation Panel', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="allow_answer_change" value="1" <?php checked( $settings['allow_answer_change'], 1 ); ?>> <?php esc_html_e( 'Allow Answer Change After Navigation', 'gmcq' ); ?></label>
					</td></tr>
					<tr><th><label for="gmcq-max-attempts"><?php esc_html_e( 'Max Attempts Per IP Per Day', 'gmcq' ); ?></label></th>
					<td><input type="number" min="0" name="max_attempts_per_ip_per_day" id="gmcq-max-attempts" value="<?php echo esc_attr( $settings['max_attempts_per_ip_per_day'] ); ?>"> <span class="description"><?php esc_html_e( '0 = unlimited', 'gmcq' ); ?></span></td></tr>
				</table>
			</div>

			<div class="gmcq-card" style="max-width:900px;margin-top:20px">
				<h2><?php esc_html_e( 'Import Settings', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="gmcq-max-csv-size"><?php esc_html_e( 'Max CSV File Size (MB)', 'gmcq' ); ?></label></th>
					<td><input type="number" min="1" name="max_csv_size_mb" id="gmcq-max-csv-size" value="<?php echo esc_attr( $settings['max_csv_size_mb'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-max-import-rows"><?php esc_html_e( 'Max Rows Per Import', 'gmcq' ); ?></label></th>
					<td><input type="number" min="100" name="max_import_rows" id="gmcq-max-import-rows" value="<?php echo esc_attr( $settings['max_import_rows'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-import-batch-size"><?php esc_html_e( 'Import Batch Size', 'gmcq' ); ?></label></th>
					<td><input type="number" min="10" max="500" name="import_batch_size" id="gmcq-import-batch-size" value="<?php echo esc_attr( $settings['import_batch_size'] ); ?>"> <span class="description"><?php esc_html_e( 'questions per batch', 'gmcq' ); ?></span></td></tr>
				</table>
			</div>

			<div class="gmcq-card" style="max-width:900px;margin-top:20px">
				<h2><?php esc_html_e( 'Search Settings', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="gmcq-search-min-query"><?php esc_html_e( 'Minimum Query Length', 'gmcq' ); ?></label></th>
					<td><input type="number" min="1" name="search_min_query_length" id="gmcq-search-min-query" value="<?php echo esc_attr( $settings['search_min_query_length'] ); ?>"> <span class="description"><?php esc_html_e( 'characters', 'gmcq' ); ?></span></td></tr>
					<tr><th><label for="gmcq-search-cache-ttl"><?php esc_html_e( 'Search Results Cache (seconds)', 'gmcq' ); ?></label></th>
					<td><input type="number" min="60" name="search_cache_ttl" id="gmcq-search-cache-ttl" value="<?php echo esc_attr( $settings['search_cache_ttl'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-search-max-per-page"><?php esc_html_e( 'Max Results Per Page', 'gmcq' ); ?></label></th>
					<td><input type="number" min="10" name="search_max_per_page" id="gmcq-search-max-per-page" value="<?php echo esc_attr( $settings['search_max_per_page'] ); ?>"></td></tr>
				</table>
			</div>

			<div class="gmcq-card" style="max-width:900px;margin-top:20px">
				<h2><?php esc_html_e( 'Cache Settings', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="gmcq-dashboard-cache-ttl"><?php esc_html_e( 'Dashboard Stats Cache (seconds)', 'gmcq' ); ?></label></th>
					<td><input type="number" min="60" name="dashboard_cache_ttl" id="gmcq-dashboard-cache-ttl" value="<?php echo esc_attr( $settings['dashboard_cache_ttl'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-health-cache-ttl"><?php esc_html_e( 'System Health Cache (seconds)', 'gmcq' ); ?></label></th>
					<td><input type="number" min="60" name="health_cache_ttl" id="gmcq-health-cache-ttl" value="<?php echo esc_attr( $settings['health_cache_ttl'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-integrity-cache-ttl"><?php esc_html_e( 'Data Integrity Cache (seconds)', 'gmcq' ); ?></label></th>
					<td><input type="number" min="60" name="integrity_cache_ttl" id="gmcq-integrity-cache-ttl" value="<?php echo esc_attr( $settings['integrity_cache_ttl'] ); ?>"></td></tr>
					<tr><th><label for="gmcq-reports-cache-ttl"><?php esc_html_e( 'Reports Summary Cache (seconds)', 'gmcq' ); ?></label></th>
					<td><input type="number" min="60" name="reports_cache_ttl" id="gmcq-reports-cache-ttl" value="<?php echo esc_attr( $settings['reports_cache_ttl'] ); ?>"></td></tr>
				</table>
			</div>

			<div class="gmcq-card" style="max-width:900px;margin-top:20px">
				<h2><?php esc_html_e( 'Data Retention', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="gmcq-activity-retention"><?php esc_html_e( 'Activity Log Retention', 'gmcq' ); ?></label></th>
					<td><select name="activity_retention_days" id="gmcq-activity-retention">
						<?php foreach ( array( 30, 90, 180, 365, 0 ) as $days ) : ?>
							<option value="<?php echo esc_attr( $days ); ?>" <?php selected( $settings['activity_retention_days'], $days ); ?>>
								<?php echo esc_html( 0 === $days ? __( 'Forever', 'gmcq' ) : sprintf( __( '%d days', 'gmcq' ), $days ) ); ?>
							</option>
						<?php endforeach; ?>
					</select></td></tr>
					<tr><th><label for="gmcq-attempt-retention"><?php esc_html_e( 'Attempt Data Retention', 'gmcq' ); ?></label></th>
					<td><select name="attempt_retention_days" id="gmcq-attempt-retention">
						<?php foreach ( array( 180, 365, 730, 0 ) as $days ) : ?>
							<option value="<?php echo esc_attr( $days ); ?>" <?php selected( $settings['attempt_retention_days'], $days ); ?>>
								<?php echo esc_html( 0 === $days ? __( 'Forever', 'gmcq' ) : sprintf( __( '%d days', 'gmcq' ), $days ) ); ?>
							</option>
						<?php endforeach; ?>
					</select></td></tr>
					<tr><th><?php esc_html_e( 'Automatic Archive', 'gmcq' ); ?></th>
					<td>
						<label><input type="checkbox" name="enable_auto_purge" value="1" <?php checked( $settings['enable_auto_purge'], 1 ); ?>> <?php esc_html_e( 'Enable automatic archive (runs weekly)', 'gmcq' ); ?></label>
						<p class="description"><?php esc_html_e( 'Note: Old attempts are archived (soft-hidden), not deleted. They are exported to CSV and answers compressed to JSON to preserve business data.', 'gmcq' ); ?></p>
					</td></tr>
				</table>
			</div>

			<div class="gmcq-card" style="max-width:900px;margin-top:20px">
				<h2><?php esc_html_e( 'Data Management', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><label><?php esc_html_e( 'On Plugin Uninstall', 'gmcq' ); ?></label></th>
					<td>
						<label><input type="radio" name="uninstall_behavior" value="keep" <?php checked( $settings['uninstall_behavior'], 'keep' ); ?>> <?php esc_html_e( 'Keep all data (tables remain)', 'gmcq' ); ?></label><br>
						<label><input type="radio" name="uninstall_behavior" value="delete" <?php checked( $settings['uninstall_behavior'], 'delete' ); ?>> <?php esc_html_e( 'Delete all data (tables removed)', 'gmcq' ); ?></label>
						<?php if ( 'delete' === $settings['uninstall_behavior'] ) : ?>
						<p class="gmcq-status-warning" style="margin-top:8px"><?php esc_html_e( 'Warning: "Delete all data" will permanently remove all questions, quizzes, attempts, and categories. This cannot be undone. Export your data first.', 'gmcq' ); ?></p>
						<?php endif; ?>
					</td></tr>
				</table>
				<p class="submit" style="margin-top:20px">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Settings', 'gmcq' ); ?></button>
				</p>
			</div>
		</form>

		<div class="gmcq-card" style="max-width:900px;margin-top:20px">
			<h2><?php esc_html_e( 'Data Management Actions', 'gmcq' ); ?></h2>
			<p>
				<button type="button" class="button button-secondary" id="gmcq-reset-settings"><?php esc_html_e( 'Reset to Defaults', 'gmcq' ); ?></button>
				<button type="button" class="button button-secondary" id="gmcq-export-data"><?php esc_html_e( 'Export All Data', 'gmcq' ); ?></button>
			</p>
		</div>

		<div class="gmcq-card" style="max-width:900px;margin-top:20px">
			<h2><?php esc_html_e( 'Backup History', 'gmcq' ); ?></h2>
			<?php if ( ! empty( $backups ) ) : ?>
			<p><button type="button" class="button" id="gmcq-cleanup-backups"><?php esc_html_e( 'Cleanup Old Backups', 'gmcq' ); ?></button></p>
			<?php endif; ?>
			<table class="widefat striped" style="max-width:100%">
				<thead><tr><th><?php esc_html_e( 'File', 'gmcq' ); ?></th><th><?php esc_html_e( 'Type', 'gmcq' ); ?></th><th><?php esc_html_e( 'Created', 'gmcq' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php if ( empty( $backups ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No backups yet.', 'gmcq' ); ?></td></tr>
				<?php else : foreach ( $backups as $b ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( $backup_url_base . ( $b['file'] ?? '' ) ); ?>" download><?php echo esc_html( $b['file'] ?? '' ); ?></a></td>
						<td><?php echo esc_html( $b['type'] ?? '' ); ?></td>
						<td><?php echo esc_html( $b['created'] ?? '' ); ?></td>
						<td><button type="button" class="button-link gmcq-del-backup" data-file="<?php echo esc_attr( $b['file'] ?? '' ); ?>"><?php esc_html_e( 'Delete', 'gmcq' ); ?></button></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<script>
	jQuery(function($){
		var nonce = '<?php echo esc_js( $nonce ); ?>';
		$('#gmcq-settings-form').on('submit', function(e){
			e.preventDefault();
			var data = $(this).serializeArray();
			data.push({name:'action', value:'gmcq_save_settings'});
			$.post(gmcqAdmin.ajaxUrl, $.param(data), function(r){
				$('.gmcq-dashboard-wrap > .notice').remove();
				var notice = $('<div class="notice ' + (r.success ? 'notice-success' : 'notice-error') + '" role="alert" style="margin-top:10px"><p>' + (r.data.message || '<?php echo esc_js( __( 'Settings saved.', 'gmcq' ) ); ?>') + '</p></div>');
				$('.wrap.gmcq-dashboard-wrap').prepend(notice);
				if (r.success) setTimeout(function(){ notice.fadeOut(400, function(){ notice.remove(); }); }, 4000);
			});
		});
		$('#gmcq-reset-settings').on('click', function(){
			if (!confirm('<?php echo esc_js( __( 'Are you sure you want to reset all settings to defaults?', 'gmcq' ) ); ?>')) return;
			$.post(gmcqAdmin.ajaxUrl, {action:'gmcq_reset_settings', _ajax_nonce: nonce}, function(r){
				if (r.success) location.reload();
			});
		});
		$('#gmcq-export-data').on('click', function(){
			window.location.href = gmcqAdmin.ajaxUrl + '?action=gmcq_export_data&_ajax_nonce=' + nonce;
		});
		$('.gmcq-del-backup').on('click', function(){
			if (!confirm('<?php echo esc_js( __( 'Delete this backup?', 'gmcq' ) ); ?>')) return;
			$.post(gmcqAdmin.ajaxUrl, {action:'gmcq_delete_backup', file: $(this).data('file'), _ajax_nonce: nonce}, function(){ location.reload(); });
		});
		$('#gmcq-cleanup-backups').on('click', function(){
			$.post(gmcqAdmin.ajaxUrl, {action:'gmcq_cleanup_backups', _ajax_nonce: nonce}, function(){ location.reload(); });
		});
	});
	</script>
	<?php
}

gmcq_register_settings_ajax_handlers();

function gmcq_add_old_slug_redirect_rules(): void {
	$old_slug = get_option( 'gmcq_old_quiz_slug' );
	if ( $old_slug ) {
		add_rewrite_rule(
			"{$old_slug}/(.+?)/?$",
			'index.php?gmcq_quiz=$matches[1]',
			'top'
		);
	}
}
add_action( 'init', 'gmcq_add_old_slug_redirect_rules' );

function gmcq_maybe_redirect_old_slug(): void {
	if ( is_404() && get_query_var( 'gmcq_quiz' ) ) {
		$new_slug = gmcq_get_setting( 'quiz_slug', 'quiz' );
		$quiz_slug = get_query_var( 'gmcq_quiz' );
		wp_safe_redirect( home_url( "{$new_slug}/{$quiz_slug}/" ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'gmcq_maybe_redirect_old_slug' );