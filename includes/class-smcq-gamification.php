<?php
/** Privacy-preserving, optional achievement badges for completed exams. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Gamification {
    public static function init() { add_shortcode( 'smcq_badges', array( __CLASS__, 'shortcode' ) ); }
    public static function badges( $scores ) {
        $completed = 0; $passed = 0; $best = 0;
        foreach ( (array) $scores as $score ) {
            if ( ! is_array( $score ) || ! isset( $score['percentage'] ) || ! is_numeric( $score['percentage'] ) ) { continue; }
            ++$completed; if ( ! empty( $score['passed'] ) ) { ++$passed; }
            $best = max( $best, (float) $score['percentage'] );
        }
        $badges = array();
        if ( $completed >= 1 ) { $badges[] = 'First Attempt'; }
        if ( $passed >= 1 ) { $badges[] = 'First Pass'; }
        if ( $completed >= 5 ) { $badges[] = 'Consistent Learner'; }
        if ( $passed >= 10 ) { $badges[] = 'Ten Exam Passes'; }
        if ( $best >= 90 ) { $badges[] = '90% Club'; }
        return array( 'completed' => $completed, 'passed' => $passed, 'badges' => $badges );
    }
    public static function shortcode() {
        if ( ! is_user_logged_in() ) { return '<p>' . esc_html__( 'Log in to see your achievements.', 'smart-mcq-manager' ) . '</p>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'smcq_attempts';
        $rows = $wpdb->get_col( $wpdb->prepare( "SELECT result_json FROM %i WHERE user_id=%d AND status='submitted' ORDER BY id DESC LIMIT 500", $table, get_current_user_id() ) );
        $scores = array();
        foreach ( (array) $rows as $row ) { $grade = json_decode( (string) $row, true ); if ( is_array( $grade ) ) { $scores[] = $grade; } }
        $data = self::badges( $scores );
        $out = '<section class="smcq-badges"><h2>' . esc_html__( 'My Achievements', 'smart-mcq-manager' ) . '</h2>';
        // translators: 1: number of completed exams, 2: number of passed exams.
		$out .= '<p>' . esc_html( sprintf( __( '%1$d completed, %2$d passed (latest 500 completed attempts)', 'smart-mcq-manager' ), $data['completed'], $data['passed'] ) ) . '</p><ul>';
        foreach ( $data['badges'] as $badge ) { $out .= '<li>🏅 ' . esc_html( $badge ) . '</li>'; }
        return $out . '</ul></section>';
    }
}