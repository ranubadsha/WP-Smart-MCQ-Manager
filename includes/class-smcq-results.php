<?php
/**
 * Server-side grading of frozen attempt snapshots. No WordPress calls in these helpers.
 * Scores and explanations are never provided to an active student attempt.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SMCQ_Results {
	/** Score a saved attempt deterministically from its frozen question and marking data. */
	public static function grade( $snapshot, $answers ) {
		$questions = isset( $snapshot['questions'] ) && is_array( $snapshot['questions'] ) ? $snapshot['questions'] : array();
		$answers = is_array( $answers ) ? $answers : array();
		$correct = 0;
		$wrong = 0;
		$unanswered = 0;
		$positive = 0.0;
		$deductions = 0.0;
		$max_marks = 0.0;
		foreach ( $questions as $question ) {
			$id = isset( $question['id'] ) ? (string) $question['id'] : '';
			$points = self::safe_mark( isset( $question['points'] ) ? $question['points'] : 1, 1 );
			$penalty = self::safe_mark( isset( $question['negative'] ) ? $question['negative'] : 0, 0 );
			$max_marks += $points;
			$choice = isset( $answers[ $id ] ) ? $answers[ $id ] : null;
			$correct_key = isset( $question['correct'] ) ? (string) $question['correct'] : '';
			$options = isset( $question['options'] ) && is_array( $question['options'] ) ? $question['options'] : array();
			$type = SMCQ_Question_Types::type( isset( $question['type'] ) ? $question['type'] : 'single' );
			$choice = SMCQ_Question_Types::canonical( $choice, $type, $options );
			if ( null === $choice ) {
				++$unanswered;
			} elseif ( hash_equals( $correct_key, $choice ) ) {
				++$correct;
				$positive += $points;
			} else {
				++$wrong;
				$deductions += $penalty;
			}
		}
		$total = count( $questions );
		$attempted = $correct + $wrong;
		$score = round( $positive - $deductions, 2 );
		$percent = $max_marks > 0 ? round( $score * 100 / $max_marks, 2 ) : 0.0;
		$pass_percent = isset( $snapshot['policy']['pass_percent'] ) && is_numeric( $snapshot['policy']['pass_percent'] ) ? (float) $snapshot['policy']['pass_percent'] : 40.0;
		$pass_percent = max( 0.0, min( 100.0, $pass_percent ) );
		return array(
			'totalQuestions' => $total,
			'attempted' => $attempted,
			'correct' => $correct,
			'wrong' => $wrong,
			'unanswered' => $unanswered,
			'maxMarks' => round( $max_marks, 2 ),
			'positiveMarks' => round( $positive, 2 ),
			'negativeMarks' => round( $deductions, 2 ),
			'score' => $score,
			'percentage' => $percent,
			'accuracy' => $attempted > 0 ? round( $correct * 100 / $attempted, 2 ) : 0.0,
			'passingPercentage' => $pass_percent,
			'passed' => $total > 0 && $max_marks > 0 && $score >= $max_marks * $pass_percent / 100,
		);
	}

	/** Full review is only returned by a post-submission, ownership-checked endpoint. */
	public static function review( $snapshot, $answers ) {
		$questions = isset( $snapshot['questions'] ) && is_array( $snapshot['questions'] ) ? $snapshot['questions'] : array();
		$answers = is_array( $answers ) ? $answers : array();
		$review = array();
		foreach ( $questions as $question ) {
			$id = (int) $question['id'];
			$options = isset( $question['options'] ) && is_array( $question['options'] ) ? $question['options'] : array();
			$order = isset( $question['order'] ) && is_array( $question['order'] ) ? $question['order'] : array( 'A', 'B', 'C', 'D' );
			$correct = isset( $question['correct'] ) ? (string) $question['correct'] : '';
			$type = SMCQ_Question_Types::type( isset( $question['type'] ) ? $question['type'] : 'single' );
			$choice = SMCQ_Question_Types::canonical( isset( $answers[ (string) $id ] ) ? $answers[ (string) $id ] : null, $type, $options );
			$state = null === $choice ? 'unanswered' : ( $choice === $correct ? 'correct' : 'wrong' );
			$mapped = array();
			foreach ( $order as $i => $key ) {
				if ( isset( $options[ $key ] ) ) {
					$mapped[] = array( 'key' => $key, 'label' => chr( 65 + $i ), 'text' => $options[ $key ] );
				}
			}
			$points = self::safe_mark( isset( $question['points'] ) ? $question['points'] : 1, 1 );
			$penalty = self::safe_mark( isset( $question['negative'] ) ? $question['negative'] : 0, 0 );
			$review[] = array(
				'questionId' => $id,
				'type' => $type,
				'passage' => isset( $question['passage'] ) ? $question['passage'] : '',
				'media' => isset( $question['media'] ) ? $question['media'] : '',
				'title' => isset( $question['title'] ) ? (string) $question['title'] : '',
				'options' => $mapped,
				'yourAnswer' => $choice,
				'correctAnswer' => $correct,
				'status' => $state,
				'earnedMarks' => 'correct' === $state ? $points : ( 'wrong' === $state ? -$penalty : 0.0 ),
				'explanation' => isset( $question['explanation'] ) ? (string) $question['explanation'] : '',
			);
		}
		return $review;
	}

	private static function safe_mark( $raw, $default ) {
		if ( ! is_numeric( $raw ) || ! is_finite( (float) $raw ) || (float) $raw < 0 ) {
			return (float) $default;
		}
		return min( 1000.0, (float) $raw );
	}
}