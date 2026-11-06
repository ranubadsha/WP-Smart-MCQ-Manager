<?php
/**
 * Step 8: Opt-in question revision, personal bookmarks and private practice sessions.
 * Exam attempts are read as frozen historical records; practice never modifies them.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SMCQ_Practice {
	const SCHEMA = '2';
	const MAX_HISTORY = 200;
	const MAX_POOL = 500;
	const MAX_QUESTIONS = 20;
	const MIN_TOPIC_ATTEMPTS = 3;
	const WEAK_ACCURACY = 60;

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_install' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'smcq_practice', array( __CLASS__, 'shortcode' ) );
		add_action( 'add_meta_boxes_smcq_question', array( __CLASS__, 'practice_box' ) );
		add_action( 'save_post_smcq_question', array( __CLASS__, 'save_question_setting' ), 20, 2 );
		add_filter( 'bulk_actions-edit-smcq_question', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-smcq_question', array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'smcq_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$bookmarks = self::table( 'bookmarks' );
		$sessions = self::table( 'practice_sessions' );
		dbDelta( "CREATE TABLE {$bookmarks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			question_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_question (user_id,question_id),
			KEY question_id (question_id)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			mode varchar(16) NOT NULL,
			active_key varchar(64) DEFAULT NULL,
			status varchar(16) NOT NULL DEFAULT 'active',
			started_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			snapshot longtext NOT NULL,
			answers longtext NOT NULL,
			result_json longtext DEFAULT NULL,
			version int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY active_key (active_key),
			KEY user_status (user_id,status),
			KEY user_mode (user_id,mode),
			KEY user_status_id (user_id,status,id)
		) {$charset};" );
		// Do not advance schema option if a host failed to create either table.
		$book_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $bookmarks ) ) );
		$session_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions ) ) );
		if ( $book_ok === $bookmarks && $session_ok === $sessions && SMCQ_Health::has_indexes( $sessions, array( 'user_status_id' ) ) ) {
			update_option( 'smcq_practice_schema', self::SCHEMA, false );
		}
	}

	public static function maybe_install() {
		if ( get_option( 'smcq_practice_schema' ) !== self::SCHEMA ) { self::install(); }
	}

	public static function practice_box( $post ) {
		add_meta_box( 'smcq_practice_access', __( 'Student Practice Availability', 'smart-mcq-manager' ), static function ( $post ) {
			wp_nonce_field( 'smcq_practice_access_' . $post->ID, 'smcq_practice_access_nonce' );
			printf( '<label><input type="checkbox" name="smcq_practice_enabled" value="1" %s /> %s</label>', checked( 1, (int) get_post_meta( $post->ID, '_smcq_practice_enabled', true ), false ), esc_html__( 'Allow students to practice and bookmark this question', 'smart-mcq-manager' ) );
			echo '<p class="description">' . esc_html__( 'Off by default. Enabling makes this question available to authenticated practice users and reveals its answer AFTER they answer or finish their practice. Do not enable questions requiring embargo during a live examination.', 'smart-mcq-manager' ) . '</p>';
		}, 'smcq_question', 'side', 'default' );
	}

	public static function save_question_setting( $id, $post ) {
		if ( ! $post || 'smcq_question' !== $post->post_type || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || ! current_user_can( 'edit_post', $id ) ) { return; }
		$nonce = isset( $_POST['smcq_practice_access_nonce'] ) && is_string( $_POST['smcq_practice_access_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['smcq_practice_access_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'smcq_practice_access_' . $id ) ) { return; }
		update_post_meta( $id, '_smcq_practice_enabled', isset( $_POST['smcq_practice_enabled'] ) && '1' === (string) $_POST['smcq_practice_enabled'] ? 1 : 0 );
	}

	public static function bulk_actions( $actions ) {
		$actions['smcq_enable_practice'] = __( 'Enable practice availability', 'smart-mcq-manager' );
		$actions['smcq_disable_practice'] = __( 'Disable practice availability', 'smart-mcq-manager' );
		return $actions;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( ! in_array( $action, array( 'smcq_enable_practice', 'smcq_disable_practice' ), true ) ) { return $redirect; }
		foreach ( array_slice( array_map( 'absint', (array) $ids ), 0, 200 ) as $id ) {
			if ( 'smcq_question' === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
				update_post_meta( $id, '_smcq_practice_enabled', 'smcq_enable_practice' === $action ? 1 : 0 );
			}
		}
		return $redirect;
	}

	/** A question must be explicitly released for practice, not merely present in a private exam bank. */
	public static function eligible( $id ) {
		return $id > 0 && 'smcq_question' === get_post_type( $id ) && 'publish' === get_post_status( $id ) && 1 === (int) get_post_meta( $id, '_smcq_practice_enabled', true ) && SMCQ_Exams::valid_question( $id );
	}

	public static function routes() {
		$auth = static function () { return is_user_logged_in(); };
		foreach ( array(
			array( '/practice/overview', 'GET', 'overview' ),
			array( '/practice/start', 'POST', 'start' ),
			array( '/practice/state', 'GET', 'state' ),
			array( '/practice/answer', 'POST', 'answer' ),
			array( '/practice/finish', 'POST', 'finish' ),
			array( '/practice/bookmarks/(?P<id>\d+)', 'POST', 'bookmark' ),
			array( '/practice/bookmarks/(?P<id>\d+)', 'DELETE', 'unbookmark' ),
		) as $route ) {
			register_rest_route( 'smcq/v1', $route[0], array( 'methods' => $route[1], 'callback' => array( __CLASS__, $route[2] ), 'permission_callback' => $auth ) );
		}
	}

	public static function shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Log in to access personal revision practice.', 'smart-mcq-manager' ) . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'smart-mcq-manager' ) . '</a></p>';
		}
		wp_enqueue_style( 'smcq-practice', SMCQ_URL . 'assets/practice.css', array(), SMCQ_VERSION );
		wp_enqueue_script( 'smcq-practice', SMCQ_URL . 'assets/practice.js', array(), SMCQ_VERSION, true );
		wp_localize_script( 'smcq-practice', 'smcqPracticeSettings', array( 'root' => esc_url_raw( rest_url( 'smcq/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
		return '<section class="smcq-practice" aria-label="' . esc_attr__( 'My revision practice', 'smart-mcq-manager' ) . '"><h2>' . esc_html__( 'Smart MCQ Practice', 'smart-mcq-manager' ) . '</h2><div class="smcq-practice-message" aria-live="polite">' . esc_html__( 'Loading revision options…', 'smart-mcq-manager' ) . '</div><div class="smcq-practice-body"></div></section>';
	}

	private static function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private static function bookmarks( $user ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT question_id FROM ' . self::table( 'bookmarks' ) . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d', $user, self::MAX_POOL ) );
		return array_values( array_unique( array_map( 'intval', is_array( $rows ) ? $rows : array() ) ) );
	}

	private static function history_rows( $user ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT snapshot,answers FROM ' . self::table( 'attempts' ) . " WHERE user_id = %d AND status = 'submitted' ORDER BY id DESC LIMIT %d",
			$user, self::MAX_HISTORY
		), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Pure helper: latest answered status for each question, plus accuracy by frozen topic. */
	public static function analyze_history( $rows ) {
		$latest = array();
		$topics = array();
		foreach ( $rows as $row ) {
			$snapshot = isset( $row['snapshot'] ) ? json_decode( (string) $row['snapshot'], true ) : array();
			$answers = isset( $row['answers'] ) ? json_decode( (string) $row['answers'], true ) : array();
			if ( ! is_array( $snapshot ) || ! isset( $snapshot['questions'] ) || ! is_array( $snapshot['questions'] ) ) { continue; }
			$answers = is_array( $answers ) ? $answers : array();
			foreach ( $snapshot['questions'] as $q ) {
				$id = isset( $q['id'] ) ? (int) $q['id'] : 0;
				if ( $id < 1 ) { continue; }
				$choice = isset( $answers[(string) $id] ) && is_string( $answers[(string) $id] ) ? $answers[(string) $id] : '';
				$options = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : array();
				$attempted = '' !== $choice && array_key_exists( $choice, $options );
				$correct = $attempted && isset( $q['correct'] ) && $choice === (string) $q['correct'];
				// Rows are descending: the first seen answered status is the most recent one.
				if ( $attempted && ! isset( $latest[$id] ) ) { $latest[$id] = $correct ? 'correct' : 'wrong'; }
				if ( $attempted ) {
					foreach ( array_unique( isset( $q['topics'] ) && is_array( $q['topics'] ) ? $q['topics'] : array() ) as $topic ) {
						if ( ! is_string( $topic ) || '' === trim( $topic ) ) { continue; }
						if ( ! isset( $topics[$topic] ) ) { $topics[$topic] = array( 'attempted' => 0, 'correct' => 0 ); }
						++$topics[$topic]['attempted'];
						if ( $correct ) { ++$topics[$topic]['correct']; }
					}
				}
			}
		}
		$wrong = array();
		foreach ( $latest as $id => $status ) { if ( 'wrong' === $status ) { $wrong[] = (int) $id; } }
		$weak = array();
		foreach ( $topics as $name => $metric ) {
			if ( $metric['attempted'] >= self::MIN_TOPIC_ATTEMPTS ) {
				$accuracy = round( 100 * $metric['correct'] / $metric['attempted'], 2 );
				if ( $accuracy < self::WEAK_ACCURACY ) { $weak[$name] = array( 'name' => $name, 'attempted' => $metric['attempted'], 'accuracy' => $accuracy ); }
			}
		}
		uasort( $weak, static function ( $a, $b ) { return $a['accuracy'] <=> $b['accuracy']; } );
		return array( 'wrongIds' => $wrong, 'weakTopics' => array_values( array_slice( $weak, 0, 10 ) ) );
	}

	private static function eligible_ids( $ids, $limit = 500 ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, self::MAX_POOL );
		if ( ! $ids ) { return array(); }
		$found = get_posts( array( 'post_type' => 'smcq_question', 'post_status' => 'publish', 'fields' => 'ids', 'post__in' => $ids, 'orderby' => 'post__in', 'numberposts' => min( self::MAX_POOL, max( 1, (int) $limit ) ), 'meta_query' => array( array( 'key' => '_smcq_practice_enabled', 'value' => '1' ) ) ) );
		return array_values( array_filter( array_map( 'intval', $found ), array( __CLASS__, 'eligible' ) ) );
	}

	private static function weak_topic_ids( $weak, $limit = 300 ) {
		$names = array_column( $weak, 'name' );
		if ( ! $names ) { return array(); }
		$term_ids = array();
		foreach ( array_slice( $names, 0, 10 ) as $name ) {
			$terms = get_terms( array( 'taxonomy' => 'smcq_topic', 'hide_empty' => false, 'name' => $name, 'number' => 10 ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) { if ( $term->name === $name ) { $term_ids[] = (int) $term->term_id; } }
			}
		}
		if ( ! $term_ids ) { return array(); }
		$ids = get_posts( array( 'post_type' => 'smcq_question', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => min( self::MAX_POOL, max( 1, (int) $limit ) ), 'orderby' => 'rand',
			'tax_query' => array( array( 'taxonomy' => 'smcq_topic', 'field' => 'term_id', 'terms' => array_unique( $term_ids ) ) ),
			'meta_query' => array( array( 'key' => '_smcq_practice_enabled', 'value' => '1' ) ),
		) );
		return array_values( array_filter( array_map( 'intval', $ids ), array( __CLASS__, 'eligible' ) ) );
	}

	/** Pure prioritized pool merge, preserving uniqueness and bounded counts. */
	public static function combine_pool( $wrong, $bookmarks, $weak, $mode, $count ) {
		$count = max( 1, min( self::MAX_QUESTIONS, (int) $count ) );
		if ( 'wrong' === $mode ) { $ids = $wrong; }
		elseif ( 'bookmarks' === $mode ) { $ids = $bookmarks; }
		elseif ( 'weak' === $mode ) { $ids = $weak; }
		else { $ids = array_merge( $wrong, $bookmarks, $weak ); }
		return array_slice( array_values( array_unique( array_map( 'intval', $ids ) ) ), 0, $count );
	}

	private static function pool( $mode, $count, $history, $bookmarks ) {
		$wrong = in_array( $mode, array( 'wrong', 'mixed' ), true ) ? self::eligible_ids( $history['wrongIds'] ) : array();
		$book = in_array( $mode, array( 'bookmarks', 'mixed' ), true ) ? self::eligible_ids( $bookmarks ) : array();
		$weak = in_array( $mode, array( 'weak', 'mixed' ), true ) ? self::weak_topic_ids( $history['weakTopics'] ) : array();
		shuffle( $wrong ); shuffle( $book ); shuffle( $weak );
		return self::combine_pool( $wrong, $book, $weak, $mode, $count );
	}

	private static function snapshot( $ids ) {
		$questions = array();
		foreach ( $ids as $id ) {
			if ( ! self::eligible( $id ) ) { continue; }
			$raw = get_post_meta( $id, '_smcq_options', true );
			$order = array( 'A', 'B', 'C', 'D' );
			shuffle( $order );
			$questions[] = array(
				'id' => $id,
				'title' => wp_strip_all_tags( get_the_title( $id ), true ),
				'options' => array_map( 'strval', $raw ),
				'order' => $order,
				'correct' => (string) get_post_meta( $id, '_smcq_correct', true ),
				'explanation' => wp_strip_all_tags( (string) get_post_meta( $id, '_smcq_explanation', true ), true ),
				'points' => 1.0, 'negative' => 0.0,
			);
		}
		return array( 'questions' => $questions, 'policy' => array( 'pass_percent' => 60 ) );
	}

	private static function active_key( $user, $mode ) { return hash( 'sha256', 'smcq-practice|' . $user . '|' . $mode ); }

	private static function load( $id, $user ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'practice_sessions' ) . ' WHERE id = %d AND user_id = %d', $id, $user ), ARRAY_A );
	}

	private static function active( $user, $mode ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'practice_sessions' ) . ' WHERE active_key = %s AND user_id = %d AND mode = %s', self::active_key( $user, $mode ), $user, $mode ), ARRAY_A );
	}

	/** Never send unattempted answers or hidden keys in an active session. */
	public static function feedback( $snapshot, $answers, $show_all = false ) {
		$answer_keys = is_array( $answers ) ? $answers : array();
		$review = array();
		foreach ( SMCQ_Results::review( $snapshot, $answer_keys ) as $item ) {
			if ( $show_all || null !== $item['yourAnswer'] ) { $review[(string) $item['questionId']] = $item; }
		}
		return $review;
	}

	private static function view( $row ) {
		$snapshot = json_decode( $row['snapshot'], true );
		$answers = json_decode( $row['answers'], true );
		$answers = is_array( $answers ) ? $answers : array();
		$complete = 'completed' === $row['status'];
		return array(
			'sessionId' => (int) $row['id'], 'mode' => $row['mode'], 'status' => $row['status'], 'version' => (int) $row['version'],
			'questions' => SMCQ_Player::public_questions( $snapshot ), 'answers' => $answers,
			'feedback' => self::feedback( $snapshot, $answers, $complete ),
			'grade' => $complete ? json_decode( $row['result_json'], true ) : null,
		);
	}

	public static function overview( $request ) {
		global $wpdb;
		$user = get_current_user_id();
		$history = self::analyze_history( self::history_rows( $user ) );
		$bookmarks = self::bookmarks( $user );
		$recent = $wpdb->get_results( $wpdb->prepare(
			'SELECT id,mode,started_at,completed_at,result_json FROM ' . self::table( 'practice_sessions' ) . " WHERE user_id = %d AND status = 'completed' ORDER BY id DESC LIMIT 10", $user
		), ARRAY_A );
		$trend = array();
		foreach ( (array) $recent as $row ) {
			$grade = json_decode( $row['result_json'], true );
			if ( ! is_array( $grade ) ) { continue; }
			$trend[] = array( 'mode' => $row['mode'], 'date' => $row['completed_at'], 'accuracy' => $grade['accuracy'], 'correct' => $grade['correct'], 'total' => $grade['totalQuestions'] );
		}
		return rest_ensure_response( array( 'bookmarks' => count( $bookmarks ), 'bookmarkIds' => $bookmarks, 'wrong' => count( $history['wrongIds'] ), 'weakTopics' => $history['weakTopics'], 'trend' => array_reverse( $trend ), 'sampleLimit' => self::MAX_HISTORY ) );
	}

	public static function start( $request ) {
		global $wpdb;
		$user = get_current_user_id();
		$mode = $request->get_param( 'mode' );
		if ( ! in_array( $mode, array( 'wrong', 'bookmarks', 'weak', 'mixed' ), true ) ) { return self::error( 'smcq_practice_mode', __( 'Select a valid practice mode.', 'smart-mcq-manager' ) ); }
		$count = $request->get_param( 'count' );
		if ( ! is_numeric( $count ) || (int) $count < 1 || (int) $count > self::MAX_QUESTIONS ) { return self::error( 'smcq_practice_count', __( 'Choose between 1 and 20 questions.', 'smart-mcq-manager' ) ); }
		$current = self::active( $user, $mode );
		if ( $current ) { return rest_ensure_response( self::view( $current ) ); }
		$history = self::analyze_history( self::history_rows( $user ) );
		$ids = self::pool( $mode, (int) $count, $history, self::bookmarks( $user ) );
		if ( ! $ids ) { return self::error( 'smcq_empty_practice', __( 'No released practice questions match this mode. Ask your instructor to enable practice on eligible questions.', 'smart-mcq-manager' ), 404 ); }
		$snapshot = self::snapshot( $ids );
		if ( ! $snapshot['questions'] ) { return self::error( 'smcq_empty_practice', __( 'No valid questions are available for practice.', 'smart-mcq-manager' ), 404 ); }
		$success = $wpdb->insert( self::table( 'practice_sessions' ), array(
			'user_id' => $user, 'mode' => $mode, 'active_key' => self::active_key( $user, $mode ), 'status' => 'active',
			'started_at' => gmdate( 'Y-m-d H:i:s' ), 'snapshot' => wp_json_encode( $snapshot ), 'answers' => '{}', 'version' => 0,
		), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ) );
		if ( ! $success ) {
			$current = self::active( $user, $mode );
			return $current ? rest_ensure_response( self::view( $current ) ) : self::error( 'smcq_practice_db', __( 'Could not start practice.', 'smart-mcq-manager' ), 500 );
		}
		return rest_ensure_response( self::view( self::load( $wpdb->insert_id, $user ) ) );
	}

	public static function state( $request ) {
		$user = get_current_user_id();
		$id = absint( $request->get_param( 'id' ) );
		$mode = (string) $request->get_param( 'mode' );
		$row = $id ? self::load( $id, $user ) : ( in_array( $mode, array( 'wrong', 'bookmarks', 'weak', 'mixed' ), true ) ? self::active( $user, $mode ) : null );
		if ( ! $row ) { return self::error( 'smcq_practice_missing', __( 'Practice session not found.', 'smart-mcq-manager' ), 404 ); }
		return rest_ensure_response( self::view( $row ) );
	}

	/** Atomic answer lock: each question can be answered only once; retries cannot re-grade it. */
	public static function answer( $request ) {
		global $wpdb;
		$user = get_current_user_id();
		$id = absint( $request->get_param( 'sessionId' ) );
		$qid = absint( $request->get_param( 'questionId' ) );
		$choice = $request->get_param( 'choice' );
		$version = $request->get_param( 'version' );
		if ( ! is_string( $choice ) || ! is_numeric( $version ) || (int) $version < 0 || (string) (int) $version !== (string) $version ) { return self::error( 'smcq_bad_answer', __( 'Invalid answer request.', 'smart-mcq-manager' ) ); }
		$row = self::load( $id, $user );
		if ( ! $row || 'active' !== $row['status'] ) { return self::error( 'smcq_practice_inactive', __( 'Practice is not active.', 'smart-mcq-manager' ), 409 ); }
		$snapshot = json_decode( $row['snapshot'], true );
		$answers = json_decode( $row['answers'], true );
		$answers = is_array( $answers ) ? $answers : array();
		if ( ! isset( $snapshot['questions'] ) || ! SMCQ_Player::valid_answer( $snapshot, $qid, $choice ) ) { return self::error( 'smcq_bad_answer', __( 'Invalid answer for this question.', 'smart-mcq-manager' ) ); }
		if ( array_key_exists( (string) $qid, $answers ) ) { return self::error( 'smcq_already_answered', __( 'This question has already been answered.', 'smart-mcq-manager' ), 409 ); }
		if ( (int) $row['version'] !== (int) $version ) { return self::error( 'smcq_practice_conflict', __( 'Practice changed in another tab. Reload to continue.', 'smart-mcq-manager' ), 409 ); }
		$answers[(string) $qid] = $choice;
		$updated = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table( 'practice_sessions' ) . " SET answers = %s, version = version + 1 WHERE id = %d AND user_id = %d AND status = 'active' AND version = %d",
			wp_json_encode( $answers ), $id, $user, (int) $version
		) );
		if ( 1 !== $updated ) { return self::error( 'smcq_practice_conflict', __( 'Another answer was saved first. Reload your practice.', 'smart-mcq-manager' ), 409 ); }
		return rest_ensure_response( self::view( self::load( $id, $user ) ) );
	}

	public static function finish( $request ) {
		global $wpdb;
		$user = get_current_user_id();
		$id = absint( $request->get_param( 'sessionId' ) );
		for ( $i = 0; $i < 5; ++$i ) {
			$row = self::load( $id, $user );
			if ( ! $row ) { return self::error( 'smcq_practice_missing', __( 'Practice session not found.', 'smart-mcq-manager' ), 404 ); }
			if ( 'completed' === $row['status'] ) { return rest_ensure_response( self::view( $row ) ); }
			if ( 'active' !== $row['status'] ) { return self::error( 'smcq_practice_inactive', __( 'Practice is not active.', 'smart-mcq-manager' ), 409 ); }
			$snapshot = json_decode( $row['snapshot'], true );
			$answers = json_decode( $row['answers'], true );
			if ( ! is_array( $snapshot ) || ! isset( $snapshot['questions'] ) ) { return self::error( 'smcq_bad_snapshot', __( 'The practice snapshot is not valid.', 'smart-mcq-manager' ), 500 ); }
			$grade = SMCQ_Results::grade( $snapshot, $answers );
			$updated = $wpdb->query( $wpdb->prepare(
				'UPDATE ' . self::table( 'practice_sessions' ) . " SET status = 'completed', active_key = NULL, result_json = %s, completed_at = %s, version = version + 1 WHERE id = %d AND user_id = %d AND status = 'active' AND version = %d",
				wp_json_encode( $grade ), gmdate( 'Y-m-d H:i:s' ), $id, $user, (int) $row['version']
			) );
			if ( 1 === $updated ) { return rest_ensure_response( self::view( self::load( $id, $user ) ) ); }
		}
		return self::error( 'smcq_practice_conflict', __( 'Could not finish due to another session update.', 'smart-mcq-manager' ), 409 );
	}

	public static function bookmark( $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		if ( ! self::eligible( $id ) ) { return self::error( 'smcq_bookmark_unavailable', __( 'This question is not released for student practice.', 'smart-mcq-manager' ), 404 ); }
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::table( 'bookmarks' ) . ' (user_id,question_id,created_at) VALUES (%d,%d,%s)', get_current_user_id(), $id, gmdate( 'Y-m-d H:i:s' ) ) );
		return rest_ensure_response( array( 'bookmarked' => true, 'questionId' => $id ) );
	}

	public static function unbookmark( $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		$wpdb->delete( self::table( 'bookmarks' ), array( 'user_id' => get_current_user_id(), 'question_id' => $id ), array( '%d', '%d' ) );
		return rest_ensure_response( array( 'bookmarked' => false, 'questionId' => $id ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['smcq-revision'] = array( 'exporter_friendly_name' => __( 'Smart MCQ Practice and Bookmarks', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'privacy_export' ) );
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['smcq-revision'] = array( 'eraser_friendly_name' => __( 'Smart MCQ Practice and Bookmarks', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'privacy_erase' ) );
		return $erasers;
	}

	public static function privacy_export( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$id = (int) $user->ID;
		$page = max( 1, (int) $page );
		$book = self::table( 'bookmarks' ); $sessions = self::table( 'practice_sessions' );
		$bookmarks = 1 === $page ? $wpdb->get_col( $wpdb->prepare( "SELECT question_id FROM {$book} WHERE user_id = %d LIMIT 10000", $id ) ) : array();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,mode,started_at,completed_at,answers,result_json FROM {$sessions} WHERE user_id = %d ORDER BY id ASC LIMIT 25 OFFSET %d", $id, ( $page - 1 ) * 25 ), ARRAY_A );
		$data = array();
		if ( $bookmarks ) { $data[] = array( 'group_id' => 'smcq-revision', 'group_label' => __( 'Practice', 'smart-mcq-manager' ), 'item_id' => 'smcq-bookmarks', 'data' => array( array( 'name' => __( 'Bookmarked Question IDs', 'smart-mcq-manager' ), 'value' => implode( ', ', $bookmarks ) ) ) ); }
		foreach ( (array) $rows as $row ) {
			$data[] = array( 'group_id' => 'smcq-revision', 'group_label' => __( 'Practice', 'smart-mcq-manager' ), 'item_id' => 'smcq-practice-' . $row['id'], 'data' => array(
				array( 'name' => __( 'Practice Mode', 'smart-mcq-manager' ), 'value' => $row['mode'] ),
				array( 'name' => __( 'Started UTC', 'smart-mcq-manager' ), 'value' => $row['started_at'] ),
				array( 'name' => __( 'Answers', 'smart-mcq-manager' ), 'value' => $row['answers'] ),
				array( 'name' => __( 'Result', 'smart-mcq-manager' ), 'value' => $row['result_json'] ),
			) );
		}
		return array( 'data' => $data, 'done' => count( (array) $rows ) < 25 );
	}

	public static function privacy_erase( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$id = (int) $user->ID;
		$a = $wpdb->delete( self::table( 'bookmarks' ), array( 'user_id' => $id ), array( '%d' ) );
		$b = $wpdb->delete( self::table( 'practice_sessions' ), array( 'user_id' => $id ), array( '%d' ) );
		return array( 'items_removed' => $a > 0 || $b > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}