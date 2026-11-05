<?php
/**
 * Plugin Name: Smart MCQ Manager
 * Description: Manage MCQ questions, explanations, subjects, and topics. Step 1 foundation.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: smart-mcq-manager
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'SMCQ_VERSION', '0.1.0' );
define( 'SMCQ_DIR', plugin_dir_path( __FILE__ ) );
require_once SMCQ_DIR . 'includes/class-smcq-questions.php';
add_action( 'init', array( 'SMCQ_Questions', 'register' ) );
add_action( 'add_meta_boxes', array( 'SMCQ_Questions', 'meta_boxes' ) );
add_action( 'save_post_smcq_question', array( 'SMCQ_Questions', 'save' ), 10, 2 );
add_filter( 'manage_smcq_question_posts_columns', array( 'SMCQ_Questions', 'columns' ) );
add_action( 'manage_smcq_question_posts_custom_column', array( 'SMCQ_Questions', 'column_value' ), 10, 2 );
add_action( 'admin_notices', array( 'SMCQ_Questions', 'notice' ) );
register_activation_hook( __FILE__, function () { update_option( 'smcq_version', SMCQ_VERSION ); } );
