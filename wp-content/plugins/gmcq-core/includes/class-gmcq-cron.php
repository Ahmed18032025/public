<?php
/**
 * GMCQ Cron Jobs — daily recalculation + weekly backup cleanup.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_register_weekly_schedule( array $schedules ): array {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'gmcq' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'gmcq_register_weekly_schedule' );

function gmcq_schedule_cron_jobs(): void {
	if ( ! wp_next_scheduled( 'gmcq_daily_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'gmcq_daily_cron' );
	}
	if ( ! wp_next_scheduled( 'gmcq_weekly_cron' ) ) {
		wp_schedule_event( time(), 'weekly', 'gmcq_weekly_cron' );
	}
}

function gmcq_clear_cron_jobs(): void {
	wp_clear_scheduled_hook( 'gmcq_daily_cron' );
	wp_clear_scheduled_hook( 'gmcq_weekly_cron' );
}

function gmcq_run_daily_cron(): void {
	if ( function_exists( 'gmcq_recalculate_category_counts' ) ) {
		gmcq_recalculate_category_counts();
	}
	if ( function_exists( 'gmcq_recalculate_usage_counts' ) ) {
		gmcq_recalculate_usage_counts();
	}
	if ( function_exists( 'gmcq_recalculate_quiz_stats' ) ) {
		gmcq_recalculate_quiz_stats();
	}
}
add_action( 'gmcq_daily_cron', 'gmcq_run_daily_cron' );

function gmcq_run_weekly_cron(): void {
	if ( function_exists( 'gmcq_cleanup_old_backups' ) ) {
		gmcq_cleanup_old_backups();
	}
}
add_action( 'gmcq_weekly_cron', 'gmcq_run_weekly_cron' );

function gmcq_recalculate_quiz_stats(): void {
	global $wpdb;
	$p = $wpdb->prefix;

	$wpdb->query(
		"UPDATE {$p}gmcq_quizzes_meta zm
		 SET zm.question_count = (
		     SELECT COUNT(*) FROM {$p}gmcq_question_map qm WHERE qm.quiz_id = zm.quiz_id
		 ),
		 zm.attempt_count = (
		     SELECT COUNT(*) FROM {$p}gmcq_attempts a
		     WHERE a.quiz_id = zm.quiz_id AND a.status = 'completed' AND a.is_active = 1
		 )
		 WHERE zm.is_active = 1"
	);
}

function gmcq_cleanup_old_backups(): void {
	$days = (int) gmcq_get_setting( 'backup_retention_days', 90 );
	if ( 0 === $days ) {
		return;
	}

	$cutoff     = strtotime( "-{$days} days" );
	$backup_dir = wp_upload_dir()['basedir'] . '/gmcq-backups';
	$backups    = get_option( 'gmcq_backup_index', array() );
	$remaining  = array();
	$max_files  = (int) gmcq_get_setting( 'max_backup_files', 50 );

	foreach ( $backups as $backup ) {
		$created = strtotime( $backup['created'] ?? '' );
		if ( $created && $created < $cutoff ) {
			$filepath = $backup_dir . '/' . ( $backup['file'] ?? '' );
			if ( file_exists( $filepath ) ) {
				wp_delete_file( $filepath );
			}
		} else {
			$remaining[] = $backup;
		}
	}

	if ( count( $remaining ) > $max_files ) {
		usort(
			$remaining,
			static function ( $a, $b ) {
				return strtotime( $a['created'] ?? '' ) <=> strtotime( $b['created'] ?? '' );
			}
		);
		while ( count( $remaining ) > $max_files ) {
			$oldest   = array_shift( $remaining );
			$filepath = $backup_dir . '/' . ( $oldest['file'] ?? '' );
			if ( file_exists( $filepath ) ) {
				wp_delete_file( $filepath );
			}
		}
	}

	update_option( 'gmcq_backup_index', $remaining );
}
