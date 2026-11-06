<?php
/** Standalone tests of the safety-critical pure helpers, without WordPress bootstrapping. */
define( 'ABSPATH', __DIR__ . '/' );
function wp_timezone() { return new DateTimeZone( 'Asia/Dhaka' ); }
require_once dirname( __DIR__ ) . '/includes/class-smcq-player.php';
function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}
$start = strtotime( '2026-11-06T10:00:00+00:00' );
assert_same( $start + 60 * 60, SMCQ_Player::deadline_timestamp( $start, 60 ), 'duration-only deadline' );
assert_same( $start + 45 * 60, SMCQ_Player::deadline_timestamp( $start, 60, $start + 45 * 60 ), 'earlier global closing' );
assert_same( $start + 60, SMCQ_Player::deadline_timestamp( $start, 0 ), 'minimum duration' );
assert_same( $start + 600 * 60, SMCQ_Player::deadline_timestamp( $start, 1000 ), 'maximum duration' );
assert_same( strtotime( '2026-11-06 10:00:00 UTC' ), SMCQ_Player::schedule_timestamp( '2026-11-06T16:00' ), 'site timezone conversion' );
assert_same( null, SMCQ_Player::schedule_timestamp( '2026-19-99T16:00' ), 'invalid date rejected' );
$snapshot = array( 'questions' => array(
	array(
		'id' => 22, 'title' => 'বাংলাদেশের রাজধানী?',
		'options' => array( 'A' => 'ঢাকা', 'B' => 'খুলনা', 'C' => 'সিলেট', 'D' => 'রাজশাহী' ),
		'correct' => 'A', 'points' => 1, 'negative' => 0.25,
		'order' => array( 'D', 'A', 'C', 'B' ),
	),
) );
$public = SMCQ_Player::public_questions( $snapshot );
$encoded = json_encode( $public );
assert_same( false, strpos( $encoded, 'correct' ) !== false, 'do not send correct keys' );
assert_same( false, strpos( $encoded, 'negative' ) !== false, 'do not send negative marks' );
assert_same( false, strpos( $encoded, 'points' ) !== false, 'do not send marks' );
assert_same( 'D', $public[0]['options'][0]['key'], 'shuffled option selection maps to stable internal key' );
assert_same( 'A', $public[0]['options'][1]['key'], 'correct option not revealed by order' );
assert_same( true, SMCQ_Player::valid_answer( $snapshot, 22, 'A' ), 'valid choice' );
assert_same( true, SMCQ_Player::valid_answer( $snapshot, 22, null ), 'clear choice' );
assert_same( false, SMCQ_Player::valid_answer( $snapshot, 23, 'A' ), 'out-of-exam question rejected' );
assert_same( false, SMCQ_Player::valid_answer( $snapshot, 22, 'E' ), 'invalid choice rejected' );
assert_same( false, SMCQ_Player::valid_answer( $snapshot, '22', 'A' ), 'numeric string rejected' );
echo "Step 5 player helper tests passed.\n";