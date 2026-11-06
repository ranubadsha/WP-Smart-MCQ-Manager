<?php
/** Admin-only live attempt counts and public-safe upcoming exam schedule. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Live_Monitor {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_shortcode( 'smcq_upcoming_exams', array( __CLASS__, 'upcoming' ) );
    }
    public static function menu() {
        add_submenu_page( 'edit.php?post_type=smcq_question', __( 'Live Examination Monitor', 'smart-mcq-manager' ), __( 'Live Monitor', 'smart-mcq-manager' ), 'manage_options', 'smcq-live-monitor', array( __CLASS__, 'admin_page' ) );
    }
    public static function stats( $rows ) {
        $stats = array( 'active' => 0, 'submitted' => 0 );
        foreach ( (array) $rows as $row ) {
            if ( is_array( $row ) && isset( $row['status'], $row['total'] ) && isset( $stats[$row['status']] ) ) { $stats[$row['status']] += (int) $row['total']; }
        }
        return $stats;
    }
    public static function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        global $wpdb;
        $table = $wpdb->prefix . 'smcq_attempts';
        $exam = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
        $where = $exam ? $wpdb->prepare( ' AND a.exam_id=%d', $exam ) : '';
        // Query is bounded, uses indexed exam/status columns, and returns aggregated counts only.
        $data = $wpdb->get_results( "SELECT a.exam_id, a.status, COUNT(*) AS total, MAX(a.started_at) AS last_started FROM {$table} a INNER JOIN {$wpdb->posts} p ON p.ID=a.exam_id WHERE p.post_type='smcq_exam' AND a.status IN ('active','submitted') {$where} GROUP BY a.exam_id,a.status ORDER BY a.exam_id DESC LIMIT 200", ARRAY_A );
        echo '<div class="wrap"><h1>' . esc_html__( 'Live Examination Monitor', 'smart-mcq-manager' ) . '</h1>';
        echo '<p>' . esc_html__( 'Refresh to update counts. Active attempts may include expired attempts pending lazy finalization; these are not certified final results.', 'smart-mcq-manager' ) . '</p>';
        echo '<form method="get"><input type="hidden" name="post_type" value="smcq_question"><input type="hidden" name="page" value="smcq-live-monitor"><label>' . esc_html__( 'Exam ID', 'smart-mcq-manager' ) . ' <input type="number" min="0" name="exam_id" value="' . esc_attr( $exam ) . '"></label> <button class="button">' . esc_html__( 'Filter / Refresh', 'smart-mcq-manager' ) . '</button></form>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Exam', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Attempt state', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Count', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Most recently started (UTC)', 'smart-mcq-manager' ) . '</th></tr></thead><tbody>';
        foreach ( (array) $data as $row ) {
            echo '<tr><td><a href="' . esc_url( get_edit_post_link( (int) $row['exam_id'] ) ) . '">' . esc_html( get_the_title( (int) $row['exam_id'] ) ) . ' (#' . esc_html( $row['exam_id'] ) . ')</a></td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $row['total'] ) . '</td><td>' . esc_html( $row['last_started'] ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    public static function upcoming() {
        $posts = get_posts( array( 'post_type' => 'smcq_exam', 'post_status' => 'publish', 'numberposts' => 20, 'orderby' => 'date', 'order' => 'DESC' ) );
        $items = array(); $now = time();
        foreach ( $posts as $post ) {
            $start = SMCQ_Player::schedule_timestamp( (string) get_post_meta( $post->ID, '_smcq_exam_start', true ) );
            $end = SMCQ_Player::schedule_timestamp( (string) get_post_meta( $post->ID, '_smcq_exam_end', true ) );
            if ( $end && $end <= $now ) { continue; }
            if ( $start && $start > $now ) { $label = __( 'Opens', 'smart-mcq-manager' ) . ': ' . wp_date( 'Y-m-d H:i', $start ); }
            else { $label = __( 'Open now', 'smart-mcq-manager' ); }
            $items[] = '<li><strong>' . esc_html( get_the_title( $post->ID ) ) . '</strong> — ' . esc_html( $label ) . ' (' . esc_html__( 'Exam ID', 'smart-mcq-manager' ) . ': ' . esc_html( $post->ID ) . ')</li>';
        }
        return '<section class="smcq-upcoming-exams"><h2>' . esc_html__( 'Upcoming Examinations', 'smart-mcq-manager' ) . '</h2><ul>' . implode( '', $items ) . '</ul></section>';
    }
}