<?php
/**
 * Plugin Name: Smart MCQ Manager
 * Description: Private MCQ question bank with classifications, admin filters, and CSV/XLSX bulk imports.
 * Version: 0.3.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: smart-mcq-manager
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SMCQ_VERSION' ) ) {
	define( 'SMCQ_VERSION', '0.3.0' );
}
if ( ! defined( 'SMCQ_DIR' ) ) {
	define( 'SMCQ_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SMCQ_URL' ) ) {
	define( 'SMCQ_URL', plugin_dir_url( __FILE__ ) );
}

require_once SMCQ_DIR . 'includes/class-smcq-questions.php';
SMCQ_Questions::init();
require_once SMCQ_DIR . 'includes/class-smcq-importer.php';
SMCQ_Importer::init();

register_activation_hook( __FILE__, static function () {
	update_option( 'smcq_version', SMCQ_VERSION );
} );