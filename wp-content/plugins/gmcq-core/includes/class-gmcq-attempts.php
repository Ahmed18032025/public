<?php
/**
 * GMCQ Attempts — start, submit answers, complete, rate limiting.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_check_attempt_rate_limit( int $quiz_id ): bool|WP_Error {
	$ip          = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$max_per_day = (int) gmcq_get_setting( 'max_attempts_per_ip_per_day', 50 );
	if ( 0 === $max_per_day ) {
		return true;
	}

	global $wpdb;
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_attempts
			 WHERE ip_address = %s AND quiz_id = %d AND DATE(started_at) = CURDATE()",
			$ip,
			$quiz_id
		)
	);

	if ( $count >= $max_per_day ) {
		return new WP_Error( 'rate_limited', __( 'Too many attempts. Please try again tomorrow.', 'gmcq' ) );
	}
	return true;
}

function gmcq_start_attempt( int $quiz_id ) {
	$meta = gmcq_get_quiz_meta( $quiz_id );
	if ( ! $meta || 1 !== (int) $meta->is_active || 'published' !== $meta->status ) {
		return new WP_Error( 'quiz_unavailable', __( 'Quiz is not available.', 'gmcq' ) );
	}

	if ( (int) $meta->require_login && ! is_user_logged_in() ) {
		return new WP_Error( 'login_required', __( 'You must be logged in to take this quiz.', 'gmcq' ) );
	}

	$rate = gmcq_check_attempt_rate_limit( $quiz_id );
	if ( is_wp_error( $rate ) ) {
		return $rate;
	}

	if ( (int) $meta->max_attempts > 0 && is_user_logged_in() ) {
		global $wpdb;
		$user_attempts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_attempts
				 WHERE quiz_id = %d AND user_id = %d AND status = 'completed' AND is_active = 1",
				$quiz_id,
				get_current_user_id()
			)
		);
		if ( $user_attempts >= (int) $meta->max_attempts ) {
			return new WP_Error( 'max_attempts', __( 'Maximum attempts reached for this quiz.', 'gmcq' ) );
		}
	}

	global $wpdb;
	$questions = gmcq_get_quiz_questions( $quiz_id );
	if ( empty( $questions ) ) {
		return new WP_Error( 'no_questions', __( 'This quiz has no questions.', 'gmcq' ) );
	}

	$session_id = '';
	if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
		$session_id = session_id();
	}
	if ( ! $session_id ) {
		$session_id = md5( uniqid( '', true ) );
	}

	$wpdb->insert(
		$wpdb->prefix . 'gmcq_attempts',
		array(
			'quiz_id'         => $quiz_id,
			'user_id'         => is_user_logged_in() ? get_current_user_id() : null,
			'total_questions' => count( $questions ),
			'status'          => 'in_progress',
			'ip_address'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
			'session_id'      => $session_id,
		),
		array( '%d', '%d', '%d', '%s', '%s', '%s' )
	);

	$attempt_id = (int) $wpdb->insert_id;
	do_action( 'gmcq_attempt_started', $attempt_id, $quiz_id );

	return $attempt_id;
}

function gmcq_get_attempt( int $attempt_id ): ?object {
	global $wpdb;
	$attempt = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_attempts WHERE id = %d",
			$attempt_id
		)
	);
	if ( ! $attempt ) {
		return null;
	}
	$attempt->answers = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_attempt_answers WHERE attempt_id = %d",
			$attempt_id
		)
	);
	return $attempt;
}

function gmcq_submit_answer( int $attempt_id, int $question_id, array $selected_answer_ids, int $time_spent = 0 ) {
	global $wpdb;

	$attempt = gmcq_get_attempt( $attempt_id );
	if ( ! $attempt || 'in_progress' !== $attempt->status ) {
		return new WP_Error( 'invalid_attempt', __( 'Attempt is not in progress.', 'gmcq' ) );
	}

	$question = gmcq_get_question( $question_id );
	if ( ! $question ) {
		return new WP_Error( 'invalid_question', __( 'Question not found.', 'gmcq' ) );
	}

	$meta = gmcq_get_quiz_meta( (int) $attempt->quiz_id );
	$map  = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_question_map WHERE quiz_id = %d AND question_id = %d",
			$attempt->quiz_id,
			$question_id
		)
	);

	$marks          = $map && null !== $map->marks ? (float) $map->marks : (float) $meta->default_marks;
	$negative_marks = $map && null !== $map->negative_marks ? (float) $map->negative_marks : (float) $meta->default_negative_marks;

	$wpdb->delete(
		$wpdb->prefix . 'gmcq_attempt_answers',
		array(
			'attempt_id'  => $attempt_id,
			'question_id' => $question_id,
		),
		array( '%d', '%d' )
	);

	if ( empty( $selected_answer_ids ) ) {
		$wpdb->insert(
			$wpdb->prefix . 'gmcq_attempt_answers',
			array(
				'attempt_id'         => $attempt_id,
				'question_id'        => $question_id,
				'selected_answer_id'   => null,
				'is_correct'           => 0,
				'marks_obtained'       => 0,
				'time_spent'           => $time_spent,
			),
			array( '%d', '%d', '%d', '%d', '%f', '%d' )
		);
		return true;
	}

	$correct_ids = array();
	foreach ( $question->answers as $ans ) {
		if ( (int) $ans->is_correct ) {
			$correct_ids[] = (int) $ans->id;
		}
	}

	sort( $correct_ids );
	$selected = array_map( 'intval', $selected_answer_ids );
	sort( $selected );

	$is_correct = ( $selected === $correct_ids );
	$marks_obtained = $is_correct ? $marks : ( $negative_marks > 0 ? -$negative_marks : 0 );

	foreach ( $selected as $aid ) {
		$wpdb->insert(
			$wpdb->prefix . 'gmcq_attempt_answers',
			array(
				'attempt_id'         => $attempt_id,
				'question_id'        => $question_id,
				'selected_answer_id' => $aid,
				'is_correct'         => $is_correct ? 1 : 0,
				'marks_obtained'     => $marks_obtained,
				'time_spent'         => $time_spent,
			),
			array( '%d', '%d', '%d', '%d', '%f', '%d' )
		);
	}

	return true;
}

function gmcq_complete_attempt( int $attempt_id, int $time_taken = 0 ) {
	global $wpdb;

	$attempt = gmcq_get_attempt( $attempt_id );
	if ( ! $attempt || 'in_progress' !== $attempt->status ) {
		return new WP_Error( 'invalid_attempt', __( 'Attempt is not in progress.', 'gmcq' ) );
	}

	$quiz_id = (int) $attempt->quiz_id;
	$meta    = gmcq_get_quiz_meta( $quiz_id );

	$stats = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
			    COUNT(DISTINCT question_id) AS answered_questions,
			    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_rows,
			    SUM(marks_obtained) AS score
			 FROM {$wpdb->prefix}gmcq_attempt_answers
			 WHERE attempt_id = %d",
			$attempt_id
		)
	);

	$total_questions = (int) $attempt->total_questions;
	$answered        = (int) ( $stats->answered_questions ?? 0 );

	$correct = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM (
			    SELECT question_id FROM {$wpdb->prefix}gmcq_attempt_answers
			    WHERE attempt_id = %d GROUP BY question_id HAVING MAX(is_correct) = 1
			 ) t",
			$attempt_id
		)
	);

	$skipped = max( 0, $total_questions - $answered );
	$wrong   = max( 0, $answered - $correct );
	$score   = (float) ( $stats->score ?? 0 );
	$max_score = gmcq_get_quiz_total_marks( $quiz_id );
	$percentage = $max_score > 0 ? round( ( $score / $max_score ) * 100, 2 ) : 0;
	$passed = $percentage >= (float) $meta->pass_percentage ? 1 : 0;

	$wpdb->update(
		$wpdb->prefix . 'gmcq_attempts',
		array(
			'score'             => $score,
			'max_score'         => $max_score,
			'percentage'        => $percentage,
			'correct_answers'   => $correct,
			'wrong_answers'     => $wrong,
			'skipped_questions' => $skipped,
			'time_taken'        => $time_taken,
			'status'            => 'completed',
			'passed'            => $passed,
			'completed_at'      => current_time( 'mysql' ),
		),
		array( 'id' => $attempt_id ),
		array( '%f', '%f', '%f', '%d', '%d', '%d', '%d', '%s', '%d', '%s' ),
		array( '%d' )
	);

	do_action( 'gmcq_attempt_completed', $quiz_id );

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}gmcq_quizzes_meta SET attempt_count = attempt_count + 1 WHERE quiz_id = %d",
			$quiz_id
		)
	);

	return gmcq_get_attempt( $attempt_id );
}

function gmcq_register_attempt_hooks(): void {
	add_action(
		'gmcq_attempt_started',
		static function ( int $attempt_id, int $quiz_id ): void {
			global $wpdb;

			$category_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT q.category_id FROM {$wpdb->prefix}gmcq_question_map qm
					 JOIN {$wpdb->prefix}gmcq_questions q ON q.id = qm.question_id
					 WHERE qm.quiz_id = %d AND q.category_id IS NOT NULL
					 ORDER BY qm.sort_order ASC LIMIT 1",
					$quiz_id
				)
			);

			$session_id = '';
			if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
				$session_id = session_id();
			}
			if ( ! $session_id ) {
				$session_id = md5( uniqid( '', true ) );
			}

			$wpdb->update(
				$wpdb->prefix . 'gmcq_attempts',
				array(
					'category_id' => $category_id ?: null,
					'ip_address'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
					'session_id'  => $session_id,
				),
				array( 'id' => $attempt_id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		},
		10,
		2
	);
}
gmcq_register_attempt_hooks();

function gmcq_register_attempt_ajax_handlers(): void {
	$actions = array(
		'gmcq_start_attempt',
		'gmcq_submit_answer',
		'gmcq_complete_attempt',
	);
	foreach ( $actions as $action ) {
		add_action( 'wp_ajax_' . $action, 'gmcq_ajax_' . str_replace( 'gmcq_', '', $action ) );
		add_action( 'wp_ajax_nopriv_' . $action, 'gmcq_ajax_' . str_replace( 'gmcq_', '', $action ) );
	}
}

function gmcq_verify_public_nonce(): bool {
	return check_ajax_referer( 'gmcq_public_nonce', '_ajax_nonce', false );
}

function gmcq_ajax_start_attempt(): void {
	if ( ! gmcq_verify_public_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'gmcq' ) ) );
	}
	$quiz_id = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;
	$result  = gmcq_start_attempt( $quiz_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( array( 'attempt_id' => (int) $result ) );
}

function gmcq_ajax_submit_answer(): void {
	if ( ! gmcq_verify_public_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'gmcq' ) ) );
	}
	$attempt_id = isset( $_POST['attempt_id'] ) ? (int) $_POST['attempt_id'] : 0;
	$question_id = isset( $_POST['question_id'] ) ? (int) $_POST['question_id'] : 0;
	$answers    = isset( $_POST['answer_ids'] ) ? array_map( 'intval', (array) $_POST['answer_ids'] ) : array();
	$time_spent = isset( $_POST['time_spent'] ) ? (int) $_POST['time_spent'] : 0;
	$result = gmcq_submit_answer( $attempt_id, $question_id, $answers, $time_spent );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( array( 'message' => __( 'Answer saved.', 'gmcq' ) ) );
}

function gmcq_ajax_complete_attempt(): void {
	if ( ! gmcq_verify_public_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'gmcq' ) ) );
	}
	$attempt_id = isset( $_POST['attempt_id'] ) ? (int) $_POST['attempt_id'] : 0;
	$time_taken = isset( $_POST['time_taken'] ) ? (int) $_POST['time_taken'] : 0;
	$result = gmcq_complete_attempt( $attempt_id, $time_taken );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( array( 'attempt' => $result ) );
}

gmcq_register_attempt_ajax_handlers();
