<?php
/**
 * Template: Single Quiz (GMCQ)
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

$quiz_id   = get_the_ID();
$quiz_meta = function_exists( 'gmcq_get_quiz_meta' ) ? gmcq_get_quiz_meta( $quiz_id ) : null;
$questions = array();
if ( $quiz_meta && ! empty( $quiz_meta->questions ) ) {
	$questions = $quiz_meta->questions;
}
$total         = count( $questions );
$time_limit    = $quiz_meta->time_limit ?? 0;
$category_name = ! empty( $quiz_meta->category_name ) ? esc_html( $quiz_meta->category_name ) : __( 'Mock Test', 'astra-quiz-child' );

$jsonld = array(
	'@context'           => 'https://schema.org',
	'@type'              => 'Quiz',
	'name'               => get_the_title(),
	'url'                => get_permalink(),
	'numberOfQuestions'  => $total,
	'typicalAgeRange'    => '18-35',
	'description'        => get_the_excerpt(),
);
if ( ! empty( $category_name ) && 'Mock Test' !== $category_name ) {
	$jsonld['about'] = array(
		'@type' => 'Thing',
		'name'  => $category_name,
	);
}
if ( $time_limit ) {
	$jsonld['timeRequired'] = 'PT' . (int) $time_limit . 'M';
}

get_header();
?>
<script type="application/ld+json"><?php echo wp_json_encode( $jsonld ); ?></script>

<main id="primary" class="aqc-page-wrapper">
	<?php
	get_template_part( 'aqc-page-header', null, array(
		'title'       => get_the_title(),
		'description' => $category_name . ( $time_limit ? ' — ' . (int) $time_limit . ' min timed test' : '' ),
	) );
	?>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<div class="aqc-quiz-layout">
				<aside class="aqc-quiz-meta" aria-label="Quiz details">
					<div class="aqc-feature-card" style="margin-bottom:16px">
						<span class="aqc-icon">📋</span>
						<h3><?php echo (int) $total; ?> Questions</h3>
						<p>Curated MCQs aligned with current exam patterns.</p>
					</div>
					<?php if ( $time_limit ) : ?>
					<div class="aqc-feature-card" style="margin-bottom:16px">
						<span class="aqc-icon">⏱️</span>
						<h3><?php echo (int) $time_limit; ?> Minutes</h3>
						<p>Timed environment to build speed and accuracy.</p>
					</div>
					<?php endif; ?>
					<div class="aqc-quiz-navigator">
						<p class="aqc-quiz-navigator__title">Question navigator</p>
						<ul class="aqc-quiz-navigator__list" id="aqc-question-nav">
							<?php if ( ! empty( $questions ) ) : ?>
								<?php foreach ( $questions as $index => $q ) : ?>
									<li><a href="#aqc-question-<?php echo (int) ( $index + 1 ); ?>"><?php echo (int) ( $index + 1 ); ?></a></li>
								<?php endforeach; ?>
							<?php else : ?>
								<li><span>—</span></li>
							<?php endif; ?>
						</ul>
					</div>
				</aside>

				<div class="aqc-quiz-questions" id="aqc-quiz-questions" role="list" aria-label="Quiz questions">
					<?php if ( ! empty( $questions ) ) : ?>
						<div class="aqc-quiz-progress">
							<div class="aqc-quiz-progress__bar">
								<div class="aqc-quiz-progress__fill" id="aqc-progress-fill" style="width:0%"></div>
							</div>
							<div class="aqc-quiz-progress__meta">
								<span id="aqc-progress-current">Question 1</span>
								<span><?php echo (int) $total; ?> total</span>
							</div>
						</div>

						<div class="aqc-quiz-card" style="grid-column:1/-1; margin-bottom:16px; border-left:4px solid var(--aqc-accent)">
							<span class="aqc-quiz-tag"><?php echo $category_name; ?></span>
							<h2><?php the_title(); ?></h2>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						</div>

						<?php foreach ( $questions as $index => $q ) : ?>
							<article class="aqc-quiz-card" style="margin-bottom:16px" id="aqc-question-<?php echo (int) ( $index + 1 ); ?>" role="listitem" data-question-index="<?php echo (int) $index; ?>">
								<p style="font-weight:700; margin-bottom:10px">
									<?php echo (int) ( $index + 1 ); ?>. <?php echo esc_html( $q->question_text ?? '' ); ?>
								</p>
								<?php if ( ! empty( $q->options ) && is_array( $q->options ) ) : ?>
									<div class="aqc-quiz-meta">
										<?php foreach ( $q->options as $opt ) : ?>
											<span><?php echo esc_html( $opt ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="aqc-no-results">Questions for this quiz are not available right now. Please check back later.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
