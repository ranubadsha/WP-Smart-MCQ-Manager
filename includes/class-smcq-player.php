<?php
/**
 * Step 5: Authenticated student examination delivery and private attempts.
 * Grading and answer review deliberately remain outside this release.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SMCQ_Player {
	const SCHEMA_VERSION = '1';
	const ROUTE_NS = 'smcq/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'smcq_exam', array( __CLASS__, 'shortcode' ) );
		add_action( 'add_meta_boxes_smcq_exam', array( __CLASS__, 'shortcode_box' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_install' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_privacy_export' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_privacy_erase' ) );
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'smcq_attempts';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			exam_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			active_key varchar(64) DEFAULT NULL,
			status varchar(16) NOT NULL DEFAULT 'active',
			completion_reason varchar(16) DEFAULT NULL,
			started_at datetime NOT NULL,
			deadline_at datetime NOT NULL,
			submitted_at datetime DEFAULT NULL,
			snapshot longtext NOT NULL,
			answers longtext NOT NULL,
			version int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY active_key (active_key),
			KEY user_exam (user_id,exam_id),
			KEY exam_status (exam_id,status)
		) {$charset};";
		dbDelta( $sql );
		update_option( 'smcq_attempt_schema', self::SCHEMA_VERSION, false );
	}

	public static function maybe_install() {
		if ( get_option( 'smcq_attempt_schema' ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function routes() {
		$auth = array( __CLASS__, 'can_play' );
		register_rest_route( self::ROUTE_NS, '/exams/(?P<id>\d+)/state', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'state' ), 'permission_callback' => $auth,
			'args' => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
		) );
		register_rest_route( self::ROUTE_NS, '/exams/(?P<id>\d+)/start', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'start' ), 'permission_callback' => $auth,
			'args' => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
		) );
		register_rest_route( self::ROUTE_NS, '/attempts/(?P<id>\d+)/answer', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'answer' ), 'permission_callback' => $auth,
		) );
		register_rest_route( self::ROUTE_NS, '/attempts/(?P<id>\d+)/submit', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'submit' ), 'permission_callback' => $auth,
		) );
	}

	/** REST cookie authentication requires a wp_rest nonce on browsers. */
	public static function can_play() {
		return is_user_logged_in();
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'smcq_exam' );
		$exam_id = absint( $atts['id'] );
		if ( ! $exam_id || 'smcq_exam' !== get_post_type( $exam_id ) || 'publish' !== get_post_status( $exam_id ) ) {
			return '<p>' . esc_html__( 'This examination is unavailable.', 'smart-mcq-manager' ) . '</p>';
		}
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to start this examination.', 'smart-mcq-manager' ) . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'smart-mcq-manager' ) . '</a></p>';
		}
		wp_enqueue_style( 'smcq-player', SMCQ_URL . 'assets/player.css', array(), SMCQ_VERSION );
		wp_enqueue_script( 'smcq-player', SMCQ_URL . 'assets/player.js', array(), SMCQ_VERSION, true );
		wp_localize_script( 'smcq-player', 'smcqPlayerSettings', array(
			'root' => esc_url_raw( rest_url( self::ROUTE_NS . '/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'messages' => array(
				'loading' => __( 'Loading examination…', 'smart-mcq-manager' ),
				'saving' => __( 'Saving answer…', 'smart-mcq-manager' ),
				'saved' => __( 'Answer saved.', 'smart-mcq-manager' ),
				'error' => __( 'The request failed. Check your connection and try again.', 'smart-mcq-manager' ),
				'confirm' => __( 'Submit your examination? You will not be able to edit your answers.', 'smart-mcq-manager' ),
				'finished' => __( 'Your attempt has been recorded. Scoring and detailed results are coming in Step 6.', 'smart-mcq-manager' ),
				'expired' => __( 'Time has expired. Your saved answers have been submitted.', 'smart-mcq-manager' ),
				'unsaved' => __( 'You have an unsaved answer. Leaving now may lose it.', 'smart-mcq-manager' ),
				'conflict' => __( 'This attempt was updated elsewhere. Reload to avoid overwriting answers.', 'smart-mcq-manager' ),
			),
		) );
		return '<section class="smcq-player" data-exam-id="' . esc_attr( $exam_id ) . '" aria-label="' . esc_attr__( 'Online examination', 'smart-mcq-manager' ) . '"><div class="smcq-player-status" role="status" aria-live="polite">' . esc_html__( 'Loading examination…', 'smart-mcq-manager' ) . '</div><div class="smcq-player-body"></div></section>';
	}

	public static function shortcode_box() {
		add_meta_box( 'smcq_exam_shortcode', __( 'Student Exam Page', 'smart-mcq-manager' ), static function ( $post ) {
			echo '<p>' . esc_html__( 'Create a WordPress page containing this shortcode. Only logged-in users may start published exams.', 'smart-mcq-manager' ) . '</p>';
			echo '<code>[smcq_exam id="' . esc_html( $post->ID ) . '"]</code>';
		}, 'smcq_exam', 'side', 'default' );
	}

	private static function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private static function utc_now() {
		return time();
	}

	private static function utc_sql( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private static function db_timestamp( $sql ) {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $sql, new DateTimeZone( 'UTC' ) );
		return $parsed ? $parsed->getTimestamp() : 0;
	}

	/** Convert the admin's site-timezone schedule into a UTC Unix timestamp. */
	public static function schedule_timestamp( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}
		$dt = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		return $dt && $dt->format( 'Y-m-d\TH:i' ) === $value ? $dt->getTimestamp() : null;
	}

	/** Earliest deadline wins: exam duration or global scheduled closing time. */
	public static function deadline_timestamp( $start, $minutes, $scheduled_end = null ) {
		$deadline = (int) $start + max( 1, min( 600, (int) $minutes ) ) * 60;
		return null === $scheduled_end ? $deadline : min( $deadline, (int) $scheduled_end );
	}

	private static function config( $exam_id ) {
		return array(
			'duration' => (int) get_post_meta( $exam_id, '_smcq_exam_duration', true ) ?: 60,
			'attempt_limit' => (int) get_post_meta( $exam_id, '_smcq_exam_attempt_limit', true ),
			'pass_percent' => (float) get_post_meta( $exam_id, '_smcq_exam_pass_percent', true ),
			'marking' => (string) get_post_meta( $exam_id, '_smcq_exam_marking', true ),
			'positive' => (float) get_post_meta( $exam_id, '_smcq_exam_positive', true ),
			'negative' => (float) get_post_meta( $exam_id, '_smcq_exam_negative', true ),
			'shuffle_questions' => 1 === (int) get_post_meta( $exam_id, '_smcq_exam_shuffle_questions', true ),
			'shuffle_options' => 1 === (int) get_post_meta( $exam_id, '_smcq_exam_shuffle_options', true ),
			'start' => self::schedule_timestamp( (string) get_post_meta( $exam_id, '_smcq_exam_start', true ) ),
			'end' => self::schedule_timestamp( (string) get_post_meta( $exam_id, '_smcq_exam_end', true ) ),
		);
	}

	private static function available_exam( $exam_id ) {
		return 'smcq_exam' === get_post_type( $exam_id ) && 'publish' === get_post_status( $exam_id );
	}

	private static function check_window( $config, $now ) {
		if ( null !== $config['start'] && $now < $config['start'] ) {
			return self::error( 'smcq_not_open', __( 'This exam has not opened yet.', 'smart-mcq-manager' ), 403 );
		}
		if ( null !== $config['end'] && $now >= $config['end'] ) {
			return self::error( 'smcq_closed', __( 'This exam has closed.', 'smart-mcq-manager' ), 403 );
		}
		return true;
	}

	private static function active_key( $exam, $user ) {
		return hash( 'sha256', 'smcq|' . $exam . '|' . $user );
	}

	private static function load_attempt( $id, $user ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d AND user_id = %d', $id, $user ), ARRAY_A );
	}

	private static function active_attempt( $exam, $user ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE active_key = %s AND exam_id = %d AND user_id = %d', self::active_key( $exam, $user ), $exam, $user ), ARRAY_A );
	}

	/** Timeout is finalized on the next server request; grading is deferred. */
	private static function finalize_if_due( $row ) {
		if ( $row && 'active' === $row['status'] && self::db_timestamp( $row['deadline_at'] ) <= self::utc_now() ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . self::table() . " SET status = 'submitted', active_key = NULL, completion_reason = 'timeout', submitted_at = deadline_at, version = version + 1 WHERE id = %d AND status = 'active'",
				(int) $row['id']
			) );
			return self::load_attempt( (int) $row['id'], (int) $row['user_id'] );
		}
		return $row;
	}

	/** Freeze both question content and hidden correct keys at attempt start. */
	private static function build_snapshot( $exam_id, $config ) {
		$ids = get_post_meta( $exam_id, '_smcq_exam_question_ids', true );
		if ( ! is_array( $ids ) ) {
			return self::error( 'smcq_empty_exam', __( 'No questions are assigned to this examination.', 'smart-mcq-manager' ) );
		}
		$ids = SMCQ_Exams::normalize_ids( $ids );
		if ( ! $ids ) {
			return self::error( 'smcq_empty_exam', __( 'No questions are assigned to this examination.', 'smart-mcq-manager' ) );
		}
		if ( $config['shuffle_questions'] ) {
			shuffle( $ids );
		}
		$questions = array();
		foreach ( $ids as $id ) {
			if ( ! SMCQ_Exams::valid_question( $id ) ) {
				return self::error( 'smcq_incomplete_exam', __( 'The exam contains an unavailable or incomplete question. Contact an administrator.', 'smart-mcq-manager' ) );
			}
			$raw_options = get_post_meta( $id, '_smcq_options', true );
			$order = array( 'A', 'B', 'C', 'D' );
			if ( $config['shuffle_options'] ) {
				shuffle( $order );
			}
			$points = 'fixed' === $config['marking'] ? $config['positive'] : get_post_meta( $id, '_smcq_marks', true );
			$negative = 'fixed' === $config['marking'] ? $config['negative'] : get_post_meta( $id, '_smcq_negative_marks', true );
			$questions[] = array(
				'id' => $id,
				'title' => wp_strip_all_tags( get_the_title( $id ), true ),
				'options' => array_map( 'strval', $raw_options ),
				'order' => $order,
				'correct' => (string) get_post_meta( $id, '_smcq_correct', true ),
				'points' => '' === $points ? 1.0 : (float) $points,
				'negative' => '' === $negative ? 0.0 : (float) $negative,
			);
		}
		return array( 'questions' => $questions, 'policy' => array( 'pass_percent' => $config['pass_percent'] ) );
	}

	/** Redact answer keys, grade fields, and future correct-answer metadata. */
	public static function public_questions( $snapshot ) {
		$public = array();
		foreach ( $snapshot['questions'] as $question ) {
			$options = array();
			foreach ( $question['order'] as $index => $key ) {
				$options[] = array( 'key' => $key, 'label' => chr( 65 + $index ), 'text' => $question['options'][ $key ] );
			}
			$public[] = array( 'id' => (int) $question['id'], 'title' => $question['title'], 'options' => $options );
		}
		return $public;
	}

	/** Validate answer submissions strictly against a frozen question set. */
	public static function valid_answer( $snapshot, $question_id, $choice ) {
		if ( ! is_int( $question_id ) || ! ( is_string( $choice ) || null === $choice ) ) {
			return false;
		}
		foreach ( $snapshot['questions'] as $question ) {
			if ( (int) $question['id'] === $question_id ) {
				return null === $choice || array_key_exists( $choice, $question['options'] );
			}
		}
		return false;
	}

	private static function view( $row ) {
		$snapshot = json_decode( $row['snapshot'], true );
		$answers = json_decode( $row['answers'], true );
		return array(
			'attemptId' => (int) $row['id'],
			'examId' => (int) $row['exam_id'],
			'examTitle' => get_the_title( (int) $row['exam_id'] ),
			'status' => $row['status'],
			'completionReason' => $row['completion_reason'],
			'deadline' => self::db_timestamp( $row['deadline_at'] ) * 1000,
			'serverNow' => self::utc_now() * 1000,
			'version' => (int) $row['version'],
			'questions' => self::public_questions( $snapshot ),
			'answers' => is_array( $answers ) ? $answers : array(),
		);
	}

	private static function minimal_view( $row ) {
		return array( 'status' => $row['status'], 'completionReason' => $row['completion_reason'] );
	}

	public static function state( $request ) {
		global $wpdb;
		$exam_id = absint( $request['id'] );
		if ( ! self::available_exam( $exam_id ) ) {
			return self::error( 'smcq_unavailable', __( 'This exam is unavailable.', 'smart-mcq-manager' ), 404 );
		}
		$user = get_current_user_id();
		$row = self::active_attempt( $exam_id, $user );
		if ( $row ) {
			$row = self::finalize_if_due( $row );
			if ( 'active' === $row['status'] ) {
				return rest_ensure_response( self::view( $row ) );
			}
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d AND exam_id = %d', $user, $exam_id ) );
		$last = $wpdb->get_row( $wpdb->prepare( 'SELECT status,completion_reason FROM ' . self::table() . ' WHERE user_id = %d AND exam_id = %d ORDER BY id DESC LIMIT 1', $user, $exam_id ), ARRAY_A );
		$config = self::config( $exam_id );
		$window = self::check_window( $config, self::utc_now() );
		return rest_ensure_response( array(
			'status' => 'idle', 'examTitle' => get_the_title( $exam_id ),
			'instructions' => wp_strip_all_tags( (string) get_post_field( 'post_content', $exam_id ), true ),
			'duration' => $config['duration'], 'attemptsUsed' => $count,
			'attemptLimit' => $config['attempt_limit'],
			'canStart' => ! is_wp_error( $window ) && ( 0 === $config['attempt_limit'] || $count < $config['attempt_limit'] ),
			'message' => is_wp_error( $window ) ? $window->get_error_message() : ( $last ? __( 'Your previous attempt was recorded.', 'smart-mcq-manager' ) : '' ),
		) );
	}

	public static function start( $request ) {
		global $wpdb;
		$exam_id = absint( $request['id'] );
		$user = get_current_user_id();
		if ( ! self::available_exam( $exam_id ) ) {
			return self::error( 'smcq_unavailable', __( 'This exam is unavailable.', 'smart-mcq-manager' ), 404 );
		}
		$now = self::utc_now();
		$config = self::config( $exam_id );
		$allowed = self::check_window( $config, $now );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$existing = self::active_attempt( $exam_id, $user );
		if ( $existing ) {
			$existing = self::finalize_if_due( $existing );
			if ( 'active' === $existing['status'] ) {
				return rest_ensure_response( self::view( $existing ) );
			}
		}
		$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d AND exam_id = %d', $user, $exam_id ) );
		if ( 0 !== $config['attempt_limit'] && $used >= $config['attempt_limit'] ) {
			return self::error( 'smcq_attempt_limit', __( 'You have reached the attempt limit.', 'smart-mcq-manager' ), 403 );
		}
		$snapshot = self::build_snapshot( $exam_id, $config );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$deadline = self::deadline_timestamp( $now, $config['duration'], $config['end'] );
		if ( $deadline <= $now ) {
			return self::error( 'smcq_closed', __( 'The exam has closed.', 'smart-mcq-manager' ), 403 );
		}
		$inserted = $wpdb->insert( self::table(), array(
			'exam_id' => $exam_id,
			'user_id' => $user,
			'active_key' => self::active_key( $exam_id, $user ),
			'status' => 'active',
			'started_at' => self::utc_sql( $now ),
			'deadline_at' => self::utc_sql( $deadline ),
			'snapshot' => wp_json_encode( $snapshot ),
			'answers' => '{}',
		) );
		if ( ! $inserted ) {
			// The active-key UNIQUE constraint prevents two active attempts across tabs.
			$existing = self::active_attempt( $exam_id, $user );
			if ( $existing ) {
				return rest_ensure_response( self::view( self::finalize_if_due( $existing ) ) );
			}
			return self::error( 'smcq_attempt_failed', __( 'Could not create an attempt. Please retry.', 'smart-mcq-manager' ), 500 );
		}
		return rest_ensure_response( self::view( self::load_attempt( (int) $wpdb->insert_id, $user ) ) );
	}

	public static function answer( $request ) {
		global $wpdb;
		$attempt_id = absint( $request['id'] );
		$user = get_current_user_id();
		$row = self::load_attempt( $attempt_id, $user );
		if ( ! $row ) {
			return self::error( 'smcq_no_attempt', __( 'Attempt not found.', 'smart-mcq-manager' ), 404 );
		}
		$row = self::finalize_if_due( $row );
		if ( 'active' !== $row['status'] ) {
			return self::error( 'smcq_already_finished', __( 'This attempt is already finished.', 'smart-mcq-manager' ), 409 );
		}
		$question_id = $request->get_param( 'questionId' );
		$choice = $request->get_param( 'choice' );
		$version = $request->get_param( 'version' );
		$snapshot = json_decode( $row['snapshot'], true );
		if ( ! is_int( $question_id ) || ! self::valid_answer( $snapshot, $question_id, $choice ) || ! is_int( $version ) || $version < 0 ) {
			return self::error( 'smcq_invalid_answer', __( 'Invalid question, choice, or version.', 'smart-mcq-manager' ), 400 );
		}
		if ( $version !== (int) $row['version'] ) {
			return self::error( 'smcq_version_conflict', __( 'This attempt changed in another tab. Reload the exam before continuing.', 'smart-mcq-manager' ), 409 );
		}
		$answers = json_decode( $row['answers'], true );
		$answers = is_array( $answers ) ? $answers : array();
		if ( null === $choice ) {
			unset( $answers[ (string) $question_id ] );
		} else {
			$answers[ (string) $question_id ] = $choice;
		}
		$new_version = $version + 1;
		$updated = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . ' SET answers = %s, version = %d WHERE id = %d AND user_id = %d AND status = %s AND version = %d AND deadline_at > %s',
			wp_json_encode( $answers ), $new_version, $attempt_id, $user, 'active', $version, self::utc_sql( self::utc_now() )
		) );
		if ( 1 !== $updated ) {
			return self::error( 'smcq_save_conflict', __( 'Answer could not be saved. Please reload the exam.', 'smart-mcq-manager' ), 409 );
		}
		return rest_ensure_response( array( 'saved' => true, 'version' => $new_version, 'answers' => $answers, 'serverNow' => self::utc_now() * 1000 ) );
	}

	public static function submit( $request ) {
		global $wpdb;
		$attempt_id = absint( $request['id'] );
		$user = get_current_user_id();
		$row = self::load_attempt( $attempt_id, $user );
		if ( ! $row ) {
			return self::error( 'smcq_no_attempt', __( 'Attempt not found.', 'smart-mcq-manager' ), 404 );
		}
		$row = self::finalize_if_due( $row );
		if ( 'active' === $row['status'] ) {
			$now = self::utc_sql( self::utc_now() );
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . self::table() . " SET status = 'submitted', active_key = NULL, completion_reason = 'manual', submitted_at = %s, version = version + 1 WHERE id = %d AND user_id = %d AND status = 'active' AND deadline_at > %s",
				$now, $attempt_id, $user, $now
			) );
			$row = self::finalize_if_due( self::load_attempt( $attempt_id, $user ) );
		}
		return rest_ensure_response( self::minimal_view( $row ) );
	}

	public static function register_privacy_export( $exporters ) {
		$exporters['smart-mcq-attempts'] = array( 'exporter_friendly_name' => __( 'Smart MCQ Attempts', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'privacy_export' ) );
		return $exporters;
	}

	public static function register_privacy_erase( $erasers ) {
		$erasers['smart-mcq-attempts'] = array( 'eraser_friendly_name' => __( 'Smart MCQ Attempts', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'privacy_erase' ) );
		return $erasers;
	}

	private static function privacy_user( $email ) {
		$user = get_user_by( 'email', $email );
		return $user ? (int) $user->ID : 0;
	}

	public static function privacy_export( $email, $page = 1 ) {
		global $wpdb;
		$user = self::privacy_user( $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$page = max( 1, (int) $page );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id ASC LIMIT 25 OFFSET %d', $user, ( $page - 1 ) * 25 ), ARRAY_A );
		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array( 'group_id' => 'smart-mcq-attempts', 'group_label' => __( 'Exam Attempts', 'smart-mcq-manager' ), 'item_id' => 'smcq-attempt-' . $row['id'], 'data' => array(
				array( 'name' => __( 'Exam ID', 'smart-mcq-manager' ), 'value' => $row['exam_id'] ),
				array( 'name' => __( 'Start (UTC)', 'smart-mcq-manager' ), 'value' => $row['started_at'] ),
				array( 'name' => __( 'Status', 'smart-mcq-manager' ), 'value' => $row['status'] ),
				array( 'name' => __( 'Submitted answers', 'smart-mcq-manager' ), 'value' => $row['answers'] ),
			) );
		}
		return array( 'data' => $data, 'done' => count( $rows ) < 25 );
	}

	public static function privacy_erase( $email, $page = 1 ) {
		global $wpdb;
		$user = self::privacy_user( $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$deleted = $wpdb->delete( self::table(), array( 'user_id' => $user ), array( '%d' ) );
		return array( 'items_removed' => $deleted > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}