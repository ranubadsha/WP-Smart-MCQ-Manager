<?php
/** Pure Step 8 snapshot-selection and progressive-feedback checks; WordPress not required. */
define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__ ) . '/includes/class-smcq-results.php';
require_once dirname( __DIR__ ) . '/includes/class-smcq-player.php';
require_once dirname( __DIR__ ) . '/includes/class-smcq-practice.php';
function assert_same( $expected, $actual, $name ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $name . ' expected ' . var_export( $expected, true ) . ' got ' . var_export( $actual, true ) );
	}
}
function record( $answers, $topic = 'Preposition' ) {
	$snapshot = array( 'questions' => array(
		array( 'id' => 11, 'title' => 'He goes', 'correct' => 'A', 'options' => array( 'A' => 'yes', 'B' => 'no' ), 'order' => array( 'A', 'B' ), 'topics' => array( $topic ) ),
		array( 'id' => 12, 'title' => 'He went', 'correct' => 'A', 'options' => array( 'A' => 'yes', 'B' => 'no' ), 'order' => array( 'A', 'B' ), 'topics' => array( $topic ) ),
	) );
	return array( 'snapshot' => json_encode( $snapshot ), 'answers' => json_encode( $answers ) );
}
// Newest-first records. Correcting a formerly wrong question removes it from wrong-answer practice.
$history = SMCQ_Practice::analyze_history( array(
	record( array( '11' => 'A', '12' => 'B' ) ),
	record( array( '11' => 'B', '12' => 'B' ) ),
	record( array( '11' => null, '12' => 'B' ) ),
) );
assert_same( array( 12 ), $history['wrongIds'], 'latest answered status' );
assert_same( 1, count( $history['weakTopics'] ), 'weak topic count' );
assert_same( 20.0, $history['weakTopics'][0]['accuracy'], 'weak topic 1 correct out of 5' );
assert_same( 'Preposition', $history['weakTopics'][0]['name'], 'topic name retained' );
assert_same( array( 12, 3, 4 ), SMCQ_Practice::combine_pool( array( 12 ), array( 12, 3 ), array( 3, 4 ), 'mixed', 10 ), 'deduplicated revision pool' );
assert_same( array( 12 ), SMCQ_Practice::combine_pool( array( 12, 3 ), array( 4 ), array( 5 ), 'wrong', 1 ), 'bounded wrong-only mode' );
assert_same( array( 4 ), SMCQ_Practice::combine_pool( array( 12 ), array( 3 ), array( 4 ), 'weak', 5 ), 'weak-only mode' );
assert_same( array( 3 ), SMCQ_Practice::combine_pool( array( 12 ), array( 3 ), array( 4 ), 'bookmarks', 5 ), 'bookmark-only mode' );
$single = array( 'questions' => array(
	array( 'id' => 99, 'title' => 'বাংলা প্রশ্ন', 'options' => array( 'A' => 'ঢাকা', 'B' => 'খুলনা' ), 'order' => array( 'B', 'A' ), 'correct' => 'A', 'points' => 1, 'negative' => 0, 'explanation' => 'ঢাকাই সঠিক।' ),
	array( 'id' => 100, 'title' => 'Another', 'options' => array( 'A' => 'True', 'B' => 'False' ), 'order' => array( 'A', 'B' ), 'correct' => 'B', 'points' => 1, 'negative' => 0, 'explanation' => 'The answer is B.' ),
) );
$active = SMCQ_Practice::feedback( $single, array( '99' => 'B' ) );
assert_same( 1, count( $active ), 'only answered questions receive feedback' );
assert_same( 'A', $active['99']['correctAnswer'], 'answer exposed after submitting choice' );
assert_same( 'ঢাকাই সঠিক।', $active['99']['explanation'], 'Bangla explanation preserved' );
assert_same( false, isset( $active['100'] ), 'unanswered answer key withheld' );
$completed = SMCQ_Practice::feedback( $single, array( '99' => 'B' ), true );
assert_same( 2, count( $completed ), 'completed sessions can review all answers' );
$public = SMCQ_Player::public_questions( $single );
assert_same( false, array_key_exists( 'correct', $public[0] ), 'correct key never in question payload' );
assert_same( false, array_key_exists( 'explanation', $public[0] ), 'explanation withheld until answer' );
assert_same( 'B', $public[0]['options'][0]['key'], 'shuffled option order remains stable' );
echo "Step 8 practice selection, privacy and feedback tests passed.\n";