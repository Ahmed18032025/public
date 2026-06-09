<?php
/**
 * Astra Quiz Child theme functions.
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

// ── Theme Supports ─────────────────────────────────────────────────────
add_action( 'after_setup_theme', function (): void {
	add_theme_support( 'custom-logo', array(
		'width'       => 200,
		'height'      => 60,
		'flex-width'  => true,
		'flex-height' => true,
	) );
	add_theme_support( 'astra-global-color' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
} );

// ── Enqueue Styles & Scripts ────────────────────────────────────────────
function aqc_enqueue_styles(): void {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( 'astra' )->get( 'Version' )
	);

	wp_enqueue_style(
		'astra-quiz-child-style',
		get_stylesheet_uri(),
		array( 'astra-parent-style' ),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'aqc-global',
		get_stylesheet_directory_uri() . '/aqc-global.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	$heading_font = get_theme_mod( 'aqc_heading_font', 'inter' );
	$font_weights = '400;500;600;700;900';
	if ( 'saira' === $heading_font ) {
		wp_enqueue_style( 'aqc-font-saira', 'https://fonts.googleapis.com/css2?family=Saira:wght@' . $font_weights . '&display=swap', array(), null );
	} elseif ( 'poppins' === $heading_font ) {
		wp_enqueue_style( 'aqc-font-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:wght@' . $font_weights . '&display=swap', array(), null );
	}

	$aqc_data = array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'aqc_public_nonce' ),
		'homeUrl'      => home_url( '/' ),
		'allQuizzes'   => home_url( '/all-quizzes/' ),
		'blogUrl'      => home_url( '/blog/' ),
		'contactUrl'   => home_url( '/contact-us/' ),
		'aboutUrl'     => home_url( '/about-us/' ),
		'enableSnow'   => get_theme_mod( 'aqc_enable_snowfall', 1 ) ? 1 : 0,
		'snowDensity'  => (int) get_theme_mod( 'aqc_snowfall_density', 30 ),
		'enableReveal' => get_theme_mod( 'aqc_enable_reveal_animations', 1 ) ? 1 : 0,
		'defaultTheme' => get_theme_mod( 'aqc_default_theme_mode', 'light' ),
	);

	wp_localize_script( 'aqc-global', 'aqcData', $aqc_data );

	$dynamic_heading_font = 'Inter, ui-sans-serif, system-ui, sans-serif';
	if ( 'saira' === $heading_font ) {
		$dynamic_heading_font = 'Saira, ui-sans-serif, system-ui, sans-serif';
	} elseif ( 'poppins' === $heading_font ) {
		$dynamic_heading_font = 'Poppins, ui-sans-serif, system-ui, sans-serif';
	}
	wp_add_inline_style( 'astra-quiz-child-style', 'h1,h2,h3,.aqc-section-heading h2{font-family:' . $dynamic_heading_font . ';}' );
}
add_action( 'wp_enqueue_scripts', 'aqc_enqueue_styles', 20 );

// ── Body Class ─────────────────────────────────────────────────────────
function aqc_body_class( array $classes ): array {
	$theme_mode = get_theme_mod( 'aqc_default_theme_mode', 'light' );
	if ( 'dark' === $theme_mode ) {
		$classes[] = 'aqc-dark-mode';
	} elseif ( 'system' === $theme_mode ) {
		$classes[] = 'aqc-system-mode';
	}
	return $classes;
}
add_filter( 'body_class', 'aqc_body_class' );

// ── Customizer ─────────────────────────────────────────────────────────
function aqc_customize_register( WP_Customize_Manager $wp_customize ): void {

	// ── Section: Logo / Brand ──
	$wp_customize->add_section( 'aqc_brand', array(
		'title'    => __( 'AQ Brand & Logo', 'astra-quiz-child' ),
		'priority' => 25,
	) );

	$wp_customize->add_setting( 'aqc_brand_text', array(
		'default'           => 'Government MCQ',
		'type'              => 'theme_mod',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'aqc_brand_text', array(
		'label'   => __( 'Brand Text', 'astra-quiz-child' ),
		'section' => 'aqc_brand',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'aqc_logo_width', array(
		'default'           => 42,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_logo_width', array(
		'label'       => __( 'Logo Width (px)', 'astra-quiz-child' ),
		'section'     => 'aqc_brand',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 30, 'max' => 80, 'step' => 1 ),
	) );

	// ── Section: Theme Mode ──
	$wp_customize->add_section( 'aqc_theme', array(
		'title'    => __( 'AQ Theme Mode', 'astra-quiz-child' ),
		'priority' => 30,
	) );

	$wp_customize->add_setting( 'aqc_default_theme_mode', array(
		'default'           => 'light',
		'type'              => 'theme_mod',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'aqc_default_theme_mode', array(
		'label'   => __( 'Default Theme Mode', 'astra-quiz-child' ),
		'section' => 'aqc_theme',
		'type'    => 'select',
		'choices' => array(
			'light'  => __( 'Light', 'astra-quiz-child' ),
			'dark'   => __( 'Dark', 'astra-quiz-child' ),
			'system' => __( 'System Preference', 'astra-quiz-child' ),
		),
	) );

	$wp_customize->add_setting( 'aqc_show_theme_toggle', array(
		'default'           => 1,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_show_theme_toggle', array(
		'label'   => __( 'Show Theme Toggle Button', 'astra-quiz-child' ),
		'section' => 'aqc_theme',
		'type'    => 'checkbox',
	) );

	// ── Section: Header ──
	$wp_customize->add_section( 'aqc_header', array(
		'title'    => __( 'AQ Header', 'astra-quiz-child' ),
		'priority' => 35,
	) );

	$wp_customize->add_setting( 'aqc_header_bg_opacity', array(
		'default'           => 0.72,
		'type'              => 'theme_mod',
		'sanitize_callback' => function( $v ) { return max( 0, min( 1, (float) $v ) ); },
	) );
	$wp_customize->add_control( 'aqc_header_bg_opacity', array(
		'label'       => __( 'Header Background Opacity', 'astra-quiz-child' ),
		'section'     => 'aqc_header',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ),
	) );

	$wp_customize->add_setting( 'aqc_header_height', array(
		'default'           => 18,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_header_height', array(
		'label'       => __( 'Header Vertical Padding (px)', 'astra-quiz-child' ),
		'section'     => 'aqc_header',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 10, 'max' => 30, 'step' => 1 ),
	) );

	// ── Section: Footer ──
	$wp_customize->add_section( 'aqc_footer', array(
		'title'    => __( 'AQ Footer', 'astra-quiz-child' ),
		'priority' => 40,
	) );

	$wp_customize->add_setting( 'aqc_footer_tagline', array(
		'default'           => __( 'Made for aspirants', 'astra-quiz-child' ),
		'type'              => 'theme_mod',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'aqc_footer_tagline', array(
		'label'   => __( 'Footer Tagline', 'astra-quiz-child' ),
		'section' => 'aqc_footer',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'aqc_footer_copyright', array(
		'default'           => '',
		'type'              => 'theme_mod',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'aqc_footer_copyright', array(
		'label'   => __( 'Copyright Text (leave blank for auto)', 'astra-quiz-child' ),
		'section' => 'aqc_footer',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'aqc_footer_show_categories', array(
		'default'           => 1,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_footer_show_categories', array(
		'label'   => __( 'Show Exam Categories Column', 'astra-quiz-child' ),
		'section' => 'aqc_footer',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'aqc_footer_show_links', array(
		'default'           => 1,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_footer_show_links', array(
		'label'   => __( 'Show Quick Links Column', 'astra-quiz-child' ),
		'section' => 'aqc_footer',
		'type'    => 'checkbox',
	) );

	// ── Section: Homepage Sections ──
	$wp_customize->add_section( 'aqc_homepage', array(
		'title'    => __( 'AQ Homepage Sections', 'astra-quiz-child' ),
		'priority' => 45,
	) );

	$sections = array(
		'stats'         => __( 'Stats', 'astra-quiz-child' ),
		'quiz_preview'  => __( 'Quiz Preview', 'astra-quiz-child' ),
		'categories'    => __( 'Categories', 'astra-quiz-child' ),
		'popular'       => __( 'Popular Quizzes', 'astra-quiz-child' ),
		'recent'        => __( 'Recently Added', 'astra-quiz-child' ),
		'features'      => __( 'Features', 'astra-quiz-child' ),
		'steps'         => __( 'How It Works', 'astra-quiz-child' ),
		'cta'           => __( 'CTA Banner', 'astra-quiz-child' ),
	);
	foreach ( $sections as $key => $label ) {
		$wp_customize->add_setting( 'aqc_show_section_' . $key, array(
			'default'           => 1,
			'type'              => 'theme_mod',
			'sanitize_callback' => 'absint',
		) );
		$wp_customize->add_control( 'aqc_show_section_' . $key, array(
			'label'   => $label,
			'section' => 'aqc_homepage',
			'type'    => 'checkbox',
		) );
	}

	// ── Section: Animations ──
	$wp_customize->add_section( 'aqc_animations', array(
		'title'    => __( 'AQ Animations', 'astra-quiz-child' ),
		'priority' => 50,
	) );

	$wp_customize->add_setting( 'aqc_enable_snowfall', array(
		'default'           => 1,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_enable_snowfall', array(
		'label'   => __( 'Enable Snowfall', 'astra-quiz-child' ),
		'section' => 'aqc_animations',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'aqc_snowfall_density', array(
		'default'           => 30,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_snowfall_density', array(
		'label'       => __( 'Snowfall Density', 'astra-quiz-child' ),
		'section'     => 'aqc_animations',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 30, 'max' => 150, 'step' => 1 ),
	) );

	$wp_customize->add_setting( 'aqc_enable_reveal_animations', array(
		'default'           => 1,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_enable_reveal_animations', array(
		'label'   => __( 'Enable Reveal Animations', 'astra-quiz-child' ),
		'section' => 'aqc_animations',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'aqc_reveal_intensity', array(
		'default'           => 'light',
		'type'              => 'theme_mod',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'aqc_reveal_intensity', array(
		'label'   => __( 'Reveal Intensity', 'astra-quiz-child' ),
		'section' => 'aqc_animations',
		'type'    => 'select',
		'choices' => array(
			'light' => __( 'Light', 'astra-quiz-child' ),
			'full'  => __( 'Full', 'astra-quiz-child' ),
		),
	) );

	// ── Section: Layout ──
	$wp_customize->add_section( 'aqc_layout', array(
		'title'    => __( 'AQ Layout', 'astra-quiz-child' ),
		'priority' => 55,
	) );

	$wp_customize->add_setting( 'aqc_container_width', array(
		'default'           => 1180,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_container_width', array(
		'label'       => __( 'Container Width (px)', 'astra-quiz-child' ),
		'section'     => 'aqc_layout',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 900, 'max' => 1400, 'step' => 10 ),
	) );

	$wp_customize->add_setting( 'aqc_heading_font', array(
		'default'           => 'inter',
		'type'              => 'theme_mod',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'aqc_heading_font', array(
		'label'   => __( 'Heading Font', 'astra-quiz-child' ),
		'section' => 'aqc_layout',
		'type'    => 'select',
		'choices' => array(
			'inter'   => 'Inter',
			'saira'   => 'Saira',
			'poppins' => 'Poppins',
		),
	) );

	$wp_customize->add_setting( 'aqc_body_font_size', array(
		'default'           => 16,
		'type'              => 'theme_mod',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'aqc_body_font_size', array(
		'label'       => __( 'Body Font Size (px)', 'astra-quiz-child' ),
		'section'     => 'aqc_layout',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 14, 'max' => 18, 'step' => 1 ),
	) );
}
add_action( 'customize_register', 'aqc_customize_register' );

// ── AJAX: Quiz Filters ──────────────────────────────────────────────────
function aqc_ajax_filter_quizzes(): void {
	check_ajax_referer( 'aqc_public_nonce', 'nonce' );

	$search      = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
	$category_id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
	$sort_by     = isset( $_GET['sort_by'] ) ? sanitize_text_field( $_GET['sort_by'] ) : 'popular';

	$cache_key = 'aqc_quiz_filter_' . md5( $search . $category_id . $sort_by );
	$html      = wp_cache_get( $cache_key );

	if ( false === $html ) {
		$quizzes = array();

		if ( function_exists( 'gmcq_get_top_quizzes' ) ) {
			$quizzes = gmcq_get_top_quizzes( 12 );
		}

		if ( 'newest' === $sort_by && function_exists( 'gmcq_get_recent_quizzes' ) ) {
			$quizzes = gmcq_get_recent_quizzes( 12 );
		}

		$html = '';
		if ( ! empty( $quizzes ) ) {
			foreach ( $quizzes as $quiz ) {
				$tag = ! empty( $quiz->category_name ) ? esc_html( $quiz->category_name ) : __( 'Mock Test', 'astra-quiz-child' );
				$title = esc_html( $quiz->post_title );
				$meta = array();
				$meta[] = (int) $quiz->question_count . ' Questions';
				if ( ! empty( $quiz->time_limit ) ) {
					$meta[] = (int) $quiz->time_limit . ' Minutes';
				}
				if ( ! empty( $quiz->attempt_count ) ) {
					$meta[] = (int) $quiz->attempt_count . ' Attempts';
				}
				$json_analytics = wp_json_encode( array(
					'quiz_id'    => (int) $quiz->quiz_id,
					'quiz_title' => $title,
					'category'   => $tag,
				) );
				$html .= '<article class="aqc-quiz-card" data-analytics=\'' . esc_attr( $json_analytics ) . '\'>';
				$html .= '<span class="aqc-quiz-tag">' . $tag . '</span>';
				$html .= '<h3><a href="' . esc_url( get_permalink( $quiz->quiz_id ) ) . '">' . $title . '</a></h3>';
				$html .= '<p>Practice with ' . (int) $quiz->question_count . ' questions in a timed environment.</p>';
				$html .= '<div class="aqc-quiz-meta">';
				foreach ( $meta as $m ) {
					$html .= '<span>' . $m . '</span>';
				}
				$html .= '</div></article>';
			}
		} else {
			$html = '<p class="aqc-no-results">' . __( 'No quizzes found matching your criteria.', 'astra-quiz-child' ) . '</p>';
		}

		wp_cache_set( $cache_key, $html, '', 300 );
	}

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_gmcq_filter_quizzes', 'aqc_ajax_filter_quizzes' );
add_action( 'wp_ajax_nopriv_gmcq_filter_quizzes', 'aqc_ajax_filter_quizzes' );

// ── AJAX: Contact Form ──────────────────────────────────────────────────
function aqc_ajax_contact_form(): void {
	check_ajax_referer( 'aqc_public_nonce', 'nonce' );

	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	$rate_limit_key = 'aqc_contact_rate_' . md5( $ip );
	$submissions    = (int) get_transient( $rate_limit_key );

	if ( $submissions >= 3 ) {
		wp_send_json_error( array( 'message' => __( 'Too many submissions. Please wait 10 minutes.', 'astra-quiz-child' ) ) );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

	if ( empty( $name ) || empty( $email ) || empty( $subject ) || empty( $message ) ) {
		wp_send_json_error( array( 'message' => __( 'All fields are required.', 'astra-quiz-child' ) ) );
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'astra-quiz-child' ) ) );
	}

	$body = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}";

	$headers = array( 'Content-Type: text/plain; charset=UTF-8', "From: {$name} <{$email}>" );
	$sent    = wp_mail( get_option( 'admin_email' ), '[AQ Contact] ' . $subject, $body, $headers );

	if ( $sent ) {
		set_transient( $rate_limit_key, $submissions + 1, 600 );
		wp_send_json_success( array( 'message' => __( 'Message sent successfully! We will get back to you soon.', 'astra-quiz-child' ) ) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to send message. Please try again later.', 'astra-quiz-child' ) ) );
	}
}
add_action( 'wp_ajax_gmcq_contact_form', 'aqc_ajax_contact_form' );
add_action( 'wp_ajax_nopriv_gmcq_contact_form', 'aqc_ajax_contact_form' );

// ── SEO: Open Graph ──────────────────────────────────────────────────────
function aqc_add_og_tags(): void {
	if ( is_singular( 'gmcq_quiz' ) ) {
		$post = get_post();
		$thumb = get_the_post_thumbnail_url( $post, 'large' );
		?>
		<meta property="og:title" content="<?php echo esc_attr( get_the_title( $post ) ); ?>">
		<meta property="og:description" content="<?php echo esc_attr( get_the_excerpt( $post ) ); ?>">
		<meta property="og:url" content="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<meta property="og:type" content="article">
		<meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<?php if ( $thumb ) : ?>
		<meta property="og:image" content="<?php echo esc_url( $thumb ); ?>">
		<?php endif; ?>
		<meta name="twitter:card" content="summary_large_image">
		<?php
	}
}
add_action( 'wp_head', 'aqc_add_og_tags', 5 );

// ── SEO: Breadcrumb Schema ─────────────────────────────────────────────
function aqc_breadcrumb_schema(): void {
	if ( ! is_singular() && ! is_home() ) {
		return;
	}
	$items = array();
	$items[] = array(
		'@type'           => 'ListItem',
		'position'        => 1,
		'name'            => get_bloginfo( 'name' ),
		'item'            => home_url( '/' ),
	);
	if ( is_singular( 'gmcq_quiz' ) ) {
		$items[] = array(
			'@type'           => 'ListItem',
			'position'        => 2,
			'name'            => get_the_title(),
			'item'            => get_permalink(),
		);
	} elseif ( is_home() ) {
		$items[] = array(
			'@type'           => 'ListItem',
			'position'        => 2,
			'name'            => get_the_title( get_option( 'page_for_posts' ) ),
			'item'            => home_url( '/blog/' ),
		);
	}
	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
}
add_action( 'wp_head', 'aqc_breadcrumb_schema', 5 );

// ── Helper: Get Customizer Value ────────────────────────────────────────
function aqc_get_setting( string $key, $default = '' ) {
	return get_theme_mod( $key, $default );
}

function aqc_get_homepage_setting( string $key, $default = '' ) {
	return get_theme_mod( $key, $default );
}
