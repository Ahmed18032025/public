<?php
/**
 * GMCQ Settings — plugin options + backup management UI.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_save_settings( array $input ): bool {
	$defaults = gmcq_get_default_settings();
	$clean    = array();

	foreach ( $defaults as $key => $default ) {
		if ( ! array_key_exists( $key, $input ) ) {
			continue;
		}
		switch ( $key ) {
			case 'quiz_slug':
				$clean[ $key ] = sanitize_title( $input[ $key ] );
				break;
			case 'uninstall_behavior':
				$clean[ $key ] = in_array( $input[ $key ], array( 'keep', 'delete' ), true ) ? $input[ $key ] : 'keep';
				break;
			default:
				$clean[ $key ] = is_numeric( $default ) ? (int) $input[ $key ] : sanitize_text_field( $input[ $key ] );
				break;
		}
	}

	$old_slug = gmcq_get_setting( 'quiz_slug', 'quiz' );
	update_option( 'gmcq_settings', wp_parse_args( $clean, get_option( 'gmcq_settings', array() ) ) );
	gmcq_reset_settings_cache();

	if ( isset( $clean['quiz_slug'] ) && $clean['quiz_slug'] !== $old_slug ) {
		update_option( 'gmcq_old_quiz_slug', $old_slug );
		do_action( 'gmcq_quiz_slug_changed', $old_slug, $clean['quiz_slug'] );
		flush_rewrite_rules();
	}

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
}

function gmcq_ajax_save_settings(): void {
	check_ajax_referer( 'gmcq_settings_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	gmcq_save_settings( wp_unslash( $_POST ) );
	wp_send_json_success( array( 'message' => __( 'Settings saved.', 'gmcq' ) ) );
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

	$settings = wp_parse_args( get_option( 'gmcq_settings', array() ), gmcq_get_default_settings() );
	$backups  = array_reverse( get_option( 'gmcq_backup_index', array() ) );
	$nonce    = wp_create_nonce( 'gmcq_settings_nonce' );
	$backup_url_base = wp_upload_dir()['baseurl'] . '/gmcq-backups/';
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php esc_html_e( 'Settings', 'gmcq' ); ?></h1>
		<div class="gmcq-card" style="max-width:800px">
			<form id="gmcq-settings-form">
				<?php wp_nonce_field( 'gmcq_settings_nonce' ); ?>
				<h2><?php esc_html_e( 'General', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Quiz URL slug', 'gmcq' ); ?></th>
					<td><input type="text" name="quiz_slug" value="<?php echo esc_attr( $settings['quiz_slug'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Max questions per quiz', 'gmcq' ); ?></th>
					<td><input type="number" name="max_questions_per_quiz" value="<?php echo (int) $settings['max_questions_per_quiz']; ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Max attempts per IP per day', 'gmcq' ); ?></th>
					<td><input type="number" name="max_attempts_per_ip_per_day" value="<?php echo (int) $settings['max_attempts_per_ip_per_day']; ?>"> <span class="description"><?php esc_html_e( '0 = unlimited', 'gmcq' ); ?></span></td></tr>
					<tr><th><?php esc_html_e( 'Uninstall behavior', 'gmcq' ); ?></th>
					<td><select name="uninstall_behavior">
						<option value="keep" <?php selected( $settings['uninstall_behavior'], 'keep' ); ?>><?php esc_html_e( 'Keep data', 'gmcq' ); ?></option>
						<option value="delete" <?php selected( $settings['uninstall_behavior'], 'delete' ); ?>><?php esc_html_e( 'Delete all data', 'gmcq' ); ?></option>
					</select></td></tr>
				</table>
				<h2><?php esc_html_e( 'Cache TTL (seconds)', 'gmcq' ); ?></h2>
				<table class="form-table">
					<?php foreach ( array( 'dashboard_cache_ttl', 'health_cache_ttl', 'integrity_cache_ttl', 'reports_cache_ttl', 'search_cache_ttl' ) as $key ) : ?>
					<tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
					<td><input type="number" name="<?php echo esc_attr( $key ); ?>" value="<?php echo (int) $settings[ $key ]; ?>"></td></tr>
					<?php endforeach; ?>
				</table>
				<h2><?php esc_html_e( 'Backup', 'gmcq' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Auto backup enabled', 'gmcq' ); ?></th>
					<td><input type="checkbox" name="backup_enabled" value="1" <?php checked( (int) $settings['backup_enabled'] ); ?>></td></tr>
					<tr><th><?php esc_html_e( 'Backup retention (days)', 'gmcq' ); ?></th>
					<td><input type="number" name="backup_retention_days" value="<?php echo (int) $settings['backup_retention_days']; ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Max backup files', 'gmcq' ); ?></th>
					<td><input type="number" name="max_backup_files" value="<?php echo (int) $settings['max_backup_files']; ?>"></td></tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'gmcq' ); ?></button></p>
			</form>
			<div id="gmcq-settings-response"></div>
		</div>
		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Backup History', 'gmcq' ); ?></h2>
			<p><button type="button" class="button" id="gmcq-cleanup-backups"><?php esc_html_e( 'Cleanup Old Backups', 'gmcq' ); ?></button></p>
			<table class="widefat striped">
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
				$('#gmcq-settings-response').text(r.success ? r.data.message : (r.data.message || 'Error'));
			});
		});
		$('.gmcq-del-backup').on('click', function(){
			if (!confirm('Delete this backup?')) return;
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
