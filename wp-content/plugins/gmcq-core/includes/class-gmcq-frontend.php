<?php
/**
 * GMCQ Frontend — shortcode + public quiz taking UI.
 *
 * Created @SparkzDev
 */
defined( 'ABSPATH' ) || exit;

function gmcq_register_shortcodes(): void {
	add_shortcode( 'gmcq_quiz', 'gmcq_shortcode_quiz' );
	add_shortcode( 'gmcq_quiz_archive', 'gmcq_shortcode_quiz_archive' );
}
add_action( 'init', 'gmcq_register_shortcodes' );

function gmcq_enqueue_frontend_assets(): void {
	global $post;
	$has_quiz_shortcode = $post instanceof WP_Post && has_shortcode( $post->post_content, 'gmcq_quiz' );
	$has_archive_shortcode = $post instanceof WP_Post && has_shortcode( $post->post_content, 'gmcq_quiz_archive' );
	$has_shortcode = $has_quiz_shortcode || $has_archive_shortcode;
	if ( ! $has_shortcode && ! is_singular( 'gmcq_quiz' ) ) {
		return;
	}
	wp_enqueue_style( 'gmcq-frontend', GMCQ_PLUGIN_URL . 'assets/css/frontend.css', array(), GMCQ_VERSION );
	wp_enqueue_script( 'gmcq-frontend', GMCQ_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), GMCQ_VERSION, true );
	wp_localize_script(
		'gmcq-frontend',
		'gmcqPublic',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gmcq_public_nonce' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gmcq_enqueue_frontend_assets' );

function gmcq_shortcode_quiz( $atts ): string {
	$atts = shortcode_atts(
		array(
			'id' => 0,
		),
		$atts,
		'gmcq_quiz'
	);

	$quiz_id = (int) $atts['id'];
	if ( $quiz_id <= 0 ) {
		return '<p>' . esc_html__( 'Quiz ID is required.', 'gmcq' ) . '</p>';
	}

	$post = get_post( $quiz_id );
	$meta = gmcq_get_quiz_meta( $quiz_id );
	if ( ! $post || ! $meta || 1 !== (int) $meta->is_active || 'published' !== $meta->status ) {
		return '<p>' . esc_html__( 'Quiz not available.', 'gmcq' ) . '</p>';
	}

	if ( (int) $meta->require_login && ! is_user_logged_in() ) {
		return '<p>' . esc_html__( 'Please log in to take this quiz.', 'gmcq' ) . '</p>';
	}

	$questions = gmcq_get_quiz_questions( $quiz_id );
	if ( empty( $questions ) ) {
		return '<p>' . esc_html__( 'This quiz has no questions yet.', 'gmcq' ) . '</p>';
	}

	if ( (int) $meta->shuffle_questions ) {
		shuffle( $questions );
	}

	ob_start();
	?>
	<div class="gmcq-quiz-wrap" data-quiz-id="<?php echo (int) $quiz_id; ?>" data-time-limit="<?php echo (int) $meta->time_limit; ?>">
		<h2 class="gmcq-quiz-title"><?php echo esc_html( $post->post_title ); ?></h2>
		<div class="gmcq-quiz-meta">
			<?php if ( (int) $meta->time_limit > 0 ) : ?>
				<span class="gmcq-timer" data-minutes="<?php echo (int) $meta->time_limit; ?>"><?php printf( esc_html__( 'Time: %d min', 'gmcq' ), (int) $meta->time_limit ); ?></span>
			<?php endif; ?>
			<span><?php printf( esc_html__( '%d questions', 'gmcq' ), count( $questions ) ); ?></span>
		</div>
		<div id="gmcq-quiz-start">
			<button type="button" class="gmcq-btn gmcq-btn-primary" id="gmcq-start-btn"><?php esc_html_e( 'Start Quiz', 'gmcq' ); ?></button>
		</div>
		<div id="gmcq-quiz-questions" style="display:none">
			<?php foreach ( $questions as $idx => $qm ) :
				$q = gmcq_get_question( (int) $qm->question_id );
				if ( ! $q ) {
					continue;
				}
				$answers = $q->answers;
				if ( (int) $meta->shuffle_answers ) {
					shuffle( $answers );
				}
				$input_type = 'mcq_multiple' === $q->question_type ? 'checkbox' : 'radio';
				?>
				<div class="gmcq-question" data-question-id="<?php echo (int) $q->id; ?>" data-qtype="<?php echo esc_attr( $q->question_type ); ?>" style="<?php echo 0 === $idx ? '' : 'display:none'; ?>">
					<div class="gmcq-q-number"><?php printf( esc_html__( 'Question %d of %d', 'gmcq' ), $idx + 1, count( $questions ) ); ?></div>
					<div class="gmcq-q-text"><?php echo wp_kses_post( $q->question_text ); ?></div>
					<div class="gmcq-answers">
						<?php foreach ( $answers as $ans ) : ?>
							<label class="gmcq-answer-option">
								<input type="<?php echo esc_attr( $input_type ); ?>" name="gmcq_q_<?php echo (int) $q->id; ?>[]" value="<?php echo (int) $ans->id; ?>">
								<?php echo esc_html( $ans->answer_text ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="gmcq-q-nav">
						<button type="button" class="gmcq-btn gmcq-save-answer"><?php esc_html_e( 'Save & Next', 'gmcq' ); ?></button>
						<button type="button" class="gmcq-btn gmcq-skip"><?php esc_html_e( 'Skip', 'gmcq' ); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
			<button type="button" class="gmcq-btn gmcq-btn-primary" id="gmcq-submit-quiz" style="display:none"><?php esc_html_e( 'Submit Quiz', 'gmcq' ); ?></button>
		</div>
		<div id="gmcq-quiz-results" style="display:none"></div>
	</div>
	<?php
	return ob_get_clean();
}

function gmcq_shortcode_quiz_archive( $atts ): string {
	$atts = shortcode_atts(
		array(
			'title' => __( 'All Quizzes', 'gmcq' ),
			'per_page' => 12,
		),
		$atts,
		'gmcq_quiz_archive'
	);

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$per_page = (int) $atts['per_page'];

	global $wpdb;
	$p = $wpdb->prefix;
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta zm
		 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
		 WHERE zm.status = 'published' AND zm.is_active = 1 AND p.post_status = 'publish'"
	);

	$offset = ( $paged - 1 ) * $per_page;
	$quizzes = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT zm.quiz_id, p.post_title, p.post_name, zm.question_count, zm.time_limit
			 FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 WHERE zm.status = 'published' AND zm.is_active = 1 AND p.post_status = 'publish'
			 ORDER BY p.post_title ASC
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);

	ob_start();
	?>
	<div class="gmcq-quiz-archive">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<h2 class="gmcq-archive-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>
		<div class="gmcq-quiz-grid">
			<?php if ( empty( $quizzes ) ) : ?>
				<p><?php esc_html_e( 'No quizzes available at the moment.', 'gmcq' ); ?></p>
			<?php else : foreach ( $quizzes as $quiz ) : ?>
				<div class="gmcq-quiz-card">
					<h3 class="gmcq-quiz-card-title">
						<a href="<?php echo esc_url( get_permalink( $quiz->quiz_id ) ); ?>">
							<?php echo esc_html( $quiz->post_title ); ?>
						</a>
					</h3>
					<div class="gmcq-quiz-card-meta">
						<span class="gmcq-quiz-questions"><?php printf( esc_html__( '%d questions', 'gmcq' ), (int) $quiz->question_count ); ?></span>
						<?php if ( (int) $quiz->time_limit > 0 ) : ?>
							<span class="gmcq-quiz-time"><?php printf( esc_html__( '%d min', 'gmcq' ), (int) $quiz->time_limit ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; endif; ?>
		</div>
		<div class="gmcq-archive-pagination">
			<?php
			echo paginate_links(
				array(
					'total'     => max( 1, ceil( $total / $per_page ) ),
					'current'   => $paged,
					'prev_text' => __( '&laquo; Previous', 'gmcq' ),
					'next_text' => __( 'Next &raquo;', 'gmcq' ),
				)
			);
			?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function gmcq_register_rest_routes(): void {
	register_rest_route(
		'gmcq/v1',
		'/quizzes/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'gmcq_rest_get_quiz',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'gmcq_register_rest_routes' );

function gmcq_rest_get_quiz( WP_REST_Request $request ) {
	$quiz_id = (int) $request['id'];
	$post    = get_post( $quiz_id );
	$meta    = gmcq_get_quiz_meta( $quiz_id );

	if ( ! $post || ! $meta || 'published' !== $meta->status ) {
		return new WP_Error( 'not_found', __( 'Quiz not found.', 'gmcq' ), array( 'status' => 404 ) );
	}

	return rest_ensure_response(
		array(
			'id'             => $quiz_id,
			'title'          => $post->post_title,
			'question_count' => (int) $meta->question_count,
			'time_limit'     => (int) $meta->time_limit,
			'pass_percentage'=> (float) $meta->pass_percentage,
		)
	);
}

function gmcq_create_frontend_assets(): void {
	$css_dir = GMCQ_PLUGIN_DIR . 'assets/css';
	$js_dir  = GMCQ_PLUGIN_DIR . 'assets/js';
	if ( ! file_exists( $css_dir ) ) {
		wp_mkdir_p( $css_dir );
	}
	if ( ! file_exists( $js_dir ) ) {
		wp_mkdir_p( $js_dir );
	}

	$css_file = $css_dir . '/frontend.css';
	if ( ! file_exists( $css_file ) ) {
		file_put_contents(
			$css_file,
			".gmcq-quiz-wrap{max-width:800px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:6px}\n.gmcq-btn{padding:8px 16px;margin:4px;cursor:pointer}\n.gmcq-btn-primary{background:#2271b1;color:#fff;border:none;border-radius:4px}\n.gmcq-answer-option{display:block;padding:8px;margin:4px 0;border:1px solid #eee;border-radius:4px}\n.gmcq-q-text{margin:12px 0;font-size:16px}\n.gmcq-quiz-archive{margin:20px auto;max-width:1200px}\n.gmcq-archive-title{margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid #ddd}\n.gmcq-quiz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}\n.gmcq-quiz-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);transition:transform 0.2s,box-shadow 0.2s}\n.gmcq-quiz-card:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,0.1)}\n.gmcq-quiz-card-title{margin:0 0 10px 0;font-size:18px}\n.gmcq-quiz-card-title a{color:#2271b1;text-decoration:none}\n.gmcq-quiz-card-title a:hover{text-decoration:underline}\n.gmcq-quiz-card-meta{color:#666;font-size:14px;display:flex;gap:15px;margin-bottom:15px}\n.gmcq-archive-pagination{margin-top:30px;text-align:center}\n.gmcq-archive-pagination .page-numbers{display:inline-block;padding:8px 12px;margin:0 2px;background:#f0f0f0;color:#333;text-decoration:none;border-radius:4px}\n.gmcq-archive-pagination .page-numbers:hover{background:#ddd}\n.gmcq-archive-pagination .page-numbers.current{background:#2271b1;color:#fff}\n"
		);
	}

	$js_file = $js_dir . '/frontend.js';
	if ( ! file_exists( $js_file ) ) {
		file_put_contents(
			$js_file,
			<<<'JS'
jQuery(function($){
  var attemptId=0,startTime=Date.now();
  function showQ(i){$('.gmcq-question').hide().eq(i).show();if(i>=$('.gmcq-question').length-1)$('#gmcq-submit-quiz').show();}
  $('#gmcq-start-btn').on('click',function(){
    var quizId=$('.gmcq-quiz-wrap').data('quiz-id');
    $.post(gmcqPublic.ajaxUrl,{action:'gmcq_start_attempt',quiz_id:quizId,_ajax_nonce:gmcqPublic.nonce},function(r){
      if(r.success){attemptId=r.data.attempt_id;startTime=Date.now();$('#gmcq-quiz-start').hide();$('#gmcq-quiz-questions').show();}
      else alert(r.data.message||'Error');
    });
  });
  var cur=0;
  function saveAnswer(skip){
    var $q=$('.gmcq-question').eq(cur),qid=$q.data('question-id');
    var ids=skip?[]:$q.find('input:checked').map(function(){return parseInt(this.value);}).get();
    $.post(gmcqPublic.ajaxUrl,{action:'gmcq_submit_answer',attempt_id:attemptId,question_id:qid,answer_ids:ids,time_spent:0,_ajax_nonce:gmcqPublic.nonce},function(){
      cur++;if(cur<$('.gmcq-question').length)showQ(cur);else $('#gmcq-submit-quiz').show();
    });
  }
  $('.gmcq-save-answer').on('click',function(){saveAnswer(false);});
  $('.gmcq-skip').on('click',function(){saveAnswer(true);});
  $('#gmcq-submit-quiz').on('click',function(){
    var timeTaken=Math.floor((Date.now()-startTime)/1000);
    $.post(gmcqPublic.ajaxUrl,{action:'gmcq_complete_attempt',attempt_id:attemptId,time_taken:timeTaken,_ajax_nonce:gmcqPublic.nonce},function(r){
      if(r.success){var a=r.data.attempt;$('#gmcq-quiz-questions').hide();$('#gmcq-quiz-results').html('<h3>Score: '+a.percentage+'%</h3><p>'+(a.passed?'Pass':'Fail')+'</p>').show();}
    });
  });
});
JS
		);
	}
}
add_action( 'admin_init', 'gmcq_create_frontend_assets' );

function gmcq_render_single_quiz_template( $content ): string {
	if ( ! is_singular( 'gmcq_quiz' ) ) {
		return $content;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	$quiz_id = get_the_ID();
	$meta = gmcq_get_quiz_meta( $quiz_id );
	if ( ! $meta || 1 !== (int) $meta->is_active || 'published' !== $meta->status ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return $content;
	}
	if ( (int) $meta->require_login && ! is_user_logged_in() ) {
		return '<p>' . esc_html__( 'Please log in to take this quiz.', 'gmcq' ) . '</p>';
	}
	return gmcq_shortcode_quiz( array( 'id' => $quiz_id ) );
}
add_filter( 'the_content', 'gmcq_render_single_quiz_template' );
