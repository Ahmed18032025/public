<?php
/**
 * GMCQ Dashboard — stats, health, integrity, top/recent quizzes.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_get_dashboard_stats(): array {
	$stats = get_transient( 'gmcq_dashboard_stats' );
	if ( false !== $stats ) {
		return $stats;
	}

	if ( get_transient( 'gmcq_dashboard_stats_lock' ) ) {
		return array( '_rebuilding' => true );
	}
	set_transient( 'gmcq_dashboard_stats_lock', true, 30 );

	global $wpdb;
	$p         = $wpdb->prefix;
	$cache_ttl = (int) gmcq_get_setting( 'dashboard_cache_ttl', 300 );

	$stats = array(
		'top_level_categories' => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NULL AND is_active = 1"
		),
		'child_categories'     => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NOT NULL AND is_active = 1"
		),
		'published_quizzes'    => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta WHERE status = 'published' AND is_active = 1"
		),
		'active_questions'     => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_questions WHERE is_active = 1"
		),
		'total_attempts'       => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_attempts WHERE status = 'completed' AND is_active = 1"
		),
		'last_updated'         => current_time( 'mysql' ),
	);

	set_transient( 'gmcq_dashboard_stats', $stats, $cache_ttl );
	delete_transient( 'gmcq_dashboard_stats_lock' );

	return $stats;
}

function gmcq_get_system_health(): array {
	$cache_key = 'gmcq_system_health';
	$health    = get_transient( $cache_key );
	if ( false !== $health ) {
		return $health;
	}

	global $wpdb;
	$p         = $wpdb->prefix;
	$cache_ttl = (int) gmcq_get_setting( 'health_cache_ttl', 600 );

	$tables_ok = true;
	foreach ( array_keys( gmcq_get_schema_contract() ) as $table ) {
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $p . $table ) );
		if ( $exists !== $p . $table ) {
			$tables_ok = false;
			break;
		}
	}

	$health = array(
		'tables_ok'           => $tables_ok,
		'db_version'          => get_option( 'gmcq_db_version', '0' ),
		'cron_daily'          => (bool) wp_next_scheduled( 'gmcq_daily_cron' ),
		'cron_weekly'         => (bool) wp_next_scheduled( 'gmcq_weekly_cron' ),
		'backup_dir_writable' => wp_is_writable( wp_upload_dir()['basedir'] . '/gmcq-backups' ) || wp_mkdir_p( wp_upload_dir()['basedir'] . '/gmcq-backups' ),
		'orphan_attempt_rows' => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_attempts a
			 LEFT JOIN {$wpdb->posts} p ON p.ID = a.quiz_id
			 WHERE p.ID IS NULL"
		),
	);

	set_transient( $cache_key, $health, $cache_ttl );
	return $health;
}

function gmcq_get_data_integrity(): array {
	$cache_ttl = (int) gmcq_get_setting( 'integrity_cache_ttl', 900 );
	$integrity = get_transient( 'gmcq_data_integrity' );
	if ( false !== $integrity ) {
		return $integrity;
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$integrity = array(
		'unassigned_questions'             => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_questions WHERE usage_count = 0 AND is_active = 1"
		),
		'questions_in_archived_quizzes'    => (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT qm.question_id) FROM {$p}gmcq_question_map qm
			 JOIN {$p}gmcq_quizzes_meta zm ON zm.quiz_id = qm.quiz_id WHERE zm.is_active = 0"
		),
		'duplicate_questions'              => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM (
			    SELECT question_hash FROM {$p}gmcq_questions GROUP BY question_hash HAVING COUNT(*) > 1
			 ) t"
		),
		'potential_duplicates'             => null,
		'categories_no_children'           => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_categories c1 WHERE parent_id IS NULL AND is_active = 1
			 AND NOT EXISTS (SELECT 1 FROM {$p}gmcq_categories c2 WHERE c2.parent_id = c1.id AND c2.is_active = 1)"
		),
		'subcategories_no_questions'       => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NOT NULL AND is_active = 1 AND question_count = 0"
		),
		'quizzes_no_questions'             => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta WHERE status = 'published' AND is_active = 1 AND question_count = 0"
		),
		'questions_in_inactive_categories' => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_questions q JOIN {$p}gmcq_categories c ON c.id = q.category_id
			 WHERE q.is_active = 1 AND c.is_active = 0"
		),
	);

	set_transient( 'gmcq_data_integrity', $integrity, $cache_ttl );
	return $integrity;
}

function gmcq_get_top_quizzes( int $limit = 5 ): array {
	$cache_key = 'gmcq_top_quizzes';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT zm.quiz_id, zm.attempt_count, zm.question_count, p.post_title
			 FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 WHERE zm.is_active = 1 AND zm.status = 'published'
			 ORDER BY zm.attempt_count DESC
			 LIMIT %d",
			$limit
		)
	);

	set_transient( $cache_key, $rows ?: array(), 300 );
	return $rows ?: array();
}

function gmcq_get_recent_quizzes( int $limit = 5 ): array {
	$cache_key = 'gmcq_recent_quizzes';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT zm.quiz_id, zm.status, zm.question_count, zm.created_at, p.post_title
			 FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 WHERE zm.is_active = 1
			 ORDER BY zm.created_at DESC
			 LIMIT %d",
			$limit
		)
	);

	set_transient( $cache_key, $rows ?: array(), 300 );
	return $rows ?: array();
}

function gmcq_get_last_import(): ?object {
	global $wpdb;
	return $wpdb->get_row(
		"SELECT * FROM {$wpdb->prefix}gmcq_imports ORDER BY started_at DESC LIMIT 1"
	);
}

function gmcq_get_attempt_quiz_title( int $quiz_id ): string {
	$cache_key = 'gmcq_quiz_title_' . $quiz_id;
	$title     = get_transient( $cache_key );
	if ( false !== $title ) {
		return $title;
	}

	$title = get_the_title( $quiz_id ) ?: 'Quiz #' . $quiz_id;
	set_transient( $cache_key, $title, 300 );
	return $title;
}

function gmcq_get_quiz_total_marks( int $quiz_id ): float {
	global $wpdb;
	return (float) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(SUM(COALESCE(qm.marks, zm.default_marks)), 0)
			 FROM {$wpdb->prefix}gmcq_question_map qm
			 JOIN {$wpdb->prefix}gmcq_quizzes_meta zm ON zm.quiz_id = qm.quiz_id
			 WHERE qm.quiz_id = %d",
			$quiz_id
		)
	);
}

function gmcq_get_quiz_avg_score( int $quiz_id ): ?float {
	global $wpdb;
	$avg = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT AVG(percentage) FROM {$wpdb->prefix}gmcq_attempts
			 WHERE quiz_id = %d AND status = 'completed' AND is_active = 1",
			$quiz_id
		)
	);
	return null !== $avg ? (float) $avg : null;
}

function gmcq_render_full_dashboard_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	$stats      = gmcq_get_dashboard_stats();
	$health     = gmcq_get_system_health();
	$integrity  = gmcq_get_data_integrity();
	$top        = gmcq_get_top_quizzes();
	$recent     = gmcq_get_recent_quizzes();
	$last_import = gmcq_get_last_import();

	$filter_links = array(
		'unassigned_questions'             => admin_url( 'admin.php?page=gmcq-questions&filter=unassigned' ),
		'questions_in_archived_quizzes'    => admin_url( 'admin.php?page=gmcq-questions&filter=archived_quiz' ),
		'duplicate_questions'              => admin_url( 'admin.php?page=gmcq-questions&filter=duplicates' ),
		'categories_no_children'           => admin_url( 'admin.php?page=gmcq-categories&filter=no_children' ),
		'subcategories_no_questions'       => admin_url( 'admin.php?page=gmcq-categories&filter=no_questions' ),
		'quizzes_no_questions'             => admin_url( 'admin.php?page=gmcq-quizzes&filter=no_questions' ),
		'questions_in_inactive_categories' => admin_url( 'admin.php?page=gmcq-questions&filter=inactive_category' ),
	);
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><span class="dashicons dashicons-analytics" style="font-size:30px;margin-right:8px"></span><?php esc_html_e( 'GMCQ Quiz Engine', 'gmcq' ); ?></h1>
		<p class="description"><?php printf( esc_html__( 'Version %s — MCQ Quiz Management System', 'gmcq' ), esc_html( GMCQ_VERSION ) ); ?></p>

		<?php if ( ! empty( $stats['_rebuilding'] ) ) : ?>
			<div class="notice notice-info"><p><?php esc_html_e( 'Dashboard stats are rebuilding. Refresh in a moment.', 'gmcq' ); ?></p></div>
		<?php else : ?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:20px 0">
			<?php
			$cards = array(
				array( __( 'Top Categories', 'gmcq' ), $stats['top_level_categories'] ?? 0 ),
				array( __( 'Subcategories', 'gmcq' ), $stats['child_categories'] ?? 0 ),
				array( __( 'Published Quizzes', 'gmcq' ), $stats['published_quizzes'] ?? 0 ),
				array( __( 'Active Questions', 'gmcq' ), $stats['active_questions'] ?? 0 ),
				array( __( 'Total Attempts', 'gmcq' ), $stats['total_attempts'] ?? 0 ),
			);
			foreach ( $cards as $card ) :
				?>
				<div class="gmcq-card" style="text-align:center;padding:16px">
					<div style="font-size:28px;font-weight:700"><?php echo (int) $card[1]; ?></div>
					<div style="color:#666"><?php echo esc_html( $card[0] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="gmcq-card">
			<h2><?php esc_html_e( 'System Health', 'gmcq' ); ?></h2>
			<table class="widefat" style="max-width:700px">
				<tbody>
					<tr><td><?php esc_html_e( 'Database tables', 'gmcq' ); ?></td><td><?php echo ! empty( $health['tables_ok'] ) ? '<span class="gmcq-status-ok">' . esc_html__( 'OK', 'gmcq' ) . '</span>' : '<span class="gmcq-status-inactive">' . esc_html__( 'Missing', 'gmcq' ) . '</span>'; ?></td></tr>
					<tr><td><?php esc_html_e( 'Daily cron', 'gmcq' ); ?></td><td><?php echo ! empty( $health['cron_daily'] ) ? '<span class="gmcq-status-ok">' . esc_html__( 'Scheduled', 'gmcq' ) . '</span>' : '<span class="gmcq-status-warning">' . esc_html__( 'Not scheduled', 'gmcq' ) . '</span>'; ?></td></tr>
					<tr><td><?php esc_html_e( 'Weekly cron', 'gmcq' ); ?></td><td><?php echo ! empty( $health['cron_weekly'] ) ? '<span class="gmcq-status-ok">' . esc_html__( 'Scheduled', 'gmcq' ) . '</span>' : '<span class="gmcq-status-warning">' . esc_html__( 'Not scheduled', 'gmcq' ) . '</span>'; ?></td></tr>
					<tr><td><?php esc_html_e( 'Backup directory', 'gmcq' ); ?></td><td><?php echo ! empty( $health['backup_dir_writable'] ) ? '<span class="gmcq-status-ok">' . esc_html__( 'Writable', 'gmcq' ) . '</span>' : '<span class="gmcq-status-inactive">' . esc_html__( 'Not writable', 'gmcq' ) . '</span>'; ?></td></tr>
					<tr><td><?php esc_html_e( 'Orphan attempts', 'gmcq' ); ?></td><td><?php echo (int) ( $health['orphan_attempt_rows'] ?? 0 ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Data Integrity', 'gmcq' ); ?></h2>
			<table class="widefat" style="max-width:700px">
				<tbody>
					<?php foreach ( $integrity as $key => $count ) : ?>
						<?php if ( null === $count ) { continue; } ?>
						<tr>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></td>
							<td>
								<?php if ( $count > 0 && isset( $filter_links[ $key ] ) ) : ?>
									<a href="<?php echo esc_url( $filter_links[ $key ] ); ?>"><?php echo (int) $count; ?></a>
								<?php else : ?>
									<?php echo (int) $count; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
			<div class="gmcq-card">
				<h2><?php esc_html_e( 'Top Quizzes', 'gmcq' ); ?></h2>
				<?php if ( empty( $top ) ) : ?>
					<p><?php esc_html_e( 'No published quizzes yet.', 'gmcq' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $top as $q ) : ?>
							<li><?php echo esc_html( $q->post_title ); ?> — <?php echo (int) $q->attempt_count; ?> <?php esc_html_e( 'attempts', 'gmcq' ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div class="gmcq-card">
				<h2><?php esc_html_e( 'Recent Quizzes', 'gmcq' ); ?></h2>
				<?php if ( empty( $recent ) ) : ?>
					<p><?php esc_html_e( 'No quizzes yet.', 'gmcq' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $recent as $q ) : ?>
							<li><?php echo esc_html( $q->post_title ); ?> (<?php echo esc_html( $q->status ); ?>)</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>

		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Last Import', 'gmcq' ); ?></h2>
			<?php if ( ! $last_import ) : ?>
				<p><?php esc_html_e( 'No imports yet.', 'gmcq' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-import' ) ); ?>"><?php esc_html_e( 'Import CSV', 'gmcq' ); ?></a></p>
			<?php else : ?>
				<p>
					<strong><?php echo esc_html( $last_import->filename ); ?></strong> —
					<?php echo esc_html( $last_import->status ); ?> —
					<?php echo (int) $last_import->imported; ?>/<?php echo (int) $last_import->total_rows; ?>
					(<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-import' ) ); ?>"><?php esc_html_e( 'View imports', 'gmcq' ); ?></a>)
				</p>
			<?php endif; ?>
		</div>

		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Quick Start', 'gmcq' ); ?></h2>
			<ol>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-categories' ) ); ?>"><?php esc_html_e( 'Create Categories', 'gmcq' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-questions' ) ); ?>"><?php esc_html_e( 'Add Questions', 'gmcq' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-quizzes' ) ); ?>"><?php esc_html_e( 'Create Quizzes', 'gmcq' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-reports' ) ); ?>"><?php esc_html_e( 'View Reports', 'gmcq' ); ?></a></li>
			</ol>
		</div>
	</div>
	<?php
}
