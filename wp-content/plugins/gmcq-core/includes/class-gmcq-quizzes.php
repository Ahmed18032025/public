<?php
/**
 * GMCQ Quizzes — CPT, meta CRUD, question mapping, admin UI.
 */
defined( 'ABSPATH' ) || exit;

function gmcq_register_quiz_cpt(): void {
	$slug = sanitize_title( gmcq_get_setting( 'quiz_slug', 'quiz' ) );

	register_post_type(
		'gmcq_quiz',
		array(
			'labels'              => array(
				'name'          => __( 'Quizzes', 'gmcq' ),
				'singular_name' => __( 'Quiz', 'gmcq' ),
			),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'rewrite'             => array( 'slug' => $slug ),
			'capability_type'     => 'post',
			'has_archive'         => false,
			'supports'            => array( 'title', 'editor', 'author' ),
		)
	);
}
add_action( 'init', 'gmcq_register_quiz_cpt' );

function gmcq_get_quiz_meta( int $quiz_id ): ?object {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_quizzes_meta WHERE quiz_id = %d",
			$quiz_id
		)
	);
}

function gmcq_create_quiz( array $data ) {
	global $wpdb;

	$title = ! empty( $data['title'] ) ? sanitize_text_field( $data['title'] ) : __( 'Untitled Quiz', 'gmcq' );
	if ( '' === trim( $title ) ) {
		return new WP_Error( 'title_required', __( 'Quiz title is required.', 'gmcq' ) );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'gmcq_quiz',
			'post_title'  => $title,
			'post_status' => ! empty( $data['status'] ) && 'published' === $data['status'] ? 'publish' : 'draft',
			'post_author' => get_current_user_id(),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$meta = array(
		'quiz_id'                => (int) $post_id,
		'category_id'            => ! empty( $data['category_id'] ) ? (int) $data['category_id'] : null,
		'time_limit'             => isset( $data['time_limit'] ) ? (int) $data['time_limit'] : 0,
		'pass_percentage'        => isset( $data['pass_percentage'] ) ? (float) $data['pass_percentage'] : 40.00,
		'max_attempts'           => isset( $data['max_attempts'] ) ? (int) $data['max_attempts'] : 0,
		'shuffle_questions'      => ! empty( $data['shuffle_questions'] ) ? 1 : 0,
		'shuffle_answers'        => ! empty( $data['shuffle_answers'] ) ? 1 : 0,
		'show_explanations'      => ! isset( $data['show_explanations'] ) || ! empty( $data['show_explanations'] ) ? 1 : 0,
		'show_correct_answers'   => ! isset( $data['show_correct_answers'] ) || ! empty( $data['show_correct_answers'] ) ? 1 : 0,
		'require_login'          => ! empty( $data['require_login'] ) ? 1 : 0,
		'questions_per_page'     => isset( $data['questions_per_page'] ) ? (int) $data['questions_per_page'] : 20,
		'default_marks'          => isset( $data['default_marks'] ) ? (float) $data['default_marks'] : 1.00,
		'default_negative_marks' => isset( $data['default_negative_marks'] ) ? (float) $data['default_negative_marks'] : 0.25,
		'status'                 => ! empty( $data['status'] ) && 'published' === $data['status'] ? 'published' : 'draft',
		'is_active'              => 1,
	);

	$formats = array( '%d', '%d', '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%d' );
	if ( null === $meta['category_id'] ) {
		unset( $meta['category_id'] );
		array_splice( $formats, 1, 1 );
	}

	$wpdb->insert( $wpdb->prefix . 'gmcq_quizzes_meta', $meta, $formats );

	if ( ! empty( $wpdb->last_error ) ) {
		wp_delete_post( $post_id, true );
		return new WP_Error( 'db_error', $wpdb->last_error );
	}

	gmcq_clear_dashboard_cache( 'quiz' );
	do_action( 'gmcq_quiz_saved', (int) $post_id );

	return (int) $post_id;
}

function gmcq_update_quiz( int $quiz_id, array $data ) {
	global $wpdb;

	$post = get_post( $quiz_id );
	if ( ! $post || 'gmcq_quiz' !== $post->post_type ) {
		return new WP_Error( 'not_found', __( 'Quiz not found.', 'gmcq' ) );
	}

	if ( ! empty( $data['title'] ) ) {
		wp_update_post(
			array(
				'ID'         => $quiz_id,
				'post_title' => sanitize_text_field( $data['title'] ),
			)
		);
	}

	$update = array();
	$format = array();

	$fields = array(
		'category_id'            => '%d',
		'time_limit'             => '%d',
		'pass_percentage'        => '%f',
		'max_attempts'           => '%d',
		'shuffle_questions'      => '%d',
		'shuffle_answers'        => '%d',
		'show_explanations'      => '%d',
		'show_correct_answers'   => '%d',
		'require_login'          => '%d',
		'questions_per_page'     => '%d',
		'default_marks'          => '%f',
		'default_negative_marks' => '%f',
		'status'                 => '%s',
	);

	foreach ( $fields as $field => $fmt ) {
		if ( array_key_exists( $field, $data ) ) {
			$update[ $field ] = $data[ $field ];
			$format[]         = $fmt;
		}
	}

	if ( isset( $data['status'] ) ) {
		wp_update_post(
			array(
				'ID'          => $quiz_id,
				'post_status' => 'published' === $data['status'] ? 'publish' : 'draft',
			)
		);
	}

	if ( ! empty( $update ) ) {
		$wpdb->update(
			$wpdb->prefix . 'gmcq_quizzes_meta',
			$update,
			array( 'quiz_id' => $quiz_id ),
			$format,
			array( '%d' )
		);
	}

	gmcq_clear_dashboard_cache( 'quiz' );
	do_action( 'gmcq_quiz_saved', $quiz_id );

	return true;
}

function gmcq_archive_quiz( int $quiz_id ) {
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'gmcq_quizzes_meta',
		array(
			'is_active'  => 0,
			'deleted_at' => current_time( 'mysql' ),
			'deleted_by' => get_current_user_id(),
		),
		array( 'quiz_id' => $quiz_id ),
		array( '%d', '%s', '%d' ),
		array( '%d' )
	);
	gmcq_clear_dashboard_cache( 'quiz' );
	return true;
}

function gmcq_restore_quiz( int $quiz_id ) {
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'gmcq_quizzes_meta',
		array(
			'is_active'  => 1,
			'deleted_at' => null,
			'deleted_by' => null,
		),
		array( 'quiz_id' => $quiz_id ),
		array( '%d', '%s', '%d' ),
		array( '%d' )
	);
	gmcq_clear_dashboard_cache( 'quiz' );
	return true;
}

function gmcq_get_quizzes( array $args = array() ): array {
	global $wpdb;

	$defaults = array(
		'filter'   => 'all',
		'search'   => '',
		'page'     => 1,
		'per_page' => 20,
	);
	$args = wp_parse_args( $args, $defaults );
	$p    = $wpdb->prefix;

	$where   = array( '1=1' );
	$prepare = array();

	switch ( $args['filter'] ) {
		case 'published':
			$where[] = "zm.status = 'published' AND zm.is_active = 1";
			break;
		case 'draft':
			$where[] = "zm.status = 'draft' AND zm.is_active = 1";
			break;
		case 'archived':
			$where[] = 'zm.is_active = 0';
			break;
		case 'no_questions':
			$where[] = "zm.status = 'published' AND zm.is_active = 1 AND zm.question_count = 0";
			break;
	}

	if ( ! empty( $args['search'] ) ) {
		$like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[]   = '(p.post_title LIKE %s OR p.post_name LIKE %s)';
		$prepare[] = $like;
		$prepare[] = $like;
	}

	$where_clause = implode( ' AND ', $where );
	$total        = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta zm JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id WHERE {$where_clause}",
			$prepare
		)
	);

	$offset = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
	$prepare[] = (int) $args['per_page'];
	$prepare[] = $offset;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT zm.*, p.post_title, p.post_status, c.name AS category_name
			 FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 LEFT JOIN {$p}gmcq_categories c ON c.id = zm.category_id
			 WHERE {$where_clause}
			 ORDER BY zm.updated_at DESC
			 LIMIT %d OFFSET %d",
			$prepare
		)
	);

	return array(
		'quizzes' => $rows ?: array(),
		'total'   => $total,
	);
}

function gmcq_get_quiz_questions( int $quiz_id ): array {
	global $wpdb;
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT qm.*, q.question_text, q.question_type, q.difficulty
			 FROM {$wpdb->prefix}gmcq_question_map qm
			 JOIN {$wpdb->prefix}gmcq_questions q ON q.id = qm.question_id
			 WHERE qm.quiz_id = %d AND q.is_active = 1
			 ORDER BY qm.sort_order ASC, qm.id ASC",
			$quiz_id
		)
	) ?: array();
}

function gmcq_set_quiz_questions( int $quiz_id, array $question_ids ): bool|WP_Error {
	global $wpdb;
	$p = $wpdb->prefix;

	$max = (int) gmcq_get_setting( 'max_questions_per_quiz', 200 );
	if ( count( $question_ids ) > $max ) {
		return new WP_Error( 'too_many', sprintf( __( 'Maximum %d questions per quiz.', 'gmcq' ), $max ) );
	}

	$existing = $wpdb->get_col(
		$wpdb->prepare( "SELECT question_id FROM {$p}gmcq_question_map WHERE quiz_id = %d", $quiz_id )
	);

	$wpdb->query( 'START TRANSACTION' );

	try {
		$wpdb->delete( $p . 'gmcq_question_map', array( 'quiz_id' => $quiz_id ), array( '%d' ) );

		$sort = 0;
		foreach ( $question_ids as $qid ) {
			$qid = (int) $qid;
			if ( $qid <= 0 ) {
				continue;
			}
			$wpdb->insert(
				$p . 'gmcq_question_map',
				array(
					'quiz_id'     => $quiz_id,
					'question_id' => $qid,
					'sort_order'  => $sort++,
				),
				array( '%d', '%d', '%d' )
			);
		}

		if ( ! empty( $wpdb->last_error ) ) {
			throw new Exception( $wpdb->last_error );
		}

		$wpdb->query( 'COMMIT' );
	} catch ( Exception $e ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'db_error', $e->getMessage() );
	}

	$new_ids = array_map( 'intval', $question_ids );
	foreach ( array_diff( $existing, $new_ids ) as $removed_id ) {
		do_action( 'gmcq_question_removed_from_quiz', (int) $removed_id, $quiz_id );
	}
	foreach ( array_diff( $new_ids, $existing ) as $added_id ) {
		do_action( 'gmcq_question_added_to_quiz', (int) $added_id, $quiz_id );
	}

	do_action( 'gmcq_quiz_questions_changed', $quiz_id );
	gmcq_clear_dashboard_cache( 'quiz' );

	return true;
}

function gmcq_register_quiz_hooks(): void {
	add_action(
		'gmcq_quiz_questions_changed',
		static function ( int $quiz_id ): void {
			global $wpdb;
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_question_map WHERE quiz_id = %d",
					$quiz_id
				)
			);
			$wpdb->update(
				$wpdb->prefix . 'gmcq_quizzes_meta',
				array( 'question_count' => $count ),
				array( 'quiz_id' => $quiz_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	);

	add_action(
		'gmcq_quiz_saved',
		static function ( int $quiz_id ): void {
			$meta = gmcq_get_quiz_meta( $quiz_id );
			if ( $meta ) {
				wp_update_post(
					array(
						'ID'          => $quiz_id,
						'post_status' => 'published' === $meta->status ? 'publish' : 'draft',
					)
				);
			}
		}
	);
}
gmcq_register_quiz_hooks();

function gmcq_register_quiz_ajax_handlers(): void {
	add_action( 'wp_ajax_gmcq_save_quiz', 'gmcq_ajax_save_quiz' );
	add_action( 'wp_ajax_gmcq_archive_quiz', 'gmcq_ajax_archive_quiz' );
	add_action( 'wp_ajax_gmcq_restore_quiz', 'gmcq_ajax_restore_quiz' );
	add_action( 'wp_ajax_gmcq_set_quiz_questions', 'gmcq_ajax_set_quiz_questions' );
	add_action( 'wp_ajax_gmcq_search_questions_for_quiz', 'gmcq_ajax_search_questions_for_quiz' );
}

function gmcq_ajax_save_quiz(): void {
	check_ajax_referer( 'gmcq_quiz_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$quiz_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$data    = array(
		'title'                  => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
		'category_id'            => isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0,
		'time_limit'             => isset( $_POST['time_limit'] ) ? (int) $_POST['time_limit'] : 0,
		'pass_percentage'        => isset( $_POST['pass_percentage'] ) ? (float) $_POST['pass_percentage'] : 40,
		'max_attempts'           => isset( $_POST['max_attempts'] ) ? (int) $_POST['max_attempts'] : 0,
		'shuffle_questions'      => ! empty( $_POST['shuffle_questions'] ) ? 1 : 0,
		'shuffle_answers'        => ! empty( $_POST['shuffle_answers'] ) ? 1 : 0,
		'show_explanations'      => ! empty( $_POST['show_explanations'] ) ? 1 : 0,
		'show_correct_answers'   => ! empty( $_POST['show_correct_answers'] ) ? 1 : 0,
		'require_login'          => ! empty( $_POST['require_login'] ) ? 1 : 0,
		'questions_per_page'     => isset( $_POST['questions_per_page'] ) ? (int) $_POST['questions_per_page'] : 20,
		'default_marks'          => isset( $_POST['default_marks'] ) ? (float) $_POST['default_marks'] : 1,
		'default_negative_marks' => isset( $_POST['default_negative_marks'] ) ? (float) $_POST['default_negative_marks'] : 0.25,
		'status'                 => isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'draft',
	);

	if ( $quiz_id > 0 ) {
		$result = gmcq_update_quiz( $quiz_id, $data );
	} else {
		$result = gmcq_create_quiz( $data );
	}

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'quiz_id' => (int) $result, 'message' => __( 'Quiz saved.', 'gmcq' ) ) );
}

function gmcq_ajax_archive_quiz(): void {
	check_ajax_referer( 'gmcq_quiz_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	gmcq_archive_quiz( $id );
	wp_send_json_success( array( 'message' => __( 'Quiz archived.', 'gmcq' ) ) );
}

function gmcq_ajax_restore_quiz(): void {
	check_ajax_referer( 'gmcq_quiz_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	gmcq_restore_quiz( $id );
	wp_send_json_success( array( 'message' => __( 'Quiz restored.', 'gmcq' ) ) );
}

function gmcq_ajax_set_quiz_questions(): void {
	check_ajax_referer( 'gmcq_quiz_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	$quiz_id = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;
	$ids     = isset( $_POST['question_ids'] ) ? array_map( 'intval', (array) $_POST['question_ids'] ) : array();
	$result  = gmcq_set_quiz_questions( $quiz_id, $ids );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( array( 'message' => __( 'Questions updated.', 'gmcq' ) ) );
}

function gmcq_ajax_search_questions_for_quiz(): void {
	check_ajax_referer( 'gmcq_quiz_nonce' );
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$category_id   = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
	$difficulty    = isset( $_GET['difficulty'] ) ? sanitize_text_field( wp_unslash( $_GET['difficulty'] ) ) : '';
	$question_type = isset( $_GET['question_type'] ) ? sanitize_text_field( wp_unslash( $_GET['question_type'] ) ) : '';
	$recent        = ! empty( $_GET['recent'] );

	$search_args = array(
		'filter'        => 'active',
		'category_id'   => $category_id,
		'difficulty'    => $difficulty,
		'question_type' => $question_type,
		'per_page'      => 20,
	);

	if ( $recent ) {
		$search_args['query']  = '';
		$search_args['filter'] = 'all';
		$search_args['page']   = 1;
	} else {
		$search_args['query'] = $q;
	}

	$result = gmcq_search_questions( $search_args );
	wp_send_json_success( $result );
}

function gmcq_render_quizzes_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	if ( 'add' === $action || ( 'edit' === $action && isset( $_GET['id'] ) ) ) {
		gmcq_render_quiz_form( 'edit' === $action ? (int) $_GET['id'] : 0 );
		return;
	}
	if ( 'questions' === $action && isset( $_GET['id'] ) ) {
		gmcq_render_quiz_questions_page( (int) $_GET['id'] );
		return;
	}

	$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$data   = gmcq_get_quizzes( array( 'filter' => $filter, 'search' => $search, 'page' => $page ) );
	$base   = admin_url( 'admin.php?page=gmcq-quizzes' );
	$nonce  = wp_create_nonce( 'gmcq_quiz_nonce' );
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php printf( '<a href="%s">%s</a> &rsaquo; %s', esc_url( admin_url( 'admin.php?page=gmcq-dashboard' ) ), esc_html__( 'GMCQ', 'gmcq' ), esc_html__( 'Quizzes', 'gmcq' ) ); ?></h1>
		<div class="gmcq-card">
			<p><a href="<?php echo esc_url( $base . '&action=add' ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Quiz', 'gmcq' ); ?></a></p>
			<div class="gmcq-filter-tabs">
				<?php
				foreach ( array( 'all' => __( 'All', 'gmcq' ), 'published' => __( 'Published', 'gmcq' ), 'draft' => __( 'Draft', 'gmcq' ), 'archived' => __( 'Archived', 'gmcq' ), 'no_questions' => __( 'No Questions', 'gmcq' ) ) as $key => $label ) :
					printf( '<a href="%s" class="%s">%s</a>', esc_url( $base . '&filter=' . $key ), $key === $filter ? 'current' : '', esc_html( $label ) );
				endforeach;
				?>
			</div>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Title', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Category', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Questions', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Attempts', 'gmcq' ); ?></th>
					<th><?php esc_html_e( 'Status', 'gmcq' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $data['quizzes'] ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No quizzes found.', 'gmcq' ); ?></td></tr>
				<?php else : foreach ( $data['quizzes'] as $quiz ) : ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $base . '&action=edit&id=' . $quiz->quiz_id ); ?>"><?php echo esc_html( $quiz->post_title ); ?></a></strong>
							<div class="row-actions">
								<a href="<?php echo esc_url( $base . '&action=questions&id=' . $quiz->quiz_id ); ?>"><?php esc_html_e( 'Manage Questions', 'gmcq' ); ?></a>
								<?php if ( 1 === (int) $quiz->is_active ) : ?>
									| <a href="#" class="gmcq-archive-quiz" data-id="<?php echo (int) $quiz->quiz_id; ?>"><?php esc_html_e( 'Archive', 'gmcq' ); ?></a>
								<?php else : ?>
									| <a href="#" class="gmcq-restore-quiz" data-id="<?php echo (int) $quiz->quiz_id; ?>"><?php esc_html_e( 'Restore', 'gmcq' ); ?></a>
								<?php endif; ?>
							</div>
						</td>
						<td><?php echo ! empty( $quiz->category_name ) ? esc_html( $quiz->category_name ) : '<em>' . esc_html__( 'None', 'gmcq' ) . '</em>'; ?></td>
						<td><?php echo (int) $quiz->question_count; ?></td>
						<td><?php echo (int) $quiz->attempt_count; ?></td>
						<td><?php echo 0 === (int) $quiz->is_active ? '<span class="gmcq-status-inactive">' . esc_html__( 'Archived', 'gmcq' ) . '</span>' : '<span class="gmcq-status-ok">' . esc_html( ucfirst( $quiz->status ) ) . '</span>'; ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<script>
	jQuery(function($){
		var nonce = '<?php echo esc_js( $nonce ); ?>';
		$('.gmcq-archive-quiz,.gmcq-restore-quiz').on('click', function(e){
			e.preventDefault();
			var action = $(this).hasClass('gmcq-archive-quiz') ? 'gmcq_archive_quiz' : 'gmcq_restore_quiz';
			$.post(gmcqAdmin.ajaxUrl, {action: action, id: $(this).data('id'), _ajax_nonce: nonce}, function(){ location.reload(); });
		});
	});
	</script>
	<?php
}

function gmcq_render_quiz_form( int $quiz_id ): void {
	$post = $quiz_id ? get_post( $quiz_id ) : null;
	$meta = $quiz_id ? gmcq_get_quiz_meta( $quiz_id ) : null;
	$cats = gmcq_get_categories( array( 'filter' => 'active', 'per_page' => -1 ) );
	$base = admin_url( 'admin.php?page=gmcq-quizzes' );
	$nonce = wp_create_nonce( 'gmcq_quiz_nonce' );
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php echo $quiz_id ? esc_html__( 'Edit Quiz', 'gmcq' ) : esc_html__( 'Add Quiz', 'gmcq' ); ?></h1>
		<div class="gmcq-card" style="max-width:800px">
			<form id="gmcq-quiz-form">
				<input type="hidden" name="id" value="<?php echo (int) $quiz_id; ?>">
				<table class="form-table">
					<tr><th><label><?php esc_html_e( 'Title', 'gmcq' ); ?></label></th>
					<td><input type="text" name="title" class="regular-text" required value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>"></td></tr>
					<tr><th><label><?php esc_html_e( 'Category (metadata)', 'gmcq' ); ?></label></th>
					<td><select name="category_id"><option value="0"><?php esc_html_e( 'None', 'gmcq' ); ?></option>
					<?php foreach ( $cats['categories'] as $c ) : ?>
						<option value="<?php echo (int) $c->id; ?>" <?php selected( $meta ? (int) $meta->category_id : 0, (int) $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?></select></td></tr>
					<tr><th><label><?php esc_html_e( 'Status', 'gmcq' ); ?></label></th>
					<td><select name="status">
						<option value="draft" <?php selected( $meta ? $meta->status : 'draft', 'draft' ); ?>><?php esc_html_e( 'Draft', 'gmcq' ); ?></option>
						<option value="published" <?php selected( $meta ? $meta->status : '', 'published' ); ?>><?php esc_html_e( 'Published', 'gmcq' ); ?></option>
					</select></td></tr>
					<tr><th><label><?php esc_html_e( 'Time Limit (minutes)', 'gmcq' ); ?></label></th>
					<td><input type="number" name="time_limit" min="0" value="<?php echo esc_attr( $meta ? (int) $meta->time_limit : 0 ); ?>"> <span class="description"><?php esc_html_e( '0 = unlimited', 'gmcq' ); ?></span></td></tr>
					<tr><th><label><?php esc_html_e( 'Pass Percentage', 'gmcq' ); ?></label></th>
					<td><input type="number" step="0.01" name="pass_percentage" value="<?php echo esc_attr( $meta ? $meta->pass_percentage : 40 ); ?>"></td></tr>
					<tr><th><label><?php esc_html_e( 'Max Attempts', 'gmcq' ); ?></label></th>
					<td><input type="number" name="max_attempts" min="0" value="<?php echo esc_attr( $meta ? (int) $meta->max_attempts : 0 ); ?>"> <span class="description"><?php esc_html_e( '0 = unlimited', 'gmcq' ); ?></span></td></tr>
					<tr><th><?php esc_html_e( 'Options', 'gmcq' ); ?></th>
					<td>
						<label><input type="checkbox" name="shuffle_questions" value="1" <?php checked( ! $meta || (int) $meta->shuffle_questions ); ?>> <?php esc_html_e( 'Shuffle questions', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="shuffle_answers" value="1" <?php checked( ! $meta || (int) $meta->shuffle_answers ); ?>> <?php esc_html_e( 'Shuffle answers', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="show_explanations" value="1" <?php checked( ! $meta || (int) $meta->show_explanations ); ?>> <?php esc_html_e( 'Show explanations', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="show_correct_answers" value="1" <?php checked( ! $meta || (int) $meta->show_correct_answers ); ?>> <?php esc_html_e( 'Show correct answers', 'gmcq' ); ?></label><br>
						<label><input type="checkbox" name="require_login" value="1" <?php checked( $meta && (int) $meta->require_login ); ?>> <?php esc_html_e( 'Require login', 'gmcq' ); ?></label>
					</td></tr>
					<tr><th><label><?php esc_html_e( 'Default Marks', 'gmcq' ); ?></label></th>
					<td><input type="number" step="0.01" name="default_marks" value="<?php echo esc_attr( $meta ? $meta->default_marks : 1 ); ?>"></td></tr>
					<tr><th><label><?php esc_html_e( 'Default Negative Marks', 'gmcq' ); ?></label></th>
					<td><input type="number" step="0.01" name="default_negative_marks" value="<?php echo esc_attr( $meta ? $meta->default_negative_marks : 0.25 ); ?>"></td></tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Quiz', 'gmcq' ); ?></button>
					<a href="<?php echo esc_url( $base ); ?>" class="button"><?php esc_html_e( 'Cancel', 'gmcq' ); ?></a>
					<?php if ( $quiz_id ) : ?>
						<a href="<?php echo esc_url( $base . '&action=questions&id=' . $quiz_id ); ?>" class="button"><?php esc_html_e( 'Manage Questions', 'gmcq' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
			<div id="gmcq-quiz-response"></div>
		</div>
	</div>
	<script>
	jQuery(function($){
		$('#gmcq-quiz-form').on('submit', function(e){
			e.preventDefault();
			var data = $(this).serializeArray();
			data.push({name: 'action', value: 'gmcq_save_quiz'});
			data.push({name: '_ajax_nonce', value: '<?php echo esc_js( $nonce ); ?>'});
			$.post(gmcqAdmin.ajaxUrl, $.param(data), function(r){
				if (r.success) {
					window.location.href = '<?php echo esc_js( $base ); ?>&action=edit&id=' + r.data.quiz_id;
				} else {
					$('#gmcq-quiz-response').text(r.data.message || 'Error');
				}
			});
		});
	});
	</script>
	<?php
}

function gmcq_render_quiz_questions_page( int $quiz_id ): void {
	$post = get_post( $quiz_id );
	if ( ! $post ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Quiz not found.', 'gmcq' ) . '</p></div>';
		return;
	}
	$assigned = gmcq_get_quiz_questions( $quiz_id );
	$assigned_ids = wp_list_pluck( $assigned, 'question_id' );
	$base = admin_url( 'admin.php?page=gmcq-quizzes' );
	$nonce = wp_create_nonce( 'gmcq_quiz_nonce' );
	$cats = gmcq_get_categories( array( 'filter' => 'active', 'per_page' => -1 ) );
	$cat_list = $cats['categories'];
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php echo esc_html( $post->post_title ); ?> — <?php esc_html_e( 'Manage Questions', 'gmcq' ); ?></h1>
		<div class="gmcq-card">
			<div class="gmcq-card" style="background:#f9f9f9;border:1px solid #ddd;padding:15px;margin-bottom:15px">
				<h3 style="margin-top:0;margin-bottom:12px"><?php esc_html_e( 'Search Questions to Add', 'gmcq' ); ?></h3>
				<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
					<input type="search" id="gmcq-q-search" placeholder="<?php esc_attr_e( 'Search by title or slug...', 'gmcq' ); ?>" style="min-width:240px;flex:1">
					<select id="gmcq-q-category" style="min-width:160px">
						<option value="0"><?php esc_html_e( 'All Categories', 'gmcq' ); ?></option>
						<?php foreach ( $cat_list as $c ) : ?>
							<option value="<?php echo (int) $c->id; ?>"><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<select id="gmcq-q-difficulty" style="min-width:120px">
						<option value=""><?php esc_html_e( 'All Difficulty', 'gmcq' ); ?></option>
						<option value="easy"><?php esc_html_e( 'Easy', 'gmcq' ); ?></option>
						<option value="medium"><?php esc_html_e( 'Medium', 'gmcq' ); ?></option>
						<option value="hard"><?php esc_html_e( 'Hard', 'gmcq' ); ?></option>
					</select>
					<select id="gmcq-q-type" style="min-width:140px">
						<option value=""><?php esc_html_e( 'All Types', 'gmcq' ); ?></option>
						<option value="mcq_single"><?php esc_html_e( 'MCQ Single', 'gmcq' ); ?></option>
						<option value="mcq_multiple"><?php esc_html_e( 'MCQ Multiple', 'gmcq' ); ?></option>
						<option value="true_false"><?php esc_html_e( 'True/False', 'gmcq' ); ?></option>
					</select>
					<button type="button" class="button" id="gmcq-q-search-btn"><?php esc_html_e( 'Search', 'gmcq' ); ?></button>
					<button type="button" class="button" id="gmcq-q-search-recent"><?php esc_html_e( 'Recent Questions', 'gmcq' ); ?></button>
					<a href="<?php echo esc_url( $base . '&action=questions&id=' . $quiz_id ); ?>" class="button"><?php esc_html_e( 'Reset Filters', 'gmcq' ); ?></a>
				</div>
				<div id="gmcq-search-results" style="margin-top:12px"></div>
			</div>
			<h3><?php esc_html_e( 'Assigned Questions', 'gmcq' ); ?> (<span id="gmcq-assigned-count"><?php echo count( $assigned ); ?></span>)</h3>
			<ul id="gmcq-assigned-list" style="list-style:none;margin:0 0 20px;padding:0;max-height:300px;overflow-y:auto;border:1px solid #ddd;background:#fff">
				<?php foreach ( $assigned as $q ) : ?>
					<li data-id="<?php echo (int) $q->question_id; ?>" style="padding:10px 12px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
						<span style="flex:1;margin-right:10px"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $q->question_text ), 15 ) ); ?></span>
						<button type="button" class="button-link gmcq-remove-q" data-id="<?php echo (int) $q->question_id; ?>" style="color:#dc3232"><?php esc_html_e( 'Remove', 'gmcq' ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p style="margin-top:15px">
				<button type="button" class="button button-primary button-large" id="gmcq-save-questions"><?php esc_html_e( 'Save Question Order', 'gmcq' ); ?></button>
				<a href="<?php echo esc_url( $base ); ?>" class="button button-large" style="margin-left:10px"><?php esc_html_e( 'Back to Quizzes', 'gmcq' ); ?></a>
			</p>
		</div>
	</div>
	<script>
	jQuery(function($){
		var nonce = '<?php echo esc_js( $nonce ); ?>';
		var quizId = <?php echo (int) $quiz_id; ?>;
		var ids = <?php echo wp_json_encode( array_map( 'intval', $assigned_ids ) ); ?>;

		function doSearch(params){
			$.get(gmcqAdmin.ajaxUrl, $.extend({
				action: 'gmcq_search_questions_for_quiz',
				q: $('#gmcq-q-search').val(),
				_ajax_nonce: nonce
			}, params || {}), function(r){
				var html = '';
				if (r.success && r.data.results) {
					r.data.results.forEach(function(item){
						if (ids.indexOf(parseInt(item.id)) >= 0) return;
						html += '<div style="padding:8px 10px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center">' +
							'<span style="flex:1;margin-right:10px;font-size:14px">' +
							$('<span>').text(item.question_text.replace(/<[^>]+>/g,'').substring(0,70)).html() +
							(item.category_name ? ' <small style="color:#999">['+$('<span>').text(item.category_name).html()+']</small>' : '') +
							'</span>' +
							'<button type="button" class="button button-small gmcq-add-q" data-id="'+item.id+'">Add</button></div>';
					});
				}
				$('#gmcq-search-results').html(html || '<p style="color:#999;padding:10px"><?php esc_html_e( 'No results found.', 'gmcq' ); ?></p>');
			});
		}

		$('#gmcq-q-search-btn').on('click', function(){
			doSearch({
				category_id: parseInt($('#gmcq-q-category').val()) || 0,
				difficulty: $('#gmcq-q-difficulty').val(),
				question_type: $('#gmcq-q-type').val()
			});
		});
		$('#gmcq-q-search-recent').on('click', function(){
			doSearch({recent: 1, q: '', category_id: 0, difficulty: '', question_type: ''});
		});
		$('#gmcq-q-search').on('keypress', function(e){
			if (e.which === 13) { e.preventDefault(); $('#gmcq-q-search-btn').click(); }
		});
		$('#gmcq-search-results').on('click', '.gmcq-add-q', function(){
			var id = parseInt($(this).data('id'));
			if (ids.indexOf(id) < 0) ids.push(id);
			$(this).closest('div').remove();
			$('#gmcq-assigned-list').append('<li data-id="'+id+'" style="padding:10px 12px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center"><span style="flex:1;margin-right:10px">Question #'+id+'</span><button type="button" class="button-link gmcq-remove-q" data-id="'+id+'" style="color:#dc3232"><?php esc_html_e( 'Remove', 'gmcq' ); ?></button></li>');
			$('#gmcq-assigned-count').text(ids.length);
		});
		$('#gmcq-assigned-list').on('click', '.gmcq-remove-q', function(){
			var id = parseInt($(this).data('id'));
			ids = ids.filter(function(x){ return x !== id; });
			$(this).closest('li').remove();
			$('#gmcq-assigned-count').text(ids.length);
		});
		function save(){
			$.post(gmcqAdmin.ajaxUrl, {action:'gmcq_set_quiz_questions', quiz_id: quizId, question_ids: ids, _ajax_nonce: nonce}, function(r){
				alert(r.success ? r.data.message : (r.data.message || 'Error'));
				if (r.success) location.reload();
			});
		}
		$('#gmcq-save-questions').on('click', save);
	});
	</script>
	<?php
}

gmcq_register_quiz_ajax_handlers();
