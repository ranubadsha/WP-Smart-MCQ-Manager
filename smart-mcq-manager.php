<?php
/**
 * Plugin Name: Smart MCQ Manager
 * Description: Admin-only MCQ question bank with exam classification, rich explanations and advanced filters.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: smart-mcq-manager
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SMCQ_VERSION' ) ) {
	define( 'SMCQ_VERSION', '0.2.0' );
}
if ( ! defined( 'SMCQ_DIR' ) ) {
	define( 'SMCQ_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SMCQ_URL' ) ) {
	define( 'SMCQ_URL', plugin_dir_url( __FILE__ ) );
}

require_once SMCQ_DIR . 'includes/class-smcq-questions.php';
SMCQ_Questions::init();

register_activation_hook( __FILE__, static function () {
	update_option( 'smcq_version', SMCQ_VERSION );
} );