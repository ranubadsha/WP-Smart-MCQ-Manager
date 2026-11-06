<?php
/**
 * Plugin Name: Smart MCQ Manager
 * Description: Private MCQ bank, CSV/XLSX import, exam builder, and authenticated examinations, grading, analytics, smart practice and private reports/exports.
 * Version: 1.4.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Smart MCQ Manager Contributors
 * Author URI: https://github.com/ranubadsha/WP-Smart-MCQ-Manager
 * Plugin URI: https://github.com/ranubadsha/WP-Smart-MCQ-Manager
 * Domain Path: /languages
 * Text Domain: smart-mcq-manager
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SMCQ_VERSION' ) ) {
	define( 'SMCQ_VERSION', '1.4.1' );
}
if ( ! defined( 'SMCQ_DIR' ) ) {
	define( 'SMCQ_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SMCQ_URL' ) ) {
	define( 'SMCQ_URL', plugin_dir_url( __FILE__ ) );
}

require_once SMCQ_DIR . 'includes/class-smcq-certificates.php';
SMCQ_Certificates::init();
require_once SMCQ_DIR . 'includes/class-smcq-blocks.php';
SMCQ_Blocks::init();
require_once SMCQ_DIR . 'includes/class-smcq-health.php';
SMCQ_Health::init();
require_once SMCQ_DIR . 'includes/class-smcq-question-types.php';
require_once SMCQ_DIR . 'includes/class-smcq-editorial.php';
SMCQ_Editorial::init();
require_once SMCQ_DIR . 'includes/class-smcq-ai.php';
SMCQ_AI::init();
require_once SMCQ_DIR . 'includes/class-smcq-live-monitor.php';
SMCQ_Live_Monitor::init();
require_once SMCQ_DIR . 'includes/class-smcq-gamification.php';
SMCQ_Gamification::init();
require_once SMCQ_DIR . 'includes/class-smcq-questions.php';
SMCQ_Questions::init();
require_once SMCQ_DIR . 'includes/class-smcq-question-reports.php';
SMCQ_Question_Reports::init();
require_once SMCQ_DIR . 'includes/class-smcq-importer.php';
SMCQ_Importer::init();
require_once SMCQ_DIR . 'includes/class-smcq-exams.php';
SMCQ_Exams::init();
require_once SMCQ_DIR . 'includes/class-smcq-results.php';
require_once SMCQ_DIR . 'includes/class-smcq-security.php';
SMCQ_Security::init();
require_once SMCQ_DIR . 'includes/class-smcq-commerce.php';
SMCQ_Commerce::init();
require_once SMCQ_DIR . 'includes/class-smcq-player.php';
SMCQ_Player::init();
require_once SMCQ_DIR . 'includes/class-smcq-analytics.php';
SMCQ_Analytics::init();
require_once SMCQ_DIR . 'includes/class-smcq-practice.php';
SMCQ_Practice::init();
require_once SMCQ_DIR . 'includes/class-smcq-reports.php';
SMCQ_Reports::init();

register_activation_hook( __FILE__, static function () {
	SMCQ_Editorial::install_roles();
	SMCQ_Player::install();
	SMCQ_Security::install();
	SMCQ_Practice::install();
	update_option( 'smcq_version', SMCQ_VERSION );
} );