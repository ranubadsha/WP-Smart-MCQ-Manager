<?php
/** Private editorial workflow, question metadata history, and duplicate review. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Editorial {
    const VERSION_OPTION = 'smcq_editorial_roles_v1';
    const MAX_VERSIONS = 25;
    const LIMIT = 1000;
    public static function init() {
        add_action( 'init', array( __CLASS__, 'install_roles' ), 5 );
        add_action( 'add_meta_boxes_smcq_question', array( __CLASS__, 'history_box' ) );
        add_action( 'save_post_smcq_question', array( __CLASS__, 'snapshot_version' ), 30, 2 );
        add_action( 'admin_post_smcq_restore_question', array( __CLASS__, 'restore' ) );
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_filter( 'manage_edit-smcq_question_columns', array( __CLASS__, 'add_status_column' ) );
        add_action( 'manage_smcq_question_posts_custom_column', array( __CLASS__, 'status_column' ), 20, 2 );
    }
    public static function capabilities() {
        return array( 'edit_smcq_questions', 'edit_others_smcq_questions', 'edit_published_smcq_questions', 'edit_private_smcq_questions', 'publish_smcq_questions', 'read_private_smcq_questions', 'delete_smcq_questions', 'delete_others_smcq_questions', 'delete_published_smcq_questions', 'delete_private_smcq_questions', 'create_smcq_questions' );
    }
    public static function install_roles() {
        if ( get_option( self::VERSION_OPTION ) === '1' ) { return; }
        $manager = get_role( 'administrator' );
        if ( $manager ) { foreach ( self::capabilities() as $cap ) { $manager->add_cap( $cap ); } }
        $editor = get_role( 'editor' );
        if ( $editor ) { foreach ( self::capabilities() as $cap ) { $editor->add_cap( $cap ); } }
        $author = array( 'read' => true, 'edit_smcq_questions' => true, 'delete_smcq_questions' => true, 'create_smcq_questions' => true, 'upload_files' => true );
        $reviewer = array_merge( $author, array_fill_keys( self::capabilities(), true ) );
        add_role( 'smcq_question_author', __( 'MCQ Question Author', 'smart-mcq-manager' ), $author );
        add_role( 'smcq_question_reviewer', __( 'MCQ Question Reviewer', 'smart-mcq-manager' ), $reviewer );
        update_option( self::VERSION_OPTION, '1', false );
    }
    public static function keys() {
        return array( '_smcq_options', '_smcq_correct', '_smcq_correct_keys', '_smcq_explanation', '_smcq_type', '_smcq_passage', '_smcq_media_id', '_smcq_difficulty', '_smcq_language', '_smcq_exam_year', '_smcq_reference', '_smcq_reference_url', '_smcq_marks', '_smcq_negative_marks', '_smcq_practice_enabled' );
    }
    public static function snapshot_version( $id, $post ) {
        if ( ! $post || $post->post_type !== 'smcq_question' || wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) { return; }
        $nonce = isset( $_POST['smcq_question_nonce'] ) && is_string( $_POST['smcq_question_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['smcq_question_nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'smcq_save_question' ) ) { return; }
        $version = array( 'title' => (string) get_the_title( $id ), 'meta' => array(), 'time' => time(), 'editor' => get_current_user_id() );
        foreach ( self::keys() as $key ) { $version['meta'][$key] = get_post_meta( $id, $key, true ); }
        $versions = get_post_meta( $id, '_smcq_edit_versions', true );
        $versions = is_array( $versions ) ? $versions : array();
        $last = end( $versions );
        if ( $last && $last['title'] === $version['title'] && $last['meta'] === $version['meta'] ) { return; }
        $versions[] = $version;
        update_post_meta( $id, '_smcq_edit_versions', array_slice( $versions, -self::MAX_VERSIONS ) );
    }
    public static function history_box() {
        add_meta_box( 'smcq_versions', __( 'MCQ Question Version History', 'smart-mcq-manager' ), array( __CLASS__, 'render_history' ), 'smcq_question', 'normal', 'default' );
    }
    public static function render_history( $post ) {
        $versions = get_post_meta( $post->ID, '_smcq_edit_versions', true );
        if ( ! is_array( $versions ) || ! $versions ) { echo '<p>' . esc_html__( 'No saved history yet. Save the question to create a version.', 'smart-mcq-manager' ) . '</p>'; return; }
        echo '<p>' . esc_html__( 'Up to 25 recent answer-and-metadata snapshots are retained. Exam attempts preserve their own historic copies.', 'smart-mcq-manager' ) . '</p><ol reversed>';
        foreach ( array_reverse( $versions, true ) as $index => $version ) {
            $date = wp_date( 'Y-m-d H:i', (int) $version['time'] );
            echo '<li>' . esc_html( $date . ' — ' . $version['title'] );
            if ( current_user_can( 'publish_smcq_questions' ) ) {
                $url = wp_nonce_url( admin_url( 'admin-post.php?action=smcq_restore_question&id=' . $post->ID . '&version=' . (int) $index ), 'smcq_restore_' . $post->ID . '_' . $index );
                echo ' <a href="' . esc_url( $url ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Restore this version? This will replace the current question details.', 'smart-mcq-manager' ) ) ) . ')">' . esc_html__( 'Restore', 'smart-mcq-manager' ) . '</a>';
            }
            echo '</li>';
        }
        echo '</ol>';
    }
    public static function restore() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $index = isset( $_GET['version'] ) ? absint( $_GET['version'] ) : -1;
        if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'publish_smcq_questions' ) || get_post_type( $id ) !== 'smcq_question' ) { wp_die( 'Not authorized', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'smcq_restore_' . $id . '_' . $index );
        $versions = get_post_meta( $id, '_smcq_edit_versions', true );
        if ( ! is_array( $versions ) || ! isset( $versions[$index]['meta'] ) || ! is_array( $versions[$index]['meta'] ) ) { wp_die( 'Version not found', '', array( 'response' => 404 ) ); }
        $selected = $versions[$index];
        // Keep a restoration checkpoint so the operation is reversible.
        $current = array( 'title' => (string) get_the_title( $id ), 'meta' => array(), 'time' => time(), 'editor' => get_current_user_id() );
        foreach ( self::keys() as $key ) { $current['meta'][$key] = get_post_meta( $id, $key, true ); }
        $versions[] = $current;
        update_post_meta( $id, '_smcq_edit_versions', array_slice( $versions, -self::MAX_VERSIONS ) );
        foreach ( self::keys() as $key ) { if ( array_key_exists( $key, $selected['meta'] ) ) { update_post_meta( $id, $key, $selected['meta'][$key] ); } }
        wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $selected['title'] ) ) );
        wp_safe_redirect( get_edit_post_link( $id, 'raw' ) ); exit;
    }
    public static function normalize( $title ) {
        $title = wp_strip_all_tags( (string) $title );
        $title = preg_replace( '/[\s\p{P}\p{S}]+/u', ' ', $title );
        $title = trim( $title );
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
    }
    public static function duplicates( $rows ) {
        $groups = array();
        foreach ( $rows as $row ) {
            $title = is_object( $row ) ? $row->post_title : $row['title'];
            $id = is_object( $row ) ? $row->ID : $row['id'];
            $key = self::normalize( $title );
            if ( '' !== $key ) { $groups[$key][] = (int) $id; }
        }
        return array_filter( $groups, static function ( $ids ) { return count( $ids ) > 1; } );
    }
    public static function admin_menu() {
        add_submenu_page( 'edit.php?post_type=smcq_question', __( 'Duplicate Question Review', 'smart-mcq-manager' ), __( 'Duplicate Review', 'smart-mcq-manager' ), 'publish_smcq_questions', 'smcq-duplicates', array( __CLASS__, 'duplicate_page' ) );
    }
    public static function duplicate_page() {
        if ( ! current_user_can( 'publish_smcq_questions' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        $posts = get_posts( array( 'post_type' => 'smcq_question', 'post_status' => array( 'publish','draft','pending','private' ), 'numberposts' => self::LIMIT, 'orderby' => 'ID', 'order' => 'DESC' ) );
        $groups = self::duplicates( $posts );
        echo '<div class="wrap"><h1>' . esc_html__( 'Duplicate Question Review', 'smart-mcq-manager' ) . '</h1>';
        echo '<p>' . esc_html__( 'Review potentially identical questions. Nothing is deleted automatically. The latest 1,000 questions are checked; use imports to avoid exact duplicates.', 'smart-mcq-manager' ) . '</p>';
        if ( ! $groups ) { echo '<p>' . esc_html__( 'No duplicates in the sampled questions.', 'smart-mcq-manager' ) . '</p>'; }
        foreach ( $groups as $title => $ids ) {
            echo '<h3>' . esc_html( $title ) . '</h3><p>';
            foreach ( $ids as $id ) { echo '<a class="button" href="' . esc_url( get_edit_post_link( $id ) ) . '">Q-' . esc_html( $id ) . '</a> '; }
            echo '</p>';
        }
        echo '</div>';
    }
    public static function add_status_column( $columns ) { $columns['smcq_review_state'] = __( 'Review Status', 'smart-mcq-manager' ); return $columns; }
    public static function status_column( $column, $id ) { if ( 'smcq_review_state' === $column ) { echo esc_html( get_post_status( $id ) ); } }
}