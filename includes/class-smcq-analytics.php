<?php
/**
 * Step 7: owner-scoped personal analytics, administrator reports, anonymous exam leaderboards.
 * All aggregates derive from frozen submitted attempt snapshots.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SMCQ_Analytics {
	const MAX_PERSONAL_ROWS = 200;
	const MAX_REPORT_SAMPLE = 500;
	const MAX_LEADERBOARD_ROWS = 2000;

	public static function init() {
		add_shortcode( 'smcq_dashboard', array( __CLASS__, 'dashboard_shortcode' ) );
		add_shortcode( 'smcq_leaderboard', array( __CLASS__, 'leaderboard_shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'smcq_attempts';
	}

	public static function routes() {
		register_rest_route( 'smcq/v1', '/analytics/me', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'my_analytics' ),
			'permission_callback' => static function () { return is_user_logged_in(); },
		) );
	}

	public static function dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'smart-mcq-manager' ) . '</p>';
		}
		wp_enqueue_style( 'smcq-analytics', SMCQ_URL . 'assets/analytics.css', array(), SMCQ_VERSION );
		wp_enqueue_script( 'smcq-analytics', SMCQ_URL . 'assets/analytics.js', array(), SMCQ_VERSION, true );
		wp_localize_script( 'smcq-analytics', 'smcqAnalytics', array(
			'url' => esc_url_raw( rest_url( 'smcq/v1/analytics/me' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'error' => __( 'Unable to load your dashboard. Refresh and try again.', 'smart-mcq-manager' ),
		) );
		return SMCQ_Reports::personal_links() . '<section class="smcq-dashboard" aria-label="' . esc_attr__( 'My MCQ performance', 'smart-mcq-manager' ) . '"><h2>' . esc_html__( 'My MCQ Performance', 'smart-mcq-manager' ) . '</h2><div class="smcq-dashboard-content" aria-live="polite">' . esc_html__( 'Loading your progress…', 'smart-mcq-manager' ) . '</div></section>';
	}

	/** No answer key is ever returned to the browser from this endpoint. */
	public static function my_analytics( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'smcq_auth_required', __( 'Log in first.', 'smart-mcq-manager' ), array( 'status' => 401 ) );
		}
		$table = self::table();
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'submitted'", $user_id
		) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,exam_id,started_at,submitted_at,snapshot,answers,result_json FROM {$table} WHERE user_id = %d AND status = 'submitted' ORDER BY id DESC LIMIT %d",
			$user_id, self::MAX_PERSONAL_ROWS
		), ARRAY_A );
		$summary = self::summarize( is_array( $rows ) ? $rows : array(), 20 );
		$summary['totalAttempts'] = $count;
		$summary['sampledAttempts'] = count( is_array( $rows ) ? $rows : array() );
		$summary['sampleLimit'] = self::MAX_PERSONAL_ROWS;
		return rest_ensure_response( $summary );
	}

	private static function numeric_grade( $row ) {
		$grade = isset( $row['result_json'] ) ? json_decode( (string) $row['result_json'], true ) : null;
		if ( is_array( $grade ) && isset( $grade['percentage'], $grade['score'], $grade['correct'], $grade['wrong'] ) && is_numeric( $grade['percentage'] ) && is_numeric( $grade['score'] ) ) {
			return $grade;
		}
		$snapshot = isset( $row['snapshot'] ) ? json_decode( (string) $row['snapshot'], true ) : null;
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['questions'] ) || ! is_array( $snapshot['questions'] ) ) { return null; }
		$answers = isset( $row['answers'] ) ? json_decode( (string) $row['answers'], true ) : array();
		return SMCQ_Results::grade( $snapshot, $answers );
	}

	/** Pure helper: aggregate capped, completed attempt records without WordPress dependencies. */
	public static function summarize( $rows, $trend_limit = 20 ) {
		$sum = 0.0;
		$passed = 0;
		$valid = 0;
		$correct = 0;
		$wrong = 0;
		$skipped = 0;
		$trend = array();
		$subjects = array();
		$topics = array();
		foreach ( $rows as $row ) {
			$grade = self::numeric_grade( $row );
			if ( ! $grade ) { continue; }
			++$valid;
			$sum += (float) $grade['percentage'];
			$passed += ! empty( $grade['passed'] ) ? 1 : 0;
			$correct += (int) $grade['correct'];
			$wrong += (int) $grade['wrong'];
			$skipped += isset( $grade['unanswered'] ) ? (int) $grade['unanswered'] : 0;
			if ( count( $trend ) < $trend_limit ) {
				$trend[] = array(
					'examId' => (int) $row['exam_id'], 'attemptId' => (int) $row['id'],
					'submittedAt' => isset( $row['submitted_at'] ) ? (string) $row['submitted_at'] : '',
					'percentage' => round( (float) $grade['percentage'], 2),
				);
			}
			$snapshot = isset( $row['snapshot'] ) ? json_decode( (string) $row['snapshot'], true ) : null;
			$answers = isset( $row['answers'] ) ? json_decode( (string) $row['answers'], true ) : array();
			if ( ! is_array( $snapshot ) || ! isset( $snapshot['questions'] ) || ! is_array( $snapshot['questions'] ) ) { continue; }
			$answers = is_array( $answers ) ? $answers : array();
			foreach ( $snapshot['questions'] as $question ) {
				if ( ! is_array( $question ) || ! isset( $question['id'] ) ) { continue; }
				$id = (string) $question['id'];
				$given = isset( $answers[ $id ] ) ? $answers[ $id ] : null;
				$options = isset( $question['options'] ) && is_array( $question['options'] ) ? $question['options'] : array();
				$canonical = SMCQ_Question_Types::canonical( $given, SMCQ_Question_Types::type( isset( $question['type'] ) ? $question['type'] : 'single' ), $options );
				$attempted = null !== $canonical;
				$is_correct = $attempted && isset( $question['correct'] ) && $canonical === (string) $question['correct'];
				foreach ( array( 'subjects' => &$subjects, 'topics' => &$topics ) as $key => &$bucket ) {
					$labels = isset( $question[ $key ] ) && is_array( $question[ $key ] ) ? $question[ $key ] : array();
					foreach ( array_unique( $labels ) as $label ) {
						if ( ! is_string( $label ) || '' === trim( $label ) ) { continue; }
						if ( ! isset( $bucket[ $label ] ) ) { $bucket[ $label ] = array( 'name' => $label, 'questions' => 0, 'attempted' => 0, 'correct' => 0, 'wrong' => 0, 'skipped' => 0 ); }
						++$bucket[ $label ]['questions'];
						if ( $attempted ) { ++$bucket[ $label ]['attempted']; } else { ++$bucket[ $label ]['skipped']; }
						if ( $is_correct ) { ++$bucket[ $label ]['correct']; }
						elseif ( $attempted ) { ++$bucket[ $label ]['wrong']; }
					}
				}
				unset( $bucket );
			}
		}
		return array(
			'gradedAttempts' => $valid,
			'averagePercentage' => $valid ? round( $sum / $valid, 2 ) : 0,
			'passedAttempts' => $passed,
			'correct' => $correct, 'wrong' => $wrong, 'unanswered' => $skipped,
			'trend' => array_reverse( $trend ),
			'subjects' => self::format_groups( $subjects ),
			'topics' => self::format_groups( $topics ),
		);
	}

	private static function format_groups( $groups ) {
		foreach ( $groups as &$group ) {
			$group['accuracy'] = $group['attempted'] ? round( $group['correct'] * 100 / $group['attempted'], 2 ) : null;
			$group['lowSample'] = $group['attempted'] < 3;
		}
		unset( $group );
		$groups = array_values( $groups );
		usort( $groups, static function ( $a, $b ) {
			if ( $a['attempted'] !== $b['attempted'] ) { return $b['attempted'] <=> $a['attempted']; }
			return strcmp( $a['name'], $b['name'] );
		} );
		return array_slice( $groups, 0, 30 );
	}

	/** Pure ranking helper: one best attempt per user; tie by fewer errors then shorter time. */
	public static function rank( $rows, $limit = 10 ) {
		$best = array();
		foreach ( $rows as $row ) {
			$grade = self::numeric_grade( $row );
			if ( ! $grade ) { continue; }
			$user = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
			if ( $user <= 0 ) { continue; }
			$start = isset( $row['started_at'] ) ? strtotime( $row['started_at'] . ' UTC' ) : false;
			$end = isset( $row['submitted_at'] ) ? strtotime( $row['submitted_at'] . ' UTC' ) : false;
			$duration = $start !== false && $end !== false ? max( 0, $end - $start ) : PHP_INT_MAX;
			$entry = array(
				'userId' => $user, 'score' => round( (float) $grade['score'], 2 ),
				'percentage' => round( (float) $grade['percentage'], 2 ),
				'wrong' => (int) $grade['wrong'], 'duration' => $duration,
			);
			if ( ! isset( $best[ $user ] ) || self::rank_cmp( $entry, $best[ $user ] ) < 0 ) { $best[ $user ] = $entry; }
		}
		$best = array_values( $best );
		usort( $best, array( __CLASS__, 'rank_cmp' ) );
		return array_slice( $best, 0, max( 1, min( 50, (int) $limit ) ) );
	}

	public static function rank_cmp( $a, $b ) {
		if ( $a['percentage'] !== $b['percentage'] ) { return $a['percentage'] > $b['percentage'] ? -1 : 1; }
		if ( $a['wrong'] !== $b['wrong'] ) { return $a['wrong'] <=> $b['wrong']; }
		if ( $a['duration'] !== $b['duration'] ) { return $a['duration'] <=> $b['duration']; }
		return $a['userId'] <=> $b['userId'];
	}

	/** Public identifiers deliberately hide WordPress usernames, emails and numeric IDs. */
	public static function anonymous_label( $exam_id, $user_id, $salt ) {
		return 'Participant ' . strtoupper( substr( hash_hmac( 'sha256', (string) $exam_id . ':' . (string) $user_id, $salt ), 0, 8 ) );
	}

	/** Pure visibility rule: explicit administrator opt-in AND a real end-time in the past. */
	public static function leaderboard_visible( $enabled, $end_time, $now ) {
		return (int) $enabled === 1 && is_int( $end_time ) && $end_time > 0 && (int) $now >= $end_time;
	}

	public static function leaderboard_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'smcq_leaderboard' );
		$exam_id = absint( $atts['id'] );
		if ( ! is_user_logged_in() ) { return '<p>' . esc_html__( 'Please log in to see the leaderboard.', 'smart-mcq-manager' ) . '</p>'; }
		if ( ! $exam_id || 'smcq_exam' !== get_post_type( $exam_id ) || 'publish' !== get_post_status( $exam_id ) ) {
			return '<p>' . esc_html__( 'The leaderboard is not available for this exam.', 'smart-mcq-manager' ) . '</p>';
		}
		$enabled = (int) get_post_meta( $exam_id, '_smcq_exam_leaderboard', true );
		if ( 1 !== $enabled ) { return '<p>' . esc_html__( 'The leaderboard is disabled for this examination.', 'smart-mcq-manager' ) . '</p>'; }
		$end = (string) get_post_meta( $exam_id, '_smcq_exam_end', true );
		$end_time = SMCQ_Player::schedule_timestamp( $end );
		if ( ! self::leaderboard_visible( get_post_meta( $exam_id, '_smcq_exam_leaderboard', true ), $end_time, time() ) ) {
			return '<p>' . esc_html__( 'The leaderboard will be published after this scheduled exam closes.', 'smart-mcq-manager' ) . '</p>';
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT id,user_id,started_at,submitted_at,snapshot,answers,result_json FROM ' . self::table() . " WHERE exam_id = %d AND status = 'submitted' ORDER BY id DESC LIMIT %d",
			$exam_id, self::MAX_LEADERBOARD_ROWS
		), ARRAY_A );
		$ranks = self::rank( is_array( $rows ) ? $rows : array(), 10 );
		if ( ! $ranks ) { return '<p>' . esc_html__( 'No completed exam attempts yet.', 'smart-mcq-manager' ) . '</p>'; }
		$html = '<section class="smcq-leaderboard"><h2>' . esc_html__( 'Exam Leaderboard', 'smart-mcq-manager' ) . '</h2>';
		$html .= '<div class="smcq-table-wrap"><table><thead><tr><th>' . esc_html__( 'Rank', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Participant', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Score (%)', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Wrong', 'smart-mcq-manager' ) . '</th></tr></thead><tbody>';
		foreach ( $ranks as $index => $rank ) {
			$label = self::anonymous_label( $exam_id, $rank['userId'], wp_salt( 'auth' ) );
			$html .= '<tr><td>' . esc_html( $index + 1 ) . '</td><td>' . esc_html( $label ) . '</td><td>' . esc_html( $rank['percentage'] ) . '%</td><td>' . esc_html( $rank['wrong'] ) . '</td></tr>';
		}
		$html .= '</tbody></table></div></section>';
		wp_enqueue_style( 'smcq-analytics', SMCQ_URL . 'assets/analytics.css', array(), SMCQ_VERSION );
		return $html;
	}

	public static function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=smcq_question', __( 'Exam Reports', 'smart-mcq-manager' ),
			__( 'Exam Reports', 'smart-mcq-manager' ), 'manage_options', 'smcq-exam-reports', array( 'SMCQ_Reports', 'admin_page' )
		);
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'smart-mcq-manager' ) ); }
		global $wpdb;
		$exam_id = isset( $_GET['smcq_exam'] ) ? absint( $_GET['smcq_exam'] ) : 0;
		$page = isset( $_GET['smcq_page'] ) ? max( 1, min( 10000, absint( $_GET['smcq_page'] ) ) ) : 1;
		$exam_list = get_posts( array( 'post_type' => 'smcq_exam', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 100, 'orderby' => 'ID', 'order' => 'DESC' ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'MCQ Exam Reports', 'smart-mcq-manager' ) . '</h1><form method="get"><input type="hidden" name="post_type" value="smcq_question"/><input type="hidden" name="page" value="smcq-exam-reports"/>';
		echo '<label for="smcq_report_exam">' . esc_html__( 'Select examination', 'smart-mcq-manager' ) . '</label> <select id="smcq_report_exam" name="smcq_exam"><option value="0">' . esc_html__( 'Choose an exam', 'smart-mcq-manager' ) . '</option>';
		foreach ( $exam_list as $exam ) { echo '<option value="' . esc_attr( $exam->ID ) . '" ' . selected( $exam_id, $exam->ID, false ) . '>' . esc_html( get_the_title( $exam->ID ) ) . ' (#' . esc_html( $exam->ID ) . ')</option>'; }
		echo '</select> <button type="submit" class="button button-primary">' . esc_html__( 'View Report', 'smart-mcq-manager' ) . '</button></form>';
		if ( ! $exam_id || 'smcq_exam' !== get_post_type( $exam_id ) ) { echo '<p>' . esc_html__( 'Select an exam to see administrator-only student results.', 'smart-mcq-manager' ) . '</p></div>'; return; }
		$table = self::table();
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE exam_id = %d AND status = 'submitted'", $exam_id ) );
		$stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,exam_id,started_at,submitted_at,snapshot,answers,result_json FROM {$table} WHERE exam_id = %d AND status = 'submitted' ORDER BY id DESC LIMIT %d",
			$exam_id, self::MAX_REPORT_SAMPLE
		), ARRAY_A );
		$summary = self::summarize( is_array( $stats ) ? $stats : array(), 0 );
		echo '<p><strong>' . esc_html__( 'Completed attempts:', 'smart-mcq-manager' ) . '</strong> ' . esc_html( $total ) . ' · <strong>' . esc_html__( 'Average score in recent sample:', 'smart-mcq-manager' ) . '</strong> ' . esc_html( $summary['averagePercentage'] ) . '% · ' . esc_html__( 'Sample size:', 'smart-mcq-manager' ) . ' ' . esc_html( $summary['gradedAttempts'] ) . ' (' . esc_html__( 'maximum', 'smart-mcq-manager' ) . ' ' . esc_html( self::MAX_REPORT_SAMPLE ) . ')</p>';
		$per_page = 25;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,submitted_at,snapshot,answers,result_json FROM {$table} WHERE exam_id = %d AND status = 'submitted' ORDER BY id DESC LIMIT %d OFFSET %d",
			$exam_id, $per_page, ( $page - 1 ) * $per_page
		), ARRAY_A );
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Attempt', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Student', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Submitted UTC', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Score', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Accuracy', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Result', 'smart-mcq-manager' ) . '</th></tr></thead><tbody>';
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$g = self::numeric_grade( $row );
			$u = get_userdata( (int) $row['user_id'] );
			$name = $u ? $u->display_name . ' (' . $u->user_login . ')' : __( 'Deleted account', 'smart-mcq-manager' );
			echo '<tr><td>#' . esc_html( $row['id'] ) . '</td><td>' . esc_html( $name ) . '</td><td>' . esc_html( $row['submitted_at'] ) . '</td><td>' . esc_html( $g ? $g['score'] . ' / ' . $g['maxMarks'] : '—' ) . '</td><td>' . esc_html( $g ? $g['accuracy'] . '%' : '—' ) . '</td><td>' . esc_html( $g ? ( $g['passed'] ? __( 'Passed', 'smart-mcq-manager' ) : __( 'Failed', 'smart-mcq-manager' ) ) : __( 'Unscored', 'smart-mcq-manager' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $total > $per_page ) {
			$base = add_query_arg( array( 'post_type' => 'smcq_question', 'page' => 'smcq-exam-reports', 'smcq_exam' => $exam_id ), admin_url( 'edit.php' ) );
			$pages = (int) ceil( $total / $per_page );
			echo '<p class="tablenav">';
			if ( $page > 1 ) { echo '<a class="button" href="' . esc_url( add_query_arg( 'smcq_page', $page - 1, $base ) ) . '">' . esc_html__( 'Previous', 'smart-mcq-manager' ) . '</a> '; }
			echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'smart-mcq-manager' ), $page, $pages ) );
			if ( $page < $pages ) { echo ' <a class="button" href="' . esc_url( add_query_arg( 'smcq_page', $page + 1, $base ) ) . '">' . esc_html__( 'Next', 'smart-mcq-manager' ) . '</a>'; }
			echo '</p>';
		}
		echo '</div>';
	}
}