<?php
/** Pure question-type, answer canonicalization and historical scoring regression tests. */
define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__ ) . '/includes/class-smcq-question-types.php';
require_once dirname( __DIR__ ) . '/includes/class-smcq-results.php';
function yes( $expected, $actual, $label ) { if ( $expected !== $actual ) { throw new RuntimeException( "$label: expected " . var_export( $expected, true ) . ' got ' . var_export( $actual, true ) ); } }
$options = array( 'A' => 'Alpha', 'B' => 'Beta', 'C' => 'Gamma', 'D' => 'Delta' );
yes( 'single', SMCQ_Question_Types::type( '' ), 'legacy fallback' );
yes( true, SMCQ_Question_Types::ready( 'single', $options, 'A' ), 'legacy ready' );
yes( true, SMCQ_Question_Types::ready( 'true_false', array( 'A' => 'True', 'B' => 'False' ), 'B' ), 'two options are enough' );
yes( false, SMCQ_Question_Types::ready( 'true_false', array( 'A' => 'True' ), 'A' ), 'missing false option' );
yes( 'A,C', SMCQ_Question_Types::canonical( 'C,A', 'multiple', $options ), 'unordered input canonical' );
yes( null, SMCQ_Question_Types::canonical( 'A,A', 'multiple', $options ), 'reject duplicates' );
yes( null, SMCQ_Question_Types::canonical( 'A,E', 'multiple', $options ), 'reject bad keys' );
yes( null, SMCQ_Question_Types::canonical( 'A,C', 'single', $options ), 'single rejects multiple' );
yes( false, SMCQ_Question_Types::ready( 'multiple', $options, 'A' ), 'multiple must have two keys' );
yes( true, SMCQ_Question_Types::ready( 'multiple', $options, 'A,C' ), 'multiple answer ready' );
yes( false, SMCQ_Question_Types::ready( 'passage', $options, 'A' ), 'passage required' );
yes( true, SMCQ_Question_Types::ready( 'passage', $options, 'A', 'The text.' ), 'passage supplied' );
yes( false, SMCQ_Question_Types::ready( 'image', $options, 'A', '', '' ), 'image required' );
$snapshot = array( 'policy' => array( 'pass_percent' => 60 ), 'questions' => array(
  array( 'id' => 1, 'type' => 'multiple', 'title' => 'Pick two', 'options' => $options, 'order' => array('C','D','A','B'), 'correct' => 'A,C', 'points' => 2, 'negative' => 0.5 ),
  array( 'id' => 2, 'type' => 'true_false', 'title' => 'T/F', 'options' => array('A'=>'True','B'=>'False'), 'order' => array('B','A'), 'correct' => 'B', 'points' => 1, 'negative' => 0.25 ),
  array( 'id' => 3, 'title' => 'Legacy', 'options' => $options, 'order' => array('A','B','C','D'), 'correct'=>'A', 'points'=>1, 'negative'=>0.5 ),
) );
$grade = SMCQ_Results::grade( $snapshot, array('1'=>'C,A','2'=>'A','3'=>'A') );
yes( 2, $grade['correct'], 'multi and old single correct' );
yes( 1, $grade['wrong'], 'wrong counted' );
yes( 2.75, $grade['score'], 'negative mark respected' );
$review = SMCQ_Results::review( $snapshot, array('1'=>'C,A','2'=>'A','3'=>'A') );
yes( 'A,C', $review[0]['yourAnswer'], 'answer stored canonical for review' );
yes( 'A,C', $review[0]['correctAnswer'], 'correct keys not reduced' );
yes( 'correct', $review[0]['status'], 'shuffled multiple review' );
yes( 'wrong', $review[1]['status'], 'T/F grading' );
yes( 'single', $review[2]['type'], 'legacy version fallback' );
echo "Step 13 question type and grading tests passed.\n";

require_once dirname( __DIR__ ) . '/includes/class-smcq-question-reports.php';
yes( true, SMCQ_Question_Reports::validate( 3, 'incorrect_key', 'The correct key is inconsistent.' ), 'issue report validation' );
yes( false, SMCQ_Question_Reports::validate( 3, 'unknown', 'The correct key is inconsistent.' ), 'unknown issue blocked' );
yes( false, SMCQ_Question_Reports::validate( 3, 'incorrect_key', 'short' ), 'short issue blocked' );

require_once dirname( __DIR__ ) . '/includes/class-smcq-certificates.php';
yes( true, SMCQ_Certificates::eligible( array( 'status' => 'submitted', 'result_json' => '{"passed":true}' ) ), 'passed attempt qualifies for certificate' );
yes( false, SMCQ_Certificates::eligible( array( 'status' => 'active', 'result_json' => '{"passed":true}' ) ), 'active attempt never gets certificate' );
yes( false, SMCQ_Certificates::eligible( array( 'status' => 'submitted', 'result_json' => '{"passed":false}' ) ), 'failed attempt never gets certificate' );