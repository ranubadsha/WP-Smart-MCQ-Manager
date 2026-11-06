<?php
/**
 * Plugin Name: Smart MCQ Manager
 * Description: Private MCQ bank, CSV/XLSX import, exam builder, and authenticated examinations, grading, analytics and smart practice.
 * Version: 0.8.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: smart-mcq-manager
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SMCQ_VERSION' ) ) {
	define( 'SMCQ_VERSION', '0.8.0' );
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
require_once SMCQ_DIR . 'includes/class-smcq-exams.php';
SMCQ_Exams::init();
require_once SMCQ_DIR . 'includes/class-smcq-results.php';
require_once SMCQ_DIR . 'includes/class-smcq-player.php';
SMCQ_Player::init();
require_once SMCQ_DIR . 'includes/class-smcq-analytics.php';
SMCQ_Analytics::init();
require_once SMCQ_DIR . 'includes/class-smcq-practice.php';
SMCQ_Practice::init();

register_activation_hook( __FILE__, static function () {
	SMCQ_Player::install();
	SMCQ_Practice::install();
	update_option( 'smcq_version', SMCQ_VERSION );
} );