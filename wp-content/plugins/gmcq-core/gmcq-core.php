<?php
/**
 * Plugin Name: GMCQ Quiz Engine
 * Description: MCQ Quiz Management System for WordPress.
 * Version:     1.0.0
 * Author:      Mr A
 * Text Domain: gmcq
 *
 * Created @SparkzDev
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GMCQ_VERSION' ) ) {
	define( 'GMCQ_VERSION', '1.0.0' );
}
if ( ! defined( 'GMCQ_DB_VERSION' ) ) {
	define( 'GMCQ_DB_VERSION', '1.0.0' );
}
if ( ! defined( 'GMCQ_PLUGIN_DIR' ) ) {
	define( 'GMCQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'GMCQ_PLUGIN_URL' ) ) {
	define( 'GMCQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// License endpoint - update after Netlify deployment
if ( ! defined( 'GMCQ_LICENSE_ENDPOINT' ) ) {
	define( 'GMCQ_LICENSE_ENDPOINT', 'https://gmcq-license.netlify.app/.netlify/functions/validate-license' );
}

$gmcq_core_files = array(
	'includes/class-gmcq-license.php',
	'includes/class-gmcq-helpers.php',
	'includes/class-gmcq-db.php',
	'includes/class-gmcq-cron.php',
	'includes/class-gmcq-dashboard.php',
	'includes/class-gmcq-categories.php',
	'includes/class-gmcq-questions.php',
	'includes/class-gmcq-quizzes.php',
	'includes/class-gmcq-attempts.php',
	'includes/class-gmcq-import.php',
	'includes/class-gmcq-reports.php',
	'includes/class-gmcq-settings.php',
	'includes/class-gmcq-frontend.php',
	'includes/class-gmcq-admin.php',
);

foreach ( $gmcq_core_files as $gmcq_file ) {
	$path = GMCQ_PLUGIN_DIR . $gmcq_file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

function gmcq_add_capabilities(): void {
	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( 'manage_gmcq' ) ) {
		$role->add_cap( 'manage_gmcq' );
	}
}

function gmcq_activate_plugin(): void {
	if ( function_exists( 'gmcq_create_tables' ) ) {
		gmcq_create_tables();
	}
	if ( function_exists( 'gmcq_schedule_cron_jobs' ) ) {
		gmcq_schedule_cron_jobs();
	}
	gmcq_add_capabilities();
	if ( function_exists( 'gmcq_create_assets_dir' ) ) {
		gmcq_create_assets_dir();
	}
	if ( function_exists( 'gmcq_create_frontend_assets' ) ) {
		gmcq_create_frontend_assets();
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'gmcq_activate_plugin' );

function gmcq_deactivate_plugin(): void {
	if ( function_exists( 'gmcq_clear_cron_jobs' ) ) {
		gmcq_clear_cron_jobs();
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gmcq_deactivate_plugin' );

function gmcq_uninstall_plugin(): void {
	if ( function_exists( 'gmcq_get_setting' ) && 'delete' === gmcq_get_setting( 'uninstall_behavior', 'keep' ) ) {
		if ( function_exists( 'gmcq_drop_tables' ) ) {
			gmcq_drop_tables();
		}
	}
}
register_uninstall_hook( __FILE__, 'gmcq_uninstall_plugin' );

add_action( 'init', 'gmcq_add_capabilities', 1 );

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'gmcq', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

add_action(
	'init',
	static function (): void {
		if ( is_admin() && get_option( 'gmcq_db_version' ) !== GMCQ_DB_VERSION && function_exists( 'gmcq_create_tables' ) ) {
			gmcq_create_tables();
		}
	},
	5
);

// Early license check - redirect to activation page if not activated
add_action( 'admin_init', 'gmcq_check_license_activation' );
function gmcq_check_license_activation(): void {
	if ( ! is_admin() ) {
		return;
	}
	
	// Allow access to license activation page
	$screen = get_current_screen();
	if ( $screen && 'gmcq-license' === $screen->id ) {
		return;
	}
	
	// Skip for AJAX requests that are license-related
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	
	// Check if license is activated (skip first 5 seconds for activation flow)
	if ( ! gmcq_license_is_activated() ) {
		// Don't redirect during activation to avoid loop
		if ( ! doing_action( 'activate_plugin' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=gmcq-license' ) );
			exit;
		}
	}
}
