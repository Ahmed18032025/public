<?php
/**
 * GMCQ Reports — summary cards, filterable list, chunked CSV export.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_build_attempts_where( array $filters ): string {
	global $wpdb;
	$parts = array();

	if ( ! empty( $filters['quiz_id'] ) ) {
		$parts[] = $wpdb->prepare( ' AND quiz_id = %d', (int) $filters['quiz_id'] );
	}
	if ( ! empty( $filters['user_id'] ) ) {
		$parts[] = $wpdb->prepare( ' AND user_id = %d', (int) $filters['user_id'] );
	}
	if ( ! empty( $filters['category_id'] ) ) {
		$parts[] = $wpdb->prepare( ' AND category_id = %d', (int) $filters['category_id'] );
	}
	if ( ! empty( $filters['date_from'] ) ) {
		$parts[] = $wpdb->prepare( ' AND started_at >= %s', sanitize_text_field( $filters['date_from'] ) . ' 00:00:00' );
	}
	if ( ! empty( $filters['date_to'] ) ) {
		$parts[] = $wpdb->prepare( ' AND started_at <= %s', sanitize_text_field( $filters['date_to'] ) . ' 23:59:59' );
	}
	if ( ! empty( $filters['passed'] ) && in_array( $filters['passed'], array( '0', '1' ), true ) ) {
		$parts[] = $wpdb->prepare( ' AND passed = %d', (int) $filters['passed'] );
	}

	return implode( '', $parts );
}

function gmcq_get_reports_summary( array $filters ): array {
	$cache_key = 'gmcq_reports_summary_' . md5( wp_json_encode( $filters ) );
	$summary   = get_transient( $cache_key );
	if ( false !== $summary ) {
		return $summary;
	}

	global $wpdb;
	$where = gmcq_build_attempts_where( $filters );

	$summary = $wpdb->get_row(
		"SELECT COUNT(*) AS total_attempts,
		        COALESCE(AVG(percentage), 0) AS avg_score,
		        COALESCE(SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 0) AS pass_rate,
		        COALESCE(AVG(time_taken), 0) AS avg_time
		 FROM {$wpdb->prefix}gmcq_attempts
		 WHERE status = 'completed' AND is_active = 1 {$where}",
		ARRAY_A
	);

	$cache_ttl = (int) gmcq_get_setting( 'reports_cache_ttl', 300 );
	set_transient( $cache_key, $summary ?: array(), $cache_ttl );
	return $summary ?: array();
}

function gmcq_get_attempts_chunk( array $filters, int $offset, int $limit ): array {
	global $wpdb;
	$p     = $wpdb->prefix;
	$where = gmcq_build_attempts_where( $filters );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.*, u.display_name AS user_name, u.user_email, c.name AS category_name
			 FROM {$p}gmcq_attempts a
			 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
			 LEFT JOIN {$p}gmcq_categories c ON c.id = a.category_id
			 WHERE a.status = 'completed' AND a.is_active = 1 {$where}
			 ORDER BY a.started_at DESC
			 LIMIT %d OFFSET %d",
			$limit,
			$offset
		)
	) ?: array();
}

function gmcq_get_attempts_list( array $filters, int $page = 1, int $per_page = 20 ): array {
	global $wpdb;
	$where = gmcq_build_attempts_where( $filters );

	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_attempts
		 WHERE status = 'completed' AND is_active = 1 {$where}"
	);

	$offset = ( $page - 1 ) * $per_page;
	$rows   = gmcq_get_attempts_chunk( $filters, $offset, $per_page );

	return array(
		'results'  => $rows,
		'total'    => $total,
		'page'     => $page,
		'per_page' => $per_page,
	);
}

function gmcq_export_attempts( array $filters ): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'gmcq' ) );
	}

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=attempts-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
	fputcsv(
		$output,
		array(
			'User', 'Email', 'Quiz', 'Category', 'Score', 'Max Score',
			'Percentage', 'Pass/Fail', 'Correct', 'Wrong', 'Skipped', 'Time Taken (s)', 'Date',
		)
	);

	$offset     = 0;
	$chunk_size = 1000;
	while ( true ) {
		$rows = gmcq_get_attempts_chunk( $filters, $offset, $chunk_size );
		if ( empty( $rows ) ) {
			break;
		}
		foreach ( $rows as $row ) {
			$quiz_title = gmcq_get_attempt_quiz_title( (int) $row->quiz_id );
			fputcsv(
				$output,
				array(
					$row->user_name ?? 'Guest',
					$row->user_email ?? '',
					$quiz_title,
					$row->category_name ?? '',
					$row->score,
					$row->max_score,
					$row->percentage . '%',
					(int) $row->passed ? 'Pass' : 'Fail',
					$row->correct_answers,
					$row->wrong_answers,
					$row->skipped_questions,
					$row->time_taken,
					$row->started_at,
				)
			);
		}
		$offset += $chunk_size;
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
	}
	fclose( $output );
	exit;
}

function gmcq_render_reports_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	if ( isset( $_GET['export'] ) && 'csv' === $_GET['export'] ) {
		check_admin_referer( 'gmcq_export_attempts' );
		$filters = array(
			'quiz_id'     => isset( $_GET['quiz_id'] ) ? (int) $_GET['quiz_id'] : 0,
			'category_id' => isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0,
			'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'passed'      => isset( $_GET['passed'] ) ? sanitize_text_field( wp_unslash( $_GET['passed'] ) ) : '',
		);
		gmcq_export_attempts( $filters );
	}

	$filters = array(
		'quiz_id'     => isset( $_GET['quiz_id'] ) ? (int) $_GET['quiz_id'] : 0,
		'category_id' => isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0,
		'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
		'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		'passed'      => isset( $_GET['passed'] ) ? sanitize_text_field( wp_unslash( $_GET['passed'] ) ) : '',
	);
	$page    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$summary = gmcq_get_reports_summary( $filters );
	$list    = gmcq_get_attempts_list( $filters, $page, 20 );
	$quizzes = gmcq_get_quizzes( array( 'per_page' => 100 ) );
	$cats    = gmcq_get_categories( array( 'per_page' => -1 ) );
	$export_url = wp_nonce_url(
		add_query_arg(
			array_merge( array( 'page' => 'gmcq-reports', 'export' => 'csv' ), array_filter( $filters ) ),
			admin_url( 'admin.php' )
		),
		'gmcq_export_attempts'
	);
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php esc_html_e( 'Reports', 'gmcq' ); ?></h1>
		<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
			<div class="gmcq-card"><strong><?php echo (int) ( $summary['total_attempts'] ?? 0 ); ?></strong><br><?php esc_html_e( 'Attempts', 'gmcq' ); ?></div>
			<div class="gmcq-card"><strong><?php echo round( (float) ( $summary['avg_score'] ?? 0 ), 1 ); ?>%</strong><br><?php esc_html_e( 'Avg Score', 'gmcq' ); ?></div>
			<div class="gmcq-card"><strong><?php echo round( (float) ( $summary['pass_rate'] ?? 0 ), 1 ); ?>%</strong><br><?php esc_html_e( 'Pass Rate', 'gmcq' ); ?></div>
			<div class="gmcq-card"><strong><?php echo round( (float) ( $summary['avg_time'] ?? 0 ) ); ?>s</strong><br><?php esc_html_e( 'Avg Time', 'gmcq' ); ?></div>
		</div>
		<div class="gmcq-card">
			<form method="get">
				<input type="hidden" name="page" value="gmcq-reports">
				<select name="quiz_id"><option value="0"><?php esc_html_e( 'All Quizzes', 'gmcq' ); ?></option>
				<?php foreach ( $quizzes['quizzes'] as $q ) : ?>
					<option value="<?php echo (int) $q->quiz_id; ?>" <?php selected( $filters['quiz_id'], (int) $q->quiz_id ); ?>><?php echo esc_html( $q->post_title ); ?></option>
				<?php endforeach; ?></select>
				<select name="category_id"><option value="0"><?php esc_html_e( 'All Categories', 'gmcq' ); ?></option>
				<?php foreach ( $cats['categories'] as $c ) : ?>
					<option value="<?php echo (int) $c->id; ?>" <?php selected( $filters['category_id'], (int) $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
				<?php endforeach; ?></select>
				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
				<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
				<select name="passed"><option value=""><?php esc_html_e( 'All', 'gmcq' ); ?></option>
					<option value="1" <?php selected( $filters['passed'], '1' ); ?>><?php esc_html_e( 'Pass', 'gmcq' ); ?></option>
					<option value="0" <?php selected( $filters['passed'], '0' ); ?>><?php esc_html_e( 'Fail', 'gmcq' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'gmcq' ); ?></button>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'gmcq' ); ?></a>
			</form>
			<table class="widefat striped" style="margin-top:15px">
				<thead><tr>
					<th><?php esc_html_e( 'User', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Quiz', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Score', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Pass', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Date', 'gmcq' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $list['results'] ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No attempts found.', 'gmcq' ); ?></td></tr>
				<?php else : foreach ( $list['results'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->user_name ?: 'Guest' ); ?></td>
						<td><?php echo esc_html( gmcq_get_attempt_quiz_title( (int) $row->quiz_id ) ); ?></td>
						<td><?php echo esc_html( $row->percentage ); ?>%</td>
						<td><?php echo (int) $row->passed ? esc_html__( 'Pass', 'gmcq' ) : esc_html__( 'Fail', 'gmcq' ); ?></td>
						<td><?php echo esc_html( $row->started_at ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
