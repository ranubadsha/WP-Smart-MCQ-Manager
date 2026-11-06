<?php
/** Pure grading and answer-key review tests, runnable without WordPress. */
define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__ ) . '/includes/class-smcq-question-types.php';
require_once dirname( __DIR__ ) . '/includes/class-smcq-results.php';
function check( $want, $got, $test ) {
	if ( $want !== $got ) {
		throw new RuntimeException( $test . ': expected ' . var_export( $want, true ) . ', got ' . var_export( $got, true ) );
	}
}
$snapshot = array(
	'policy' => array( 'pass_percent' => 65 ),
	'questions' => array(
		array( 'id' => 100, 'title' => 'বাংলাদেশের রাজধানী?', 'options' => array( 'A' => 'ঢাকা', 'B' => 'চট্টগ্রাম', 'C' => 'সিলেট', 'D' => 'খুলনা' ), 'order' => array( 'D', 'B', 'A', 'C' ), 'correct' => 'A', 'points' => 2, 'negative' => 0.5, 'explanation' => 'ঢাকা বাংলাদেশের রাজধানী।' ),
		array( 'id' => 101, 'title' => 'Which is even?', 'options' => array( 'A' => '1', 'B' => '2', 'C' => '3', 'D' => '5' ), 'order' => array( 'A', 'B', 'C', 'D' ), 'correct' => 'B', 'points' => 1, 'negative' => 0.25 ),
		array( 'id' => 102, 'title' => 'Unanswered?', 'options' => array( 'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D' ), 'order' => array( 'B', 'A', 'C', 'D' ), 'correct' => 'C', 'points' => 2, 'negative' => 0.5 ),
	),
);
$answers = array( '100' => 'A', '101' => 'C', '102' => null );
$grade = SMCQ_Results::grade( $snapshot, $answers );
check( 3, $grade['totalQuestions'], 'total' );
check( 2, $grade['attempted'], 'attempted' );
check( 1, $grade['correct'], 'correct' );
check( 1, $grade['wrong'], 'wrong' );
check( 1, $grade['unanswered'], 'unanswered' );
check( 5.0, $grade['maxMarks'], 'maximum marks' );
check( 2.0, $grade['positiveMarks'], 'positive marks' );
check( 0.25, $grade['negativeMarks'], 'negative marks' );
check( 1.75, $grade['score'], 'net score' );
check( 35.0, $grade['percentage'], 'score percentage' );
check( 50.0, $grade['accuracy'], 'accuracy excludes skipped' );
check( false, $grade['passed'], 'pass/fail false' );
$review = SMCQ_Results::review( $snapshot, $answers );
check( 'A', $review[0]['correctAnswer'], 'correct answer uses internal key, not display order' );
check( 'A', $review[0]['options'][2]['key'], 'frozen shuffled option order' );
check( 'ঢাকা', $review[0]['options'][2]['text'], 'Bangla answer retained' );
check( 'ঢাকা বাংলাদেশের রাজধানী।', $review[0]['explanation'], 'frozen explanation retained' );
check( 'wrong', $review[1]['status'], 'wrong answer in review' );
check( -0.25, $review[1]['earnedMarks'], 'wrong penalty in review' );
check( 'unanswered', $review[2]['status'], 'skipped answer in review' );
check( 0.0, $review[2]['earnedMarks'], 'skipped penalty' );
$passing = $snapshot;
$passing['policy']['pass_percent'] = 35;
check( true, SMCQ_Results::grade( $passing, $answers )['passed'], 'threshold inclusive' );
$allwrong = SMCQ_Results::grade( $snapshot, array( '100' => 'B', '101' => 'A', '102' => 'D' ) );
check( -1.25, $allwrong['score'], 'negative total allowed' );
check( false, $allwrong['passed'], 'negative score fails' );
$empty = SMCQ_Results::grade( array( 'questions' => array() ), array() );
check( false, $empty['passed'], 'empty exams do not pass' );
check( 0.0, $empty['accuracy'], 'empty exams safe' );
$legacy = $snapshot;
unset( $legacy['questions'][0]['explanation'] );
check( '', SMCQ_Results::review( $legacy, $answers )[0]['explanation'], 'Step 5 attempts without explanation supported' );
$invalid = SMCQ_Results::grade( $snapshot, array( '100' => 'E' ) );
check( 3, $invalid['unanswered'], 'invalid stored answer does not score' );
echo "Step 6 grading and review tests passed.\n";