<?php
/** Minimal PHP-only regression tests for Step 4 exam selection and validation. */

define( 'ABSPATH', __DIR__ . '/' );
$posts = array(
	101 => (object) array( 'ID' => 101, 'post_type' => 'smcq_question', 'post_status' => 'publish', 'post_title' => 'বাংলাদেশের রাজধানী?' ),
	102 => (object) array( 'ID' => 102, 'post_type' => 'smcq_question', 'post_status' => 'publish', 'post_title' => 'English grammar' ),
	103 => (object) array( 'ID' => 103, 'post_type' => 'smcq_question', 'post_status' => 'draft', 'post_title' => 'Unpublished' ),
	104 => (object) array( 'ID' => 104, 'post_type' => 'smcq_question', 'post_status' => 'publish', 'post_title' => 'Missing answer' ),
	105 => (object) array( 'ID' => 105, 'post_type' => 'smcq_exam', 'post_status' => 'publish', 'post_title' => 'Not a question' ),
);
$metas = array();
foreach ( array( 101, 102, 103, 104 ) as $id ) {
	$metas[ $id ]['_smcq_options'] = array( 'A' => 'Answer A', 'B' => 'Answer B', 'C' => 'Answer C', 'D' => 'Answer D' );
	$metas[ $id ]['_smcq_correct'] = 'B';
}
$metas[104]['_smcq_correct'] = '';
$metas[101]['_smcq_difficulty'] = 'easy';
$metas[102]['_smcq_difficulty'] = 'hard';
function get_post( $id ) { global $posts; return isset( $posts[ $id ] ) ? $posts[ $id ] : null; }
function get_post_meta( $id, $key, $single ) { global $metas; return $metas[$id][$key] ?? ''; }
function update_meta_cache( $type, $ids ) { return true; }
function wp_timezone() { return new DateTimeZone( 'Asia/Dhaka' ); }
class WP_Query {
	public $posts;
	public static $last_args;
	public function __construct( $args ) {
		self::$last_args = $args;
		$this->posts = array( 105, 104, 103, 102, 101 );
		$meta = $args['meta_query'] ?? array();
		if ( $meta ) {
			$values = array();
			foreach ( $meta as $rule ) { if ( isset( $rule['value'] ) ) $values[] = $rule['value']; }
			if ( in_array( 'hard', $values, true ) ) $this->posts = array( 102 );
		}
	}
}
require dirname( __DIR__ ) . '/includes/class-smcq-exams.php';
function expect( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAILED: $message\n" ); exit( 1 ); }
}
$ids = SMCQ_Exams::normalize_ids( " 101,102\n101 ; abc;0;-1,103 " );
expect( $ids === array( 101, 102, 103 ), 'normalizes and deduplicates IDs' );
expect( SMCQ_Exams::normalize_ids( array( '101', '102' ) ) === array( 101, 102 ), 'stored ID arrays remain compatible' );
expect( count( SMCQ_Exams::normalize_ids( implode( ',', range( 1, 201 ) ) ) ) === 200, 'enforces question count ceiling' );
expect( SMCQ_Exams::decimal( '9999', 60, 1, 600 ) === 60, 'invalid numeric input uses default' );
expect( SMCQ_Exams::decimal( '1e999', 60, 1, 600 ) === 60, 'rejects non-finite value' );
expect( SMCQ_Exams::decimal( '38.555', 40, 0, 100 ) === 38.56, 'rounds fractional percentages' );
expect( SMCQ_Exams::valid_question( 101 ), 'Bangla complete question is eligible' );
expect( ! SMCQ_Exams::valid_question( 103 ), 'draft question is excluded' );
expect( ! SMCQ_Exams::valid_question( 104 ), 'incomplete question is excluded' );
expect( ! SMCQ_Exams::valid_question( 105 ), 'non-question post is excluded' );
expect( SMCQ_Exams::validate_local_date( '2026-11-06T21:30' ) === '2026-11-06T21:30', 'valid local datetime retained' );
expect( SMCQ_Exams::validate_local_date( '2026-02-30T25:99' ) === '', 'invalid date rejected' );
$generated = SMCQ_Exams::generate( array( 'subject' => 2, 'difficulty' => '' ), 2 );
sort( $generated );
expect( $generated === array( 101, 102 ), 'generator filters out drafts and incomplete questions' );
expect( WP_Query::$last_args['tax_query'][0]['taxonomy'] === 'smcq_subject', 'generator includes subject taxonomy' );
expect( SMCQ_Exams::generate( array( 'difficulty' => '' ), 3 ) === array(), 'generator does not return partial sets' );
expect( SMCQ_Exams::generate( array( 'difficulty' => 'hard' ), 1 ) === array( 102 ), 'difficulty filtered generation' );
expect( SMCQ_Exams::generate( array(), 201 ) === array(), 'rejects overly large tests' );
echo "PASS: Step 4 exam builder validation, eligibility and selection checks.\n";