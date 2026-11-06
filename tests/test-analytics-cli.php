<?php
/** Pure analytics + leaderboard tests; WordPress is not required. */
define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__ ) . '/includes/class-smcq-question-types.php';
require_once dirname( __DIR__ ) . '/includes/class-smcq-results.php';
require_once dirname( __DIR__ ) . '/includes/class-smcq-analytics.php';
function check( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . ' expected ' . var_export( $expected, true ) . ', actual ' . var_export( $actual, true ) );
	}
}
function make_row( $uid, $id, $given, $wrong, $submitted, $category = true ) {
	$q = array(
		'id' => 23, 'title' => 'বাংলা প্রশ্ন', 'correct' => 'A', 'points' => 1,
		'negative' => 0.25, 'options' => array( 'A' => 'হ্যাঁ', 'B' => 'না' ),
	);
	if ( $category ) { $q['subjects'] = array( 'বাংলা' ); $q['topics'] = array( 'ব্যাকরণ', 'বাংলা' ); }
	$snapshot = array( 'policy' => array( 'pass_percent' => 50 ), 'questions' => array( $q ) );
	$answers = array( '23' => $given );
	return array(
		'id' => $id, 'user_id' => $uid, 'exam_id' => 80,
		'started_at' => '2026-11-06 10:00:00', 'submitted_at' => $submitted,
		'snapshot' => json_encode( $snapshot ), 'answers' => json_encode( $answers ),
		'result_json' => $wrong ? null : json_encode( SMCQ_Results::grade( $snapshot, $answers ) ),
	);
}
$rows = array(
	make_row( 7, 9, 'A', false, '2026-11-06 10:05:00' ),
	make_row( 7, 8, 'B', true, '2026-11-06 10:06:00' ),
	make_row( 8, 7, null, true, '2026-11-06 10:04:00', false ),
);
$sum = SMCQ_Analytics::summarize( $rows );
check( 3, $sum['gradedAttempts'], 'attempts' );
check( 1, $sum['passedAttempts'], 'passes' );
check( 1, $sum['correct'], 'correct' );
check( 1, $sum['wrong'], 'wrong' );
check( 1, $sum['unanswered'], 'skipped' );
check( 1, $sum['subjects'][0]['correct'], 'subject correct count' );
check( 2, $sum['subjects'][0]['attempted'], 'subject answers counted' );
check( 50.0, $sum['subjects'][0]['accuracy'], 'subject accuracy' );
check( true, $sum['subjects'][0]['lowSample'], 'not reliable below 3' );
check( 2, $sum['topics'][0]['questions'], 'topic question counts' );
check( 7, $sum['trend'][0]['attemptId'], 'trend chronological across supplied newest-first rows' );
$rank = SMCQ_Analytics::rank( $rows, 10 );
check( 2, count( $rank ), 'best attempt per participant' );
check( 7, $rank[0]['userId'], 'best score first' );
check( 100.0, $rank[0]['percentage'], 'best attempt selected' );
check( 8, $rank[1]['userId'], 'unanswered attempt in ranking' );
check( SMCQ_Analytics::anonymous_label( 80, 7, 'secret' ), SMCQ_Analytics::anonymous_label( 80, 7, 'secret' ), 'pseudonym stable' );
check( false, SMCQ_Analytics::anonymous_label( 80, 7, 'secret' ) === SMCQ_Analytics::anonymous_label( 81, 7, 'secret' ), 'exam scoped pseudonyms' );
$legacy = SMCQ_Analytics::summarize( array( $rows[2] ) );
check( 0, count( $legacy['topics'] ), 'legacy snapshots cannot be retroactively categorized' );
$bad = $rows[0];
$bad['snapshot'] = '{}'; $bad['result_json'] = null;
check( 0, SMCQ_Analytics::summarize( array( $bad ) )['gradedAttempts'], 'skip corrupted frozen attempt' );
check( false, SMCQ_Analytics::leaderboard_visible( 0, 100, 101 ), 'opt-out hidden' );
check( false, SMCQ_Analytics::leaderboard_visible( 1, null, 101 ), 'missing schedule hidden' );
check( false, SMCQ_Analytics::leaderboard_visible( 1, 200, 101 ), 'before exam close hidden' );
check( true, SMCQ_Analytics::leaderboard_visible( 1, 100, 100 ), 'available at close' );
echo "Step 7 analytics and leaderboard tests passed.\n";