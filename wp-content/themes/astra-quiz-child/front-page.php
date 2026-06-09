<?php
/**
 * Modern quiz website homepage for the Astra Quiz Child theme.
 * Uses dynamic data from the GMCQ Quiz Engine plugin.
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

// ── Dynamic data from GMCQ ──────────────────────────────────────────────
$dashboard_stats  = function_exists( 'gmcq_get_dashboard_stats' ) ? gmcq_get_dashboard_stats() : array();
$top_quizzes      = function_exists( 'gmcq_get_top_quizzes' ) ? gmcq_get_top_quizzes( 6 ) : array();
$recent_quizzes   = function_exists( 'gmcq_get_recent_quizzes' ) ? gmcq_get_recent_quizzes( 6 ) : array();
$category_tree    = function_exists( 'gmcq_get_category_tree' ) ? gmcq_get_category_tree( array( 'filter' => 'active' ) ) : array();

// Fallback stats if plugin not active or DB empty
$stat_questions   = ! empty( $dashboard_stats['active_questions'] ) ? $dashboard_stats['active_questions'] : 500;
$stat_quizzes     = ! empty( $dashboard_stats['published_quizzes'] ) ? $dashboard_stats['published_quizzes'] : 25;
$stat_categories  = ! empty( $dashboard_stats['top_level_categories'] ) ? $dashboard_stats['top_level_categories'] : 6;
$stat_attempts    = ! empty( $dashboard_stats['total_attempts'] ) ? $dashboard_stats['total_attempts'] : 0;

// Number formatting for large numbers
$questions_display = $stat_questions > 999 ? round( $stat_questions / 1000, 1 ) . 'k+' : $stat_questions . '+';
$attempts_display  = $stat_attempts > 999 ? round( $stat_attempts / 1000, 1 ) . 'k+' : $stat_attempts . '+';
?>

<main id="primary" class="aqc-homepage">
	<div class="aqc-animated-bg" aria-hidden="true">
		<span class="aqc-orb aqc-orb-one"></span>
		<span class="aqc-orb aqc-orb-two"></span>
		<span class="aqc-orb aqc-orb-three"></span>
	</div>
	<div class="aqc-snowfall" aria-hidden="true"></div>

	<header class="aqc-header">
		<div class="aqc-container">
			<nav class="aqc-nav" aria-label="Homepage navigation">
				<a class="aqc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Home">
					<span class="aqc-brand-mark">✓</span>
					<span>Government MCQ</span>
				</a>

				<ul class="aqc-menu">
					<li><a class="is-active" href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></li>
					<li><a href="<?php echo esc_url( home_url( '/all-quizzes/' ) ); ?>">All Quizzes</a></li>
					<li><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Contact Us</a></li>
					<li><a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">About Us</a></li>
				</ul>
			</nav>
		</div>
	</header>

	<section class="aqc-hero" id="home">
		<div class="aqc-container aqc-hero-grid">
			<div class="aqc-hero-copy">
				<span class="aqc-badge"><span class="aqc-pulse"></span> Daily practice for government exam aspirants</span>
				<h1><?php echo esc_html( aqc_get_homepage_setting( 'aqc_hero_title', __( 'Prepare smarter for government exams.', 'astra-quiz-child' ) ) ); ?></h1>
				<p>
					<?php echo esc_html( aqc_get_homepage_setting( 'aqc_hero_description', __( 'Practice exam-oriented MCQs for SSC, Railway, Banking, Defence, Police, State PSC and General Knowledge. Build speed, accuracy and confidence with focused quiz preparation.', 'astra-quiz-child' ) ) ); ?>
				</p>

				<div class="aqc-actions">
					<a class="aqc-btn aqc-btn-primary" href="<?php echo esc_url( home_url( '/all-quizzes/' ) ); ?>">Explore Quizzes →</a>
					<a class="aqc-btn aqc-btn-ghost" href="#categories">View Exam Categories</a>
				</div>

				<div class="aqc-quick-stats" aria-label="Preparation highlights">
					<div class="aqc-stat"><strong><?php echo esc_html( $questions_display ); ?></strong><span>Practice MCQs</span></div>
					<div class="aqc-stat"><strong><?php echo (int) $stat_quizzes; ?>+</strong><span>Mock Sets</span></div>
					<div class="aqc-stat"><strong><?php echo (int) $stat_categories; ?></strong><span>Exam Categories</span></div>
				</div>

				<?php if ( $stat_attempts > 0 ) : ?>
				<div class="aqc-quick-stats" style="margin-top:12px" aria-label="Community activity">
					<div class="aqc-stat"><strong><?php echo esc_html( $attempts_display ); ?></strong><span>Tests Completed</span></div>
				</div>
				<?php endif; ?>
			</div>

			<aside class="aqc-hero-card" aria-label="Sample quiz preview">
				<div class="aqc-card-top">
					<h2>Live Mock Preview</h2>
					<span class="aqc-live">Timed Test</span>
				</div>
				<div class="aqc-question-preview">
					<p>Which article of the Indian Constitution deals with equality before law?</p>
					<div class="aqc-option">A. Article 14</div>
					<div class="aqc-option">B. Article 19</div>
					<div class="aqc-option is-correct">C. Article 14 ✓</div>
					<div class="aqc-option">D. Article 21</div>
				</div>
				<div class="aqc-score-box">
					<div><strong>82%</strong><span>Accuracy</span></div>
					<div><strong>18m</strong><span>Time Left</span></div>
					<div><strong>Rank ↑</strong><span>Improving</span></div>
				</div>
			</aside>
		</div>
	</section>

	<section class="aqc-section" id="categories">
		<div class="aqc-container">
			<div class="aqc-section-heading">
				<h2>Choose your exam category</h2>
				<p>Start with topic-wise quizzes and move toward full mock tests as your preparation improves.</p>
			</div>

			<div class="aqc-grid">
				<?php if ( ! empty( $category_tree ) ) : ?>
					<?php foreach ( $category_tree as $parent ) : ?>
						<article class="aqc-feature-card">
							<span class="aqc-icon">📘</span>
							<h3><?php echo esc_html( $parent->name ); ?></h3>
							<p>
								<?php
								echo ! empty( $parent->description )
									? esc_html( $parent->description )
									: esc_html( 'Practice ' . strtolower( $parent->name ) . ' questions for competitive exams.' );
								?>
								<?php if ( ! empty( $parent->children ) ) : ?>
								<br><br><strong style="color:var(--aqc-secondary);font-size:0.9rem">
									<?php
									$child_names = array();
									foreach ( $parent->children as $child ) {
										$child_names[] = $child->name;
									}
									echo esc_html( implode( ' · ', array_slice( $child_names, 0, 4 ) ) );
									if ( count( $child_names ) > 4 ) {
										echo ' +' . ( count( $child_names ) - 4 ) . ' more';
									}
									?>
								</strong>
								<?php endif; ?>
							</p>
						</article>
					<?php endforeach; ?>
				<?php else : ?>
					<article class="aqc-feature-card"><span class="aqc-icon">📘</span><h3>SSC & CGL Practice</h3><p>Quantitative aptitude, reasoning, English and general awareness quizzes for SSC exams.</p></article>
					<article class="aqc-feature-card"><span class="aqc-icon">🚆</span><h3>Railway Exams</h3><p>RRB NTPC, Group D and ALP-style practice sets with speed-focused question patterns.</p></article>
					<article class="aqc-feature-card"><span class="aqc-icon">🏦</span><h3>Banking Awareness</h3><p>Reasoning, banking awareness, current affairs and numerical ability for bank aspirants.</p></article>
					<article class="aqc-feature-card"><span class="aqc-icon">🛡️</span><h3>Defence & Police</h3><p>General studies, physical-test written preparation, constitution and basic science MCQs.</p></article>
					<article class="aqc-feature-card"><span class="aqc-icon">🏛️</span><h3>State PSC</h3><p>State-level GK, polity, history, geography and administrative awareness quizzes.</p></article>
					<article class="aqc-feature-card"><span class="aqc-icon">🌍</span><h3>General Knowledge</h3><p>Daily GK and current-affairs based practice to strengthen your overall exam readiness.</p></article>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="aqc-section" id="all-quizzes">
		<div class="aqc-container">
			<div class="aqc-section-heading">
				<h2>Popular quiz sets</h2>
				<p>High-value mock tests and practice sets designed around common government exam patterns.</p>
			</div>

			<div class="aqc-grid">
				<?php if ( ! empty( $top_quizzes ) ) : ?>
					<?php foreach ( $top_quizzes as $quiz ) : ?>
						<?php $quiz_tag = ! empty( $quiz->category_name ) ? $quiz->category_name : 'Mock Test'; ?>
						<article class="aqc-quiz-card" data-analytics='{"quiz_id":<?php echo (int) $quiz->quiz_id; ?>,"quiz_title":"<?php echo esc_js( $quiz->post_title ); ?>","category":"<?php echo esc_js( $quiz_tag ); ?>"}'>
							<span class="aqc-quiz-tag"><?php echo esc_html( $quiz_tag ); ?></span>
							<h3>
								<a href="<?php echo esc_url( get_permalink( $quiz->quiz_id ) ); ?>">
									<?php echo esc_html( $quiz->post_title ); ?>
								</a>
							</h3>
							<p>Practice with <?php echo (int) $quiz->question_count; ?> questions in a timed environment to test your knowledge.</p>
							<div class="aqc-quiz-meta">
								<span><?php echo (int) $quiz->question_count; ?> Questions</span>
								<?php if ( ! empty( $quiz->time_limit ) ) : ?>
									<span><?php echo (int) $quiz->time_limit; ?> Minutes</span>
								<?php endif; ?>
								<?php if ( ! empty( $quiz->attempt_count ) ) : ?>
									<span><?php echo (int) $quiz->attempt_count; ?> Attempts</span>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				<?php else : ?>
					<article class="aqc-quiz-card" data-analytics='{"quiz_id":0,"quiz_title":"SSC General Awareness - Set 01","category":"SSC Mock"}'><span class="aqc-quiz-tag">SSC Mock</span><h3>SSC General Awareness - Set 01</h3><p>Polity, history, geography and science questions for quick revision.</p><div class="aqc-quiz-meta"><span>50 Questions</span><span>45 Minutes</span><span>Beginner</span></div></article>
					<article class="aqc-quiz-card" data-analytics='{"quiz_id":0,"quiz_title":"Banking Current Affairs Sprint","category":"Banking"}'><span class="aqc-quiz-tag">Banking</span><h3>Banking Current Affairs Sprint</h3><p>Practice recent economy, RBI, schemes and banking-awareness updates.</p><div class="aqc-quiz-meta"><span>40 Questions</span><span>30 Minutes</span><span>Moderate</span></div></article>
					<article class="aqc-quiz-card" data-analytics='{"quiz_id":0,"quiz_title":"RRB Reasoning Speed Test","category":"Railway"}'><span class="aqc-quiz-tag">Railway</span><h3>RRB Reasoning Speed Test</h3><p>Improve accuracy in series, coding-decoding, analogy and puzzles.</p><div class="aqc-quiz-meta"><span>35 Questions</span><span>25 Minutes</span><span>Timed</span></div></article>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $recent_quizzes ) && count( $recent_quizzes ) > 3 ) : ?>
			<div style="margin-top:32px">
				<div class="aqc-section-heading">
					<h2>Recently added</h2>
					<p>New quiz sets to keep your preparation updated with fresh questions.</p>
				</div>
				<div class="aqc-grid">
					<?php foreach ( array_slice( $recent_quizzes, 0, 3 ) as $quiz ) : ?>
						<article class="aqc-quiz-card">
							<span class="aqc-quiz-tag">New</span>
							<h3>
								<a href="<?php echo esc_url( get_permalink( $quiz->quiz_id ) ); ?>">
									<?php echo esc_html( $quiz->post_title ); ?>
								</a>
							</h3>
							<p>Recently published quiz set with fresh practice questions — try it now.</p>
							<div class="aqc-quiz-meta">
								<span><?php echo (int) $quiz->question_count; ?> Questions</span>
								<span><?php echo esc_html( human_time_diff( strtotime( $quiz->created_at ), current_time( 'timestamp' ) ) ); ?> ago</span>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="aqc-section">
		<div class="aqc-container">
			<div class="aqc-section-heading">
				<h2>Why aspirants use it</h2>
				<p>A focused quiz experience for serious exam preparation, revision and self-assessment.</p>
			</div>

			<div class="aqc-grid">
				<article class="aqc-feature-card"><span class="aqc-icon">⏱️</span><h3>Timed practice</h3><p>Train under exam-like pressure and improve question selection speed.</p></article>
				<article class="aqc-feature-card"><span class="aqc-icon">🎯</span><h3>Topic-wise revision</h3><p>Focus on weak sections like polity, maths, reasoning or current affairs.</p></article>
				<article class="aqc-feature-card"><span class="aqc-icon">📈</span><h3>Score improvement</h3><p>Use repeated practice to improve accuracy before attempting full mock tests.</p></article>
			</div>
		</div>
	</section>

	<section class="aqc-section">
		<div class="aqc-container">
			<div class="aqc-section-heading">
				<h2>How it works</h2>
				<p>Simple preparation flow built for daily practice and consistent revision.</p>
			</div>

			<div class="aqc-steps">
				<article class="aqc-step"><span class="aqc-step-number">1</span><h3>Select a category</h3><p>Pick your target exam or subject and begin with a focused quiz set.</p></article>
				<article class="aqc-step"><span class="aqc-step-number">2</span><h3>Attempt MCQs</h3><p>Practice with exam-style questions, options and timed test conditions.</p></article>
				<article class="aqc-step"><span class="aqc-step-number">3</span><h3>Improve daily</h3><p>Review performance, revise weak topics and come back for the next set.</p></article>
			</div>
		</div>
	</section>

	<section class="aqc-cta">
		<div class="aqc-container">
			<div class="aqc-cta-box">
				<h2><?php echo esc_html( aqc_get_homepage_setting( 'aqc_cta_title', __( 'Ready to start your next mock test?', 'astra-quiz-child' ) ) ); ?></h2>
				<p><?php echo esc_html( aqc_get_homepage_setting( 'aqc_cta_description', __( 'Make quiz practice part of your daily routine and prepare confidently for upcoming government exams.', 'astra-quiz-child' ) ) ); ?></p>
				<a class="aqc-btn aqc-btn-primary" href="<?php echo esc_url( home_url( '/all-quizzes/' ) ) ?>">Start Practicing Today</a>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();