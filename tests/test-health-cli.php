<?php
/** Isolated Step 12 schema and health-check tests; no WordPress installation required. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
require_once __DIR__ . '/../includes/class-smcq-health.php';

function ensure( $value, $why ) { if ( ! $value ) { fwrite( STDERR, "FAILED: {$why}\n" ); exit( 1 ); } }
function wp_next_scheduled( $name ) { return 'smcq_security_cleanup' === $name ? 123456 : false; }
function get_option( $name ) { return array( 'smcq_attempt_schema' => '3', 'smcq_practice_schema' => '2', 'smcq_security_schema' => '1' )[ $name ] ?? ''; }
function is_ssl() { return true; }
class SMCQ_Player { const SCHEMA_VERSION = '3'; }
class SMCQ_Practice { const SCHEMA = '2'; }
class SMCQ_Security { const SCHEMA = '1'; }
class SMCQ_Commerce { public static function woocommerce_active() { return false; } }

final class Fakewpdb {
    public $prefix = 'wp_';
    public $rows = array();
    public function esc_like( $s ) { return $s; }
    public function prepare( $sql, $arg ) { return str_replace( '%s', "'" . $arg . "'", $sql ); }
    public function get_var( $sql ) {
        if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $sql, $parts ) ) {
            return isset( $this->rows[ $parts[1] ] ) ? $parts[1] : null;
        }
        return null;
    }
    public function get_results( $sql, $format = null ) {
        if ( ! preg_match( '/SHOW INDEX FROM `([a-zA-Z0-9_]+)`/', $sql, $m ) ) { return false; }
        return array_map( static function ( $n ) { return array( 'Key_name' => $n ); }, $this->rows[ $m[1] ] ?? array() );
    }
}
$wpdb = new Fakewpdb();
$wp_version = '6.8';
ensure( SMCQ_Health::missing_index_names( array( 'PRIMARY', 'foo' ), array( 'primary', 'FOO' ) ) === array(), 'case-insensitive index matching' );
ensure( SMCQ_Health::missing_index_names( array( 'a', 'b' ), array( 'a' ) ) === array( 'b' ), 'missing index' );
ensure( SMCQ_Health::summary( array( SMCQ_Health::check( 'x', true, '', '' ), SMCQ_Health::check( 'y', false, '', '' ), SMCQ_Health::check( 'z', false, '', '', 'warning' ) ) ) === array( 'pass' => 1, 'warning' => 1, 'error' => 1 ), 'status summary' );

foreach ( SMCQ_Health::table_specs() as $suffix => $indexes ) { $wpdb->rows[ 'wp_smcq_' . $suffix ] = $indexes; }
ensure( SMCQ_Health::has_indexes( 'wp_smcq_attempts', array( 'user_status_id', 'status_id' ) ), 'has index lookup' );
ensure( ! SMCQ_Health::has_indexes( 'wp_smcq_attempts; DROP TABLE users', array( 'status_id' ) ), 'reject unsafe table name' );

$checks = SMCQ_Health::checks();
$summary = SMCQ_Health::summary( $checks );
ensure( $summary['error'] === 0, 'all required indexes present' );
ensure( $summary['warning'] >= 1, 'WooCommerce optional warning instead of error' );
$wpdb->rows['wp_smcq_attempts'] = array( 'PRIMARY', 'user_exam' );
$checks = SMCQ_Health::checks();
$index_error = array_values( array_filter( $checks, static function ( $row ) { return 'table_attempts' === $row['code'] && 'error' === $row['status']; } ) );
ensure( count( $index_error ) === 1 && strpos( $index_error[0]['detail'], 'user_status_id' ) !== false, 'missing indexes flagged' );
unset( $wpdb->rows['wp_smcq_bookmarks'] );
$checks = SMCQ_Health::checks();
ensure( count( array_filter( $checks, static function ( $row ) { return 'table_bookmarks' === $row['code'] && 'error' === $row['status']; } ) ) === 1, 'missing table flagged' );

$player = file_get_contents( __DIR__ . '/../includes/class-smcq-player.php' );
$practice = file_get_contents( __DIR__ . '/../includes/class-smcq-practice.php' );
ensure( strpos( $player, "SCHEMA_VERSION = '3'" ) !== false, 'attempt migration version' );
ensure( strpos( $practice, "SCHEMA = '2'" ) !== false, 'practice migration version' );
foreach ( array( 'user_status_id', 'exam_status_id', 'status_id', 'status_deadline' ) as $key ) { ensure( strpos( $player, 'KEY ' . $key . ' (' ) !== false, 'attempt index ' . $key ); }
ensure( strpos( $practice, 'KEY user_status_id (user_id,status,id)' ) !== false, 'practice index' );
ensure( strpos( file_get_contents( __DIR__ . '/../smart-mcq-manager.php' ), 'Version: 1.2.0' ) !== false, 'plugin release version' );
echo "Step 12 diagnostics and schema tests passed.\n";