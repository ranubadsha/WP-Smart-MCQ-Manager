<?php
/**
 * Step 12: private read-only site health, verified schema upgrades, optional repair.
 * No authentication credentials, answer keys, or student records are exposed.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SMCQ_Health {
	const MIN_PHP = '7.4';
	const MIN_WP = '6.2';
	const LIMIT_ROWS = 250;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_smcq_repair_schema', array( __CLASS__, 'repair' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command( 'smcq doctor', array( __CLASS__, 'cli' ) );
		}
	}

	/** Table names are fixed plugin identifiers; never interpolate administrator input into SQL. */
	public static function table_specs() {
		return array(
			'attempts' => array( 'PRIMARY', 'active_key', 'user_exam', 'exam_status', 'user_status_id', 'exam_status_id', 'status_id', 'status_deadline' ),
			'bookmarks' => array( 'PRIMARY', 'user_question', 'question_id' ),
			'practice_sessions' => array( 'PRIMARY', 'active_key', 'user_status', 'user_mode', 'user_status_id' ),
			'security_events' => array( 'PRIMARY', 'exam_event', 'attempt_time', 'user_id', 'created_at' ),
			'security_leases' => array( 'PRIMARY', 'user_id', 'updated_at' ),
		);
	}

	/** Pure function for tests and older database versions. */
	public static function missing_index_names( $required, $actual ) {
		$actual = array_map( 'strtolower', array_map( 'strval', (array) $actual ) );
		return array_values( array_filter( (array) $required, static function ( $key ) use ( $actual ) {
			return ! in_array( strtolower( (string) $key ), $actual, true );
		} ) );
	}

	/** Return index names after quoting a plugin-owned identifier. */
	public static function index_names( $table ) {
		global $wpdb;
		if ( ! is_string( $table ) || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $table ) ) { return array(); }
		$rows = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ), ARRAY_A );
		if ( ! is_array( $rows ) ) { return array(); }
		return array_values( array_unique( array_filter( array_map( static function ( $row ) {
			return isset( $row['Key_name'] ) ? (string) $row['Key_name'] : '';
		}, $rows ) ) ) );
	}

	public static function has_indexes( $table, $required ) {
		return array() === self::missing_index_names( $required, self::index_names( $table ) );
	}

	public static function check( $code, $good, $name, $detail, $severity = 'error' ) {
		return array(
			'code' => (string) $code,
			'status' => $good ? 'pass' : ( 'warning' === $severity ? 'warning' : 'error' ),
			'name' => (string) $name,
			'detail' => (string) $detail,
		);
	}

	/** Runs only for administrators or the local WP-CLI operator; no external HTTP calls. */
	public static function checks() {
		global $wpdb, $wp_version;
		$checks = array();
		$checks[] = self::check( 'php', version_compare( PHP_VERSION, self::MIN_PHP, '>=' ), 'PHP version', 'PHP ' . PHP_VERSION . '; minimum ' . self::MIN_PHP );
		$checks[] = self::check( 'wordpress', version_compare( (string) $wp_version, self::MIN_WP, '>=' ), 'WordPress version', 'WordPress ' . (string) $wp_version . '; minimum ' . self::MIN_WP );
		$checks[] = self::check( 'zip', class_exists( 'ZipArchive' ), 'PHP ZIP', 'ZIP is required for XLSX import and export; CSV works without it.', 'warning' );
		$checks[] = self::check( 'xml', class_exists( 'SimpleXMLElement' ), 'PHP SimpleXML', 'SimpleXML is required for XLSX import; CSV works without it.', 'warning' );
		$checks[] = self::check( 'https', is_ssl(), 'HTTPS', 'Use HTTPS for student sessions, exam submissions, and paid checkout.', 'warning' );
		$checks[] = self::check( 'cron', (bool) wp_next_scheduled( 'smcq_security_cleanup' ), 'Security log cleanup', 'The security cleanup event must be scheduled; WP-Cron requires traffic or a real server cron.', 'warning' );
		$checks[] = self::check( 'attempt_schema', (string) get_option( 'smcq_attempt_schema' ) === SMCQ_Player::SCHEMA_VERSION, 'Attempt schema version', 'Database schema migration should match plugin version.' );
		$checks[] = self::check( 'practice_schema', (string) get_option( 'smcq_practice_schema' ) === SMCQ_Practice::SCHEMA, 'Practice schema version', 'Practice session indexes must be installed.' );
		$checks[] = self::check( 'security_schema', (string) get_option( 'smcq_security_schema' ) === SMCQ_Security::SCHEMA, 'Security schema version', 'Security audit tables should be present.' );
		foreach ( self::table_specs() as $suffix => $indexes ) {
			$table = $wpdb->prefix . 'smcq_' . $suffix;
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $found !== $table ) {
				$checks[] = self::check( 'table_' . $suffix, false, 'Table: ' . $suffix, 'Missing table. Make a database backup, then run schema repair.' );
				continue;
			}
			$missing = self::missing_index_names( $indexes, self::index_names( $table ) );
			$checks[] = self::check( 'table_' . $suffix, empty( $missing ), 'Table indexes: ' . $suffix, $missing ? 'Missing indexes: ' . implode( ', ', $missing ) : 'Required indexes present.' );
		}
		$checks[] = self::check( 'woo', SMCQ_Commerce::woocommerce_active(), 'WooCommerce (optional)', 'WooCommerce is needed only for premium exams; free exams work without it.', 'warning' );
		$checks[] = self::check( 'debug_display', ! ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ), 'Public error display', 'Disable WP_DEBUG_DISPLAY on live examination websites.', 'warning' );
		return $checks;
	}

	public static function summary( $rows ) {
		$counts = array( 'pass' => 0, 'warning' => 0, 'error' => 0 );
		foreach ( (array) $rows as $row ) {
			if ( isset( $row['status'] ) && isset( $counts[ $row['status'] ] ) ) { ++$counts[ $row['status'] ]; }
		}
		return $counts;
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=smcq_question', __( 'System Status', 'smart-mcq-manager' ), __( 'System Status', 'smart-mcq-manager' ), 'manage_options', 'smcq-system-status', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'smart-mcq-manager' ) ); }
		$rows = self::checks();
		$counts = self::summary( $rows );
		echo '<div class="wrap"><h1>' . esc_html__( 'Smart MCQ — System Status', 'smart-mcq-manager' ) . '</h1>';
		echo '<p>' . esc_html__( 'Private diagnostics. This screen does not inspect or export student answers, payment information, or secret keys. Changes to table indexes can take time on large sites.', 'smart-mcq-manager' ) . '</p>';
		if ( isset( $_GET['smcq_repaired'] ) && '1' === $_GET['smcq_repaired'] ) { echo '<div class="notice notice-success"><p>' . esc_html__( 'Schema repair attempted; check the diagnostics below for any remaining errors.', 'smart-mcq-manager' ) . '</p></div>'; }
		echo '<p><strong>' . esc_html( sprintf( '%d passed · %d warnings · %d errors', $counts['pass'], $counts['warning'], $counts['error'] ) ) . '</strong></p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Status', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Check', 'smart-mcq-manager' ) . '</th><th>' . esc_html__( 'Notes', 'smart-mcq-manager' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><strong>' . esc_html( strtoupper( $row['status'] ) ) . '</strong></td><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['detail'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Database Schema Repair', 'smart-mcq-manager' ) . '</h2><p>' . esc_html__( 'Back up the database and test on staging first. This action creates missing plugin tables and indexes without deleting existing student records. Large index rebuilds may lock tables on some hosts.', 'smart-mcq-manager' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="smcq_repair_schema" />';
		wp_nonce_field( 'smcq_repair_schema', 'smcq_repair_nonce' );
		echo '<button class="button button-secondary" type="submit">' . esc_html__( 'Repair plugin schema', 'smart-mcq-manager' ) . '</button></form></div>';
	}

	/** Explicit administrator action only. No external work and no destructive SQL. */
	public static function repair() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'smart-mcq-manager' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'smcq_repair_schema', 'smcq_repair_nonce' );
		SMCQ_Player::install();
		SMCQ_Practice::install();
		SMCQ_Security::install();
		wp_safe_redirect( add_query_arg( array( 'post_type' => 'smcq_question', 'page' => 'smcq-system-status', 'smcq_repaired' => '1' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/** Optional: wp smcq doctor --format=json; outputs no student data or secrets. */
	public static function cli( $args, $assoc_args ) {
		$rows = self::checks();
		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
			\WP_CLI::line( wp_json_encode( array( 'summary' => self::summary( $rows ), 'checks' => $rows ), JSON_PRETTY_PRINT ) );
		} else {
			foreach ( $rows as $row ) { \WP_CLI::line( strtoupper( $row['status'] ) . ' ' . $row['name'] . ': ' . $row['detail'] ); }
			$summary = self::summary( $rows );
			\WP_CLI::line( sprintf( '%d passed, %d warnings, %d errors', $summary['pass'], $summary['warning'], $summary['error'] ) );
		}
		if ( self::summary( $rows )['error'] > 0 ) { \WP_CLI::halt( 1 ); }
	}
}