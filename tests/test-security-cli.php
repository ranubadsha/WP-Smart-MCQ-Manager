<?php
/** Step 10: pure security session helper tests (no running WordPress needed). */
define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__ ) . '/includes/class-smcq-security.php';
function assert_security( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { throw new RuntimeException( $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) ); }
}
assert_security( true, SMCQ_Security::valid_client_id( str_repeat( 'a3', 16 ) ), 'valid 16-byte hex identifier' );
assert_security( false, SMCQ_Security::valid_client_id( str_repeat( 'aa', 15 ) ), 'short client ID rejected' );
assert_security( false, SMCQ_Security::valid_client_id( str_repeat( 'AA', 16 ) ), 'uppercase client ID rejected' );
assert_security( false, SMCQ_Security::valid_client_id( str_repeat( 'aa', 16 ) . '\n' ), 'invalid client ID suffix rejected' );
assert_security( false, SMCQ_Security::valid_client_id( array( 'bad' ) ), 'array client ID rejected' );
assert_security( true, SMCQ_Security::valid_event( 'tab_hidden' ), 'allowed student activity' );
assert_security( true, SMCQ_Security::valid_event( 'attempt_submitted' ), 'allowed server event' );
assert_security( false, SMCQ_Security::valid_event( 'arbitrary' ), 'event allowlist' );
assert_security( false, SMCQ_Security::valid_event( array( 'tab_hidden' ) ), 'array event rejected' );
$hash_a = hash( 'sha256', 'session a' );
$hash_b = hash( 'sha256', 'session b' );
assert_security( 'new', SMCQ_Security::lease_decision( '', 0, $hash_a, 1000 ), 'first claim' );
assert_security( 'same', SMCQ_Security::lease_decision( $hash_a, 1000, $hash_a, 1002 ), 'owner continues' );
assert_security( 'conflict', SMCQ_Security::lease_decision( $hash_a, 1000, $hash_b, 1005 ), 'second tab blocked' );
assert_security( 'conflict', SMCQ_Security::lease_decision( $hash_a, 1000, $hash_b, 1180 ), 'lease threshold still locked' );
assert_security( 'expired', SMCQ_Security::lease_decision( $hash_a, 1000, $hash_b, 1181 ), 'stale lease reclaim' );
assert_security( 'takeover', SMCQ_Security::lease_decision( $hash_a, 1000, $hash_b, 1005, true ), 'explicit authorized takeover' );
echo "Step 10 security helper tests passed.\n";