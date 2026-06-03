<?php

defined( 'ABSPATH' ) || exit;

function gmcq_create_tables(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix;
	$sqls            = gmcq_get_schema_sqls( $prefix, $charset_collate );

	foreach ( $sqls as $sql ) {
		dbDelta( $sql );
	}

	add_option( 'gmcq_settings', gmcq_get_default_settings() );
	update_option( 'gmcq_db_version', GMCQ_DB_VERSION );
}

function gmcq_get_schema_sqls( string $prefix, string $charset_collate ): array {
	return array(
		'gmcq_categories'      => gmcq_sql_categories( $prefix, $charset_collate ),
		'gmcq_imports'         => gmcq_sql_imports( $prefix, $charset_collate ),
		'gmcq_questions'       => gmcq_sql_questions( $prefix, $charset_collate ),
		'gmcq_answers'         => gmcq_sql_answers( $prefix, $charset_collate ),
		'gmcq_quizzes_meta'    => gmcq_sql_quizzes_meta( $prefix, $charset_collate ),
		'gmcq_question_map'    => gmcq_sql_question_map( $prefix, $charset_collate ),
		'gmcq_attempts'        => gmcq_sql_attempts( $prefix, $charset_collate ),
		'gmcq_attempt_answers' => gmcq_sql_attempt_answers( $prefix, $charset_collate ),
	);
}

function gmcq_get_schema_contract(): array {
	return array(
		'gmcq_categories'      => array(
			'columns'   => array(
				'id',
				'parent_id',
				'name',
				'slug',
				'description',
				'question_count',
				'sort_order',
				'is_active',
				'created_by',
				'created_at',
				'updated_at',
			),
			'forbidden' => array( 'deleted_at', 'deleted_by', 'sub_question_count' ),
		),
		'gmcq_imports'         => array(
			'columns'   => array(
				'id',
				'filename',
				'total_rows',
				'imported',
				'skipped_dupes',
				'skipped_errors',
				'status',
				'target_category_id',
				'target_quiz_id',
				'user_id',
				'error_log',
				'started_at',
				'completed_at',
			),
			'forbidden' => array( 'processed_rows', 'temp_file_path' ),
		),
		'gmcq_questions'       => array(
			'columns'   => array(
				'id',
				'category_id',
				'question_text',
				'question_hash',
				'question_type',
				'explanation',
				'difficulty',
				'marks',
				'negative_marks',
				'is_active',
				'usage_count',
				'import_id',
				'created_by',
				'created_at',
				'updated_at',
				'deleted_at',
				'deleted_by',
			),
			'forbidden' => array(),
		),
		'gmcq_answers'         => array(
			'columns'   => array( 'id', 'question_id', 'answer_text', 'is_correct', 'sort_order' ),
			'forbidden' => array(),
		),
		'gmcq_quizzes_meta'    => array(
			'columns'   => array(
				'id',
				'quiz_id',
				'category_id',
				'time_limit',
				'pass_percentage',
				'max_attempts',
				'shuffle_questions',
				'shuffle_answers',
				'show_explanations',
				'show_correct_answers',
				'require_login',
				'questions_per_page',
				'default_marks',
				'default_negative_marks',
				'status',
				'is_active',
				'question_count',
				'attempt_count',
				'created_at',
				'updated_at',
				'deleted_at',
				'deleted_by',
			),
			'forbidden' => array( 'category_auto', 'avg_score', 'total_marks' ),
		),
		'gmcq_question_map'    => array(
			'columns'   => array( 'id', 'quiz_id', 'question_id', 'sort_order', 'marks', 'negative_marks' ),
			'forbidden' => array(),
		),
		'gmcq_attempts'        => array(
			'columns'   => array(
				'id',
				'quiz_id',
				'user_id',
				'category_id',
				'score',
				'max_score',
				'percentage',
				'total_questions',
				'correct_answers',
				'wrong_answers',
				'skipped_questions',
				'time_taken',
				'status',
				'passed',
				'is_active',
				'ip_address',
				'session_id',
				'started_at',
				'completed_at',
			),
			'forbidden' => array( 'original_category_id', 'quiz_title' ),
		),
		'gmcq_attempt_answers' => array(
			'columns'   => array(
				'id',
				'attempt_id',
				'question_id',
				'selected_answer_id',
				'is_correct',
				'marks_obtained',
				'time_spent',
			),
			'forbidden' => array(),
		),
	);
}

function gmcq_validate_schema(): array {
	global $wpdb;

	$prefix          = isset( $wpdb ) && isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';
	$charset_collate = isset( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' )
		? $wpdb->get_charset_collate()
		: 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
	$sqls            = gmcq_get_schema_sqls( $prefix, $charset_collate );
	$contract        = gmcq_get_schema_contract();
	$errors          = array();

	if ( count( $sqls ) !== 8 ) {
		$errors[] = 'Phase 1 schema must define exactly 8 tables.';
	}

	foreach ( $contract as $table => $definition ) {
		if ( ! isset( $sqls[ $table ] ) ) {
			$errors[] = "Missing schema SQL for {$table}.";
			continue;
		}

		$sql = $sqls[ $table ];

		foreach ( $definition['columns'] as $column ) {
			if ( ! preg_match( '/\n\s*' . preg_quote( $column, '/' ) . '\s+/i', $sql ) ) {
				$errors[] = "Missing {$table}.{$column}.";
			}
		}

		foreach ( $definition['forbidden'] as $column ) {
			if ( preg_match( '/\n\s*' . preg_quote( $column, '/' ) . '\s+/i', $sql ) ) {
				$errors[] = "Forbidden Phase 1 column {$table}.{$column} is present.";
			}
		}
	}

	foreach ( array( 'gmcq_activity_log', 'gmcq_attempt_answers_archive' ) as $table ) {
		if ( isset( $sqls[ $table ] ) ) {
			$errors[] = "Forbidden Phase 1 table {$table} is present.";
		}
	}

	return array(
		'valid'  => empty( $errors ),
		'errors' => $errors,
		'tables' => array_keys( $sqls ),
	);
}

function gmcq_drop_tables(): void {
	global $wpdb;

	$prefix = $wpdb->prefix;
	$tables = array(
		'gmcq_attempt_answers',
		'gmcq_attempts',
		'gmcq_question_map',
		'gmcq_quizzes_meta',
		'gmcq_answers',
		'gmcq_questions',
		'gmcq_imports',
		'gmcq_categories',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" );
	}

	delete_option( 'gmcq_settings' );
	delete_option( 'gmcq_db_version' );
	delete_option( 'gmcq_old_quiz_slug' );
	delete_option( 'gmcq_backup_index' );
}

function gmcq_sql_categories( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_categories (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		parent_id bigint(20) unsigned DEFAULT NULL,
		name varchar(255) NOT NULL,
		slug varchar(255) NOT NULL,
		description text DEFAULT NULL,
		question_count int(11) DEFAULT 0,
		sort_order int(11) DEFAULT 0,
		is_active tinyint(1) DEFAULT 1,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY idx_slug (slug),
		KEY idx_parent_active (parent_id, is_active),
		KEY idx_active_created (is_active, created_at)
	) {$charset_collate};";
}

function gmcq_sql_questions( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_questions (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		category_id bigint(20) unsigned DEFAULT NULL,
		question_text text NOT NULL,
		question_hash char(32) NOT NULL,
		question_type enum('mcq_single','mcq_multiple','true_false') DEFAULT 'mcq_single',
		explanation text DEFAULT NULL,
		difficulty enum('easy','medium','hard') DEFAULT 'medium',
		marks decimal(5,2) DEFAULT 1.00,
		negative_marks decimal(5,2) DEFAULT 0.25,
		is_active tinyint(1) DEFAULT 1,
		usage_count int(11) DEFAULT 0,
		import_id bigint(20) unsigned DEFAULT NULL,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		deleted_at datetime DEFAULT NULL,
		deleted_by bigint(20) unsigned DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY idx_question_hash (question_hash),
		KEY idx_category_active (category_id, is_active),
		KEY idx_difficulty (difficulty),
		KEY idx_is_active (is_active),
		KEY idx_question_type (question_type),
		KEY idx_import (import_id),
		KEY idx_usage (usage_count),
		FULLTEXT KEY idx_question_text (question_text)
	) {$charset_collate};";
}

function gmcq_sql_answers( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_answers (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		question_id bigint(20) unsigned NOT NULL,
		answer_text text NOT NULL,
		is_correct tinyint(1) DEFAULT 0,
		sort_order int(11) DEFAULT 0,
		PRIMARY KEY  (id),
		KEY idx_question (question_id),
		KEY idx_question_correct (question_id, is_correct)
	) {$charset_collate};";
}

function gmcq_sql_quizzes_meta( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_quizzes_meta (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		quiz_id bigint(20) unsigned NOT NULL,
		category_id bigint(20) unsigned DEFAULT NULL,
		time_limit int(11) DEFAULT 0,
		pass_percentage decimal(5,2) DEFAULT 40.00,
		max_attempts int(11) DEFAULT 0,
		shuffle_questions tinyint(1) DEFAULT 1,
		shuffle_answers tinyint(1) DEFAULT 1,
		show_explanations tinyint(1) DEFAULT 1,
		show_correct_answers tinyint(1) DEFAULT 1,
		require_login tinyint(1) DEFAULT 0,
		questions_per_page int(11) DEFAULT 20,
		default_marks decimal(5,2) DEFAULT 1.00,
		default_negative_marks decimal(5,2) DEFAULT 0.25,
		status enum('draft','published') DEFAULT 'draft',
		is_active tinyint(1) DEFAULT 1,
		question_count int(11) DEFAULT 0,
		attempt_count int(11) DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		deleted_at datetime DEFAULT NULL,
		deleted_by bigint(20) unsigned DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY idx_quiz_id (quiz_id),
		KEY idx_category (category_id),
		KEY idx_status (status),
		KEY idx_status_active (status, is_active),
		KEY idx_question_count (question_count)
	) {$charset_collate};";
}

function gmcq_sql_question_map( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_question_map (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		quiz_id bigint(20) unsigned NOT NULL,
		question_id bigint(20) unsigned NOT NULL,
		sort_order int(11) DEFAULT 0,
		marks decimal(5,2) DEFAULT NULL,
		negative_marks decimal(5,2) DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY idx_quiz_question (quiz_id, question_id),
		KEY idx_quiz_order (quiz_id, sort_order),
		KEY idx_question (question_id)
	) {$charset_collate};";
}

function gmcq_sql_attempts( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_attempts (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		quiz_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		category_id bigint(20) unsigned DEFAULT NULL,
		score decimal(8,2) DEFAULT 0,
		max_score decimal(8,2) DEFAULT 0,
		percentage decimal(5,2) DEFAULT 0,
		total_questions int(11) DEFAULT 0,
		correct_answers int(11) DEFAULT 0,
		wrong_answers int(11) DEFAULT 0,
		skipped_questions int(11) DEFAULT 0,
		time_taken int(11) DEFAULT 0,
		status enum('in_progress','completed') DEFAULT 'in_progress',
		passed tinyint(1) DEFAULT NULL,
		is_active tinyint(1) DEFAULT 1,
		ip_address varchar(45) DEFAULT NULL,
		session_id varchar(64) DEFAULT NULL,
		started_at datetime DEFAULT CURRENT_TIMESTAMP,
		completed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY idx_quiz_id (quiz_id),
		KEY idx_user_id (user_id),
		KEY idx_status (status),
		KEY idx_started_at (started_at),
		KEY idx_quiz_user (quiz_id, user_id),
		KEY idx_category_started (category_id, started_at),
		KEY idx_quiz_status_date (quiz_id, status, started_at),
		KEY idx_user_date (user_id, started_at),
		KEY idx_ip_quiz_date (ip_address, quiz_id, started_at)
	) {$charset_collate};";
}

function gmcq_sql_attempt_answers( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_attempt_answers (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		attempt_id bigint(20) unsigned NOT NULL,
		question_id bigint(20) unsigned NOT NULL,
		selected_answer_id bigint(20) unsigned DEFAULT NULL,
		is_correct tinyint(1) DEFAULT 0,
		marks_obtained decimal(5,2) DEFAULT 0,
		time_spent int(11) DEFAULT 0,
		PRIMARY KEY  (id),
		KEY idx_attempt (attempt_id),
		KEY idx_question (question_id),
		KEY idx_attempt_question (attempt_id, question_id)
	) {$charset_collate};";
}

function gmcq_sql_imports( string $prefix, string $charset_collate ): string {
	return "CREATE TABLE {$prefix}gmcq_imports (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		filename varchar(255) NOT NULL,
		total_rows int(11) DEFAULT 0,
		imported int(11) DEFAULT 0,
		skipped_dupes int(11) DEFAULT 0,
		skipped_errors int(11) DEFAULT 0,
		status enum('pending','processing','completed','failed') DEFAULT 'pending',
		target_category_id bigint(20) unsigned DEFAULT NULL,
		target_quiz_id bigint(20) unsigned DEFAULT NULL,
		user_id bigint(20) unsigned NOT NULL,
		error_log json DEFAULT NULL,
		started_at datetime DEFAULT CURRENT_TIMESTAMP,
		completed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY idx_status (status),
		KEY idx_user (user_id),
		KEY idx_started (started_at DESC)
	) {$charset_collate};";
}
