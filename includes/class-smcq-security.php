<?php
/**
 * Step 10: optional exam event monitoring, single-tab leases, private admin audit.
 * Events are observations, NOT conclusive evidence of misconduct.
 * No IP address, user-agent, or raw client session token is stored.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SMCQ_Security {
	const SCHEMA = '1';
	const LEASE_SECONDS = 180;
	const MAX_EVENTS = 200;
	const RETENTION_DAYS = 30;
	const EVENT_TYPES = array( 'tab_hidden', 'tab_visible', 'fullscreen_exit', 'session_conflict', 'session_takeover', 'attempt_started', 'attempt_submitted', 'attempt_timeout' );

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_install' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_smcq_security_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'smcq_security_cleanup', array( __CLASS__, 'cleanup' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_export' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	private static function events_table() { global $wpdb; return $wpdb->prefix . 'smcq_security_events'; }
	private static function leases_table() { global $wpdb; return $wpdb->prefix . 'smcq_security_leases'; }

	public static function settings( $exam_id ) {
		return array(
			'monitor' => '1' === (string) get_post_meta( $exam_id, '_smcq_exam_monitor_activity', true ),
			'singleTab' => '1' === (string) get_post_meta( $exam_id, '_smcq_exam_single_tab', true ),
		);
	}

	public static function valid_client_id( $token ) {
		return is_string( $token ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $token );
	}

	public static function valid_event( $type ) {
		return is_string( $type ) && in_array( $type, self::EVENT_TYPES, true );
	}

	/** Pure helper for session state transitions; the database update below is compare-and-swap. */
	public static function lease_decision( $existing_hash, $existing_updated, $new_hash, $now, $takeover = false ) {
		if ( ! $existing_hash ) { return 'new'; }
		if ( hash_equals( (string) $existing_hash, $new_hash ) ) { return 'same'; }
		if ( $takeover ) { return 'takeover'; }
		if ( (int) $existing_updated + self::LEASE_SECONDS < (int) $now ) { return 'expired'; }
		return 'conflict';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$events = self::events_table();
		$leases = self::leases_table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempt_id bigint(20) unsigned NOT NULL,
			exam_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			event_type varchar(32) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY exam_event (exam_id,event_type),
			KEY attempt_time (attempt_id,created_at),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$leases} (
			attempt_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (attempt_id),
			KEY user_id (user_id),
			KEY updated_at (updated_at)
		) {$charset};" );
		$event_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $events ) ) );
		$lease_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $leases ) ) );
		if ( $event_exists === $events && $lease_exists === $leases ) {
			update_option( 'smcq_security_schema', self::SCHEMA, false );
		}
		self::maybe_schedule();
	}

	public static function maybe_install() {
		if ( get_option( 'smcq_security_schema' ) !== self::SCHEMA ) { self::install(); }
		self::maybe_schedule();
	}

	private static function maybe_schedule() {
		if ( ! wp_next_scheduled( 'smcq_security_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smcq_security_cleanup' );
		}
	}

	/** Cleanup may run late on low-traffic WordPress sites; data is retained at most until the next run. */
	public static function cleanup() {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::events_table() . ' WHERE created_at < %s', $cutoff ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::leases_table() . ' WHERE updated_at < %s', $cutoff ) );
	}

	/** Caller is responsible for checking authenticated attempt ownership. */
	public static function authorize( $row, $request, $force_takeover = false ) {
		if ( 'active' !== $row['status'] || ! self::settings( (int) $row['exam_id'] )['singleTab'] ) { return true; }
		global $wpdb;
		$token = $request->get_header( 'X-SMCQ-Client-Id' );
		if ( ! self::valid_client_id( $token ) ) {
			return new WP_Error( 'smcq_client_required', __( 'Reload this exam page to establish a session.', 'smart-mcq-manager' ), array( 'status' => 428 ) );
		}
		$hash = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
		$table = self::leases_table();
		$attempt = (int) $row['id'];
		$user = (int) $row['user_id'];
		$now = time();
		$now_sql = gmdate( 'Y-m-d H:i:s', $now );
		// UNIQUE attempt_id protects concurrent first claims across tabs.
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . $table . ' (attempt_id,user_id,token_hash,updated_at) VALUES (%d,%d,%s,%s)', $attempt, $user, $hash, $now_sql ) );
		$lease = $wpdb->get_row( $wpdb->prepare( 'SELECT token_hash, updated_at FROM ' . $table . ' WHERE attempt_id = %d', $attempt ), ARRAY_A );
		if ( ! $lease ) {
			return new WP_Error( 'smcq_session_unavailable', __( 'Could not validate this exam session. Try again.', 'smart-mcq-manager' ), array( 'status' => 503 ) );
		}
		$updated = strtotime( $lease['updated_at'] . ' UTC' );
		$decision = self::lease_decision( $lease['token_hash'], $updated, $hash, $now, $force_takeover );
		if ( 'same' === $decision || 'new' === $decision ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $table . ' SET updated_at = %s WHERE attempt_id = %d AND token_hash = %s', $now_sql, $attempt, $hash ) );
			return true;
		}
		if ( 'conflict' === $decision ) {
			self::record( $row, 'session_conflict' );
			return new WP_Error( 'smcq_session_conflict', __( 'This attempt is active in another tab or device. Choose Continue here to take over.', 'smart-mcq-manager' ), array( 'status' => 409, 'attemptId' => $attempt ) );
		}
		// Compare-and-swap prevents two concurrent takeovers from both succeeding.
		$swapped = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $table . ' SET token_hash = %s, updated_at = %s WHERE attempt_id = %d AND token_hash = %s AND updated_at = %s', $hash, $now_sql, $attempt, $lease['token_hash'], $lease['updated_at'] ) );
		if ( 1 !== $swapped ) {
			return new WP_Error( 'smcq_session_conflict', __( 'Another session changed. Retry to continue on this device.', 'smart-mcq-manager' ), array( 'status' => 409, 'attemptId' => $attempt ) );
		}
		self::record( $row, 'session_takeover' );
		return true;
	}

	public static function release( $attempt_id ) {
		global $wpdb;
		$wpdb->delete( self::leases_table(), array( 'attempt_id' => absint( $attempt_id ) ), array( '%d' ) );
	}

	/** Server-generated security signals are recorded only if the exam opted in. */
	public static function record( $row, $type ) {
		if ( ! self::valid_event( $type ) || ! self::settings( (int) $row['exam_id'] )['monitor'] ) { return false; }
		global $wpdb;
		$table = self::events_table();
		$attempt = (int) $row['id'];
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE attempt_id = %d', $attempt ) );
		if ( $count >= self::MAX_EVENTS ) { return false; }
		$last = $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM ' . $table . ' WHERE attempt_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1', $attempt, $type ) );
		if ( $last && time() - (int) strtotime( $last . ' UTC' ) < 5 ) { return false; }
		return (bool) $wpdb->insert( $table, array(
			'attempt_id' => $attempt, 'exam_id' => (int) $row['exam_id'], 'user_id' => (int) $row['user_id'],
			'event_type' => $type, 'created_at' => gmdate( 'Y-m-d H:i:s' ),
		), array( '%d', '%d', '%d', '%s', '%s' ) );
	}

	public static function routes() {
		$perm = static function () { return is_user_logged_in(); };
		register_rest_route( 'smcq/v1', '/attempts/(?P<id>\d+)/ping', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'ping' ), 'permission_callback' => $perm ) );
		register_rest_route( 'smcq/v1', '/attempts/(?P<id>\d+)/activity', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'activity' ), 'permission_callback' => $perm ) );
		register_rest_route( 'smcq/v1', '/attempts/(?P<id>\d+)/takeover', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'takeover' ), 'permission_callback' => $perm ) );
	}

	private static function owned_active( $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id,exam_id,user_id,status,deadline_at FROM ' . $wpdb->prefix . 'smcq_attempts WHERE id = %d AND user_id = %d', $id, get_current_user_id() ), ARRAY_A );
	}

	public static function ping( $request ) {
		$row = self::owned_active( $request );
		if ( ! $row || 'active' !== $row['status'] || strtotime( $row['deadline_at'] . ' UTC' ) <= time() ) {
			return new WP_Error( 'smcq_no_active_attempt', __( 'Active attempt not found.', 'smart-mcq-manager' ), array( 'status' => 404 ) );
		}
		$guard = self::authorize( $row, $request );
		if ( is_wp_error( $guard ) ) { return $guard; }
		return rest_ensure_response( array( 'active' => true, 'serverNow' => time() * 1000 ) );
	}

	public static function activity( $request ) {
		$row = self::owned_active( $request );
		if ( ! $row || 'active' !== $row['status'] || strtotime( $row['deadline_at'] . ' UTC' ) <= time() ) {
			return new WP_Error( 'smcq_no_active_attempt', __( 'Active attempt not found.', 'smart-mcq-manager' ), array( 'status' => 404 ) );
		}
		$guard = self::authorize( $row, $request );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$type = $request->get_param( 'event' );
		if ( ! in_array( $type, array( 'tab_hidden', 'tab_visible', 'fullscreen_exit' ), true ) ) {
			return new WP_Error( 'smcq_invalid_event', __( 'Invalid event type.', 'smart-mcq-manager' ), array( 'status' => 400 ) );
		}
		self::record( $row, $type );
		return rest_ensure_response( array( 'accepted' => true ) );
	}

	public static function takeover( $request ) {
		$row = self::owned_active( $request );
		if ( ! $row || 'active' !== $row['status'] || strtotime( $row['deadline_at'] . ' UTC' ) <= time() ) {
			return new WP_Error( 'smcq_no_active_attempt', __( 'Active attempt not found.', 'smart-mcq-manager' ), array( 'status' => 404 ) );
		}
		$guard = self::authorize( $row, $request, true );
		if ( is_wp_error( $guard ) ) { return $guard; }
		return rest_ensure_response( array( 'takenOver' => true ) );
	}

	public static function admin_menu() {
		add_submenu_page( 'edit.php?post_type=smcq_question', __( 'Exam Security', 'smart-mcq-manager' ), __( 'Exam Security', 'smart-mcq-manager' ), 'manage_options', 'smcq-security', array( __CLASS__, 'admin_page' ) );
	}

	private static function filter_values( $input ) {
		$exam = isset( $input['exam_id'] ) ? absint( $input['exam_id'] ) : 0;
		$user = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : 0;
		$type = isset( $input['event_type'] ) && is_scalar( $input['event_type'] ) ? sanitize_key( wp_unslash( $input['event_type'] ) ) : '';
		return array( 'exam_id' => $exam, 'user_id' => $user, 'event_type' => self::valid_event( $type ) ? $type : '' );
	}

	private static function find_events( $filters, $limit, $offset = 0 ) {
		global $wpdb;
		$where = array( '1=1' );
		$args = array();
		if ( $filters['exam_id'] ) { $where[] = 'exam_id = %d'; $args[] = $filters['exam_id']; }
		if ( $filters['user_id'] ) { $where[] = 'user_id = %d'; $args[] = $filters['user_id']; }
		if ( $filters['event_type'] ) { $where[] = 'event_type = %s'; $args[] = $filters['event_type']; }
		$args[] = $limit;
		$args[] = $offset;
		$sql = 'SELECT id,exam_id,user_id,attempt_id,event_type,created_at FROM ' . self::events_table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'smart-mcq-manager' ) ); }
		$filters = self::filter_values( $_GET );
		$page = isset( $_GET['paged'] ) ? max( 1, min( 200, absint( $_GET['paged'] ) ) ) : 1;
		$rows = self::find_events( $filters, 26, ( $page - 1 ) * 25 );
		$has_more = count( $rows ) > 25;
		$base_url = admin_url( 'edit.php?post_type=smcq_question&page=smcq-security' );
		$filter_query = array_filter( $filters );
		echo '<div class="wrap"><h1>' . esc_html__( 'MCQ Exam Security Events', 'smart-mcq-manager' ) . '</h1>';
		echo '<p>' . esc_html__( 'Visibility events and session conflicts are warning signals, not proof of cheating. Monitoring is optional for each exam. Logs exclude IP addresses and automatically expire after 30 days.', 'smart-mcq-manager' ) . '</p>';
		echo '<form method="get"><input type="hidden" name="post_type" value="smcq_question"><input type="hidden" name="page" value="smcq-security">';
		foreach ( array( 'exam_id' => 'Exam ID', 'user_id' => 'Student user ID' ) as $key => $label ) {
			echo '<label style="display:inline-block;margin:0 12px 12px 0">' . esc_html( $label ) . ' <input type="number" min="0" name="' . esc_attr( $key ) . '" value="' . esc_attr( $filters[ $key ] ) . '"></label>';
		}
		echo '<label>' . esc_html__( 'Event', 'smart-mcq-manager' ) . ' <select name="event_type"><option value="">All events</option>';
		foreach ( self::EVENT_TYPES as $type ) { echo '<option value="' . esc_attr( $type ) . '" ' . selected( $filters['event_type'], $type, false ) . '>' . esc_html( str_replace( '_', ' ', $type ) ) . '</option>'; }
		echo '</select></label> <button class="button button-primary" type="submit">' . esc_html__( 'Apply filters', 'smart-mcq-manager' ) . '</button></form>';
		$export_url = add_query_arg( array_merge( $filter_query, array( 'action' => 'smcq_security_csv' ) ), admin_url( 'admin-post.php' ) );
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( $export_url, 'smcq_security_csv' ) ) . '">' . esc_html__( 'Export CSV (max 5000 events)', 'smart-mcq-manager' ) . '</a></p>';
		echo '<table class="widefat striped"><thead><tr><th>UTC Time</th><th>Exam ID</th><th>Student ID</th><th>Attempt ID</th><th>Event</th></tr></thead><tbody>';
		foreach ( array_slice( $rows, 0, 25 ) as $r ) {
			echo '<tr><td>' . esc_html( $r['created_at'] ) . '</td><td>' . esc_html( $r['exam_id'] ) . '</td><td>' . esc_html( $r['user_id'] ) . '</td><td>' . esc_html( $r['attempt_id'] ) . '</td><td>' . esc_html( str_replace( '_', ' ', $r['event_type'] ) ) . '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="5">' . esc_html__( 'No events found.', 'smart-mcq-manager' ) . '</td></tr>'; }
		echo '</tbody></table><p>';
		if ( $page > 1 ) { echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $filter_query, array( 'paged' => $page - 1 ) ), $base_url ) ) . '">Previous</a> '; }
		if ( $has_more ) { echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $filter_query, array( 'paged' => $page + 1 ) ), $base_url ) ) . '">Next</a>'; }
		echo '</p></div>';
	}

	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'smart-mcq-manager' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'smcq_security_csv' );
		$rows = self::find_events( self::filter_values( $_GET ), 5000 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="smcq-security-events.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Time (UTC)', 'Exam ID', 'Student ID', 'Attempt ID', 'Event' ) );
		foreach ( $rows as $r ) { fputcsv( $out, array( $r['created_at'], (int) $r['exam_id'], (int) $r['user_id'], (int) $r['attempt_id'], $r['event_type'] ) ); }
		fclose( $out );
		exit;
	}

	public static function register_export( $exporters ) {
		$exporters['smcq-security'] = array( 'exporter_friendly_name' => __( 'Smart MCQ Security Events', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'privacy_export' ) );
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['smcq-security'] = array( 'eraser_friendly_name' => __( 'Smart MCQ Security Events', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'privacy_erase' ) );
		return $erasers;
	}

	public static function privacy_export( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$page = max( 1, (int) $page );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,exam_id,attempt_id,event_type,created_at FROM ' . self::events_table() . ' WHERE user_id = %d ORDER BY id ASC LIMIT 26 OFFSET %d', $user->ID, ( $page - 1 ) * 25 ), ARRAY_A );
		$data = array();
		foreach ( array_slice( $rows, 0, 25 ) as $row ) {
			$data[] = array( 'group_id' => 'smcq-security', 'group_label' => 'Exam Security Events', 'item_id' => 'smcq-event-' . $row['id'], 'data' => array(
				array( 'name' => 'Exam ID', 'value' => $row['exam_id'] ), array( 'name' => 'Attempt ID', 'value' => $row['attempt_id'] ),
				array( 'name' => 'Event', 'value' => $row['event_type'] ), array( 'name' => 'Time (UTC)', 'value' => $row['created_at'] ),
			) );
		}
		return array( 'data' => $data, 'done' => count( $rows ) <= 25 );
	}

	public static function privacy_erase( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$a = $wpdb->delete( self::events_table(), array( 'user_id' => $user->ID ), array( '%d' ) );
		$b = $wpdb->delete( self::leases_table(), array( 'user_id' => $user->ID ), array( '%d' ) );
		return array( 'items_removed' => ( $a > 0 || $b > 0 ), 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}