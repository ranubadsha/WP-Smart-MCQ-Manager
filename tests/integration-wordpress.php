<?php
/** Execute with WP-CLI `wp eval-file` inside a fresh WordPress + MySQL installation. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
function smcq_assert( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( 'Integration test failed: ' . $message ); }
}
smcq_assert( post_type_exists( 'smcq_question' ), 'question CPT registered' );
smcq_assert( post_type_exists( 'smcq_exam' ), 'exam CPT registered' );
smcq_assert( taxonomy_exists( 'smcq_subject' ), 'subjects registered' );
smcq_assert( get_role( 'smcq_question_author' ) !== null, 'author role installed' );
smcq_assert( get_role( 'smcq_question_reviewer' ) !== null, 'reviewer role installed' );
smcq_assert( get_role( 'administrator' )->has_cap( 'publish_smcq_questions' ), 'administrators can publish questions' );
$roles = get_role( 'smcq_question_author' );
smcq_assert( $roles->has_cap( 'edit_smcq_questions' ) && ! $roles->has_cap( 'publish_smcq_questions' ), 'authors cannot publish without review' );
global $wpdb;
foreach ( array_keys( SMCQ_Health::table_specs() ) as $name ) {
    $table = $wpdb->prefix . 'smcq_' . $name;
    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
    smcq_assert( $found === $table, 'plugin table exists: ' . $name );
}
smcq_assert( get_option( 'smcq_attempt_schema' ) === SMCQ_Player::SCHEMA_VERSION, 'attempt schema migrated' );
smcq_assert( get_option( 'smcq_practice_schema' ) === SMCQ_Practice::SCHEMA, 'practice schema migrated' );
smcq_assert( get_option( 'smcq_security_schema' ) === SMCQ_Security::SCHEMA, 'security schema migrated' );
$admin = get_user_by( 'login', 'admin' );
smcq_assert( $admin !== false, 'WP-CLI test admin exists' );
wp_set_current_user( $admin->ID );
$id = wp_insert_post( array( 'post_type' => 'smcq_question', 'post_status' => 'draft', 'post_title' => 'Test Question', 'post_author' => $admin->ID ), true );
smcq_assert( ! is_wp_error( $id ), 'insert question' );
update_post_meta( $id, '_smcq_options', array( 'A'=>'Correct','B'=>'Wrong','C'=>'Other','D'=>'Unknown' ) );
update_post_meta( $id, '_smcq_correct', 'A' );
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
smcq_assert( SMCQ_Exams::valid_question( $id ), 'question is eligible for an exam' );
wp_delete_post( $id, true );
smcq_assert( shortcode_exists( 'smcq_exam' ), 'exam shortcode registered' );
smcq_assert( shortcode_exists( 'smcq_practice' ), 'practice shortcode registered' );
smcq_assert( shortcode_exists( 'smcq_daily_quiz' ), 'daily quiz shortcode registered' );
smcq_assert( shortcode_exists( 'smcq_badges' ), 'badges shortcode registered' );
echo "WordPress + MySQL activation/permissions/shortcodes integration smoke checks passed.\n";