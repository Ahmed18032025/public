<?php
/**
 * Plugin Name: GMCQ Quiz Engine
 * Description: MCQ Quiz Management System for WordPress.
 * Version:     1.0.0
 * Text Domain: gmcq
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
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

// Safely include required core files if they exist
$gmcq_core_files = array(
	'includes/class-gmcq-helpers.php',
	'includes/class-gmcq-db.php',
	'includes/class-gmcq-categories.php',
	'includes/class-gmcq-admin.php',
);

foreach ( $gmcq_core_files as $gmcq_file ) {
	if ( file_exists( GMCQ_PLUGIN_DIR . $gmcq_file ) ) {
		require_once GMCQ_PLUGIN_DIR . $gmcq_file;
	}
}

// Register activation hook
if ( ! function_exists( 'gmcq_activate_plugin' ) ) {
	function gmcq_activate_plugin(): void {
		// Ensure DB table creation on activation
		if ( function_exists( 'gmcq_create_tables' ) ) {
			gmcq_create_tables();
		}
		
		// Create assets directory for CSS
		if ( function_exists( 'gmcq_create_assets_dir' ) ) {
			gmcq_create_assets_dir();
		}
	}
	register_activation_hook( __FILE__, 'gmcq_activate_plugin' );
}

if ( ! function_exists( 'gmcq_deactivate_plugin' ) ) {
	function gmcq_deactivate_plugin(): void {
		// Future deactivation logic goes here
	}
	register_deactivation_hook( __FILE__, 'gmcq_deactivate_plugin' );
}
register_deactivation_hook( __FILE__, 'gmcq_deactivate_plugin' );