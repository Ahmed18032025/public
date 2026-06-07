<?php
/**
 * Modern quiz website homepage for the Astra Quiz Child theme.
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="aqc-homepage">
	<div class="aqc-animated-bg" aria-hidden="true">
		<span class="aqc-orb aqc-orb-one"></span>
		<span class="aqc-orb aqc-orb-two"></span>
		<span class="aqc-orb aqc-orb-three"></span>
	</div>

	<header class="aqc-header">
		<div class="aqc-container">
			<nav class="aqc-nav" aria-label="Homepage navigation">
				<a class="aqc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Home">
					<span class="aqc-brand-mark">✓</span>
					<span>ExamQuiz Pro</span>
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
				<h1>Prepare smarter for <span class="aqc-gradient-text">government exams</span>.</h1>
				<p>
					Practice exam-oriented MCQs for SSC, Railway, Banking, Defence, Police, State PSC and General Knowledge.
					Build speed, accuracy and confidence with focused quiz preparation.
				</p>

				<div class="aqc-actions">
					<a class="aqc-btn aqc-btn-primary" href="<?php echo esc_url( home_url( '/all-quizzes/' ) ); ?>">Explore Quizzes →</a>
					<a class="aqc-btn aqc-btn-ghost" href="#categories">View Exam Categories</a>
				</div>

				<div class="aqc-quick-stats" aria-label="Preparation highlights">
					<div class="aqc-stat"><strong>500+</strong><span>Practice MCQs</span></div>
					<div class="aqc-stat"><strong>25+</strong><span>Mock Sets</span></div>
					<div class="aqc-stat"><strong>6</strong><span>Exam Categories</span></div>
				</div>
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
				<article class="aqc-feature-card"><span class="aqc-icon">📘</span><h3>SSC & CGL Practice</h3><p>Quantitative aptitude, reasoning, English and general awareness quizzes for SSC exams.</p></article>
				<article class="aqc-feature-card"><span class="aqc-icon">🚆</span><h3>Railway Exams</h3><p>RRB NTPC, Group D and ALP-style practice sets with speed-focused question patterns.</p></article>
				<article class="aqc-feature-card"><span class="aqc-icon">🏦</span><h3>Banking Awareness</h3><p>Reasoning, banking awareness, current affairs and numerical ability for bank aspirants.</p></article>
				<article class="aqc-feature-card"><span class="aqc-icon">🛡️</span><h3>Defence & Police</h3><p>General studies, physical-test written preparation, constitution and basic science MCQs.</p></article>
				<article class="aqc-feature-card"><span class="aqc-icon">🏛️</span><h3>State PSC</h3><p>State-level GK, polity, history, geography and administrative awareness quizzes.</p></article>
				<article class="aqc-feature-card"><span class="aqc-icon">🌍</span><h3>General Knowledge</h3><p>Daily GK and current-affairs based practice to strengthen your overall exam readiness.</p></article>
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
				<article class="aqc-quiz-card"><span class="aqc-quiz-tag">SSC Mock</span><h3>SSC General Awareness - Set 01</h3><p>Polity, history, geography and science questions for quick revision.</p><div class="aqc-quiz-meta"><span>50 Questions</span><span>45 Minutes</span><span>Beginner</span></div></article>
				<article class="aqc-quiz-card"><span class="aqc-quiz-tag">Banking</span><h3>Banking Current Affairs Sprint</h3><p>Practice recent economy, RBI, schemes and banking-awareness updates.</p><div class="aqc-quiz-meta"><span>40 Questions</span><span>30 Minutes</span><span>Moderate</span></div></article>
				<article class="aqc-quiz-card"><span class="aqc-quiz-tag">Railway</span><h3>RRB Reasoning Speed Test</h3><p>Improve accuracy in series, coding-decoding, analogy and puzzles.</p><div class="aqc-quiz-meta"><span>35 Questions</span><span>25 Minutes</span><span>Timed</span></div></article>
			</div>
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
				<h2>Ready to start your next mock test?</h2>
				<p>Make quiz practice part of your daily routine and prepare confidently for upcoming government exams.</p>
				<a class="aqc-btn aqc-btn-primary" href="<?php echo esc_url( home_url( '/all-quizzes/' ) ); ?>">Start Practicing Today</a>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();