<?php
/** Minimal no-dependency tests for the importer parser and validators. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/tmp/' ); }
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $obj ) { return $obj instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_kses_post( $html ) { return (string) $html; }
function wp_http_validate_url( $url ) { return preg_match( '~^https?://[a-z0-9.-]+/~i', $url ) ? $url : false; }
require dirname( __DIR__ ) . '/includes/class-smcq-question-types.php';
require dirname( __DIR__ ) . '/includes/class-smcq-importer.php';
function call_importer( $method, array $args ) {
	$ref = new ReflectionMethod( 'SMCQ_Importer', $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( null, $args );
}
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$csv = tempnam( sys_get_temp_dir(), 'smcq_test_' );
$fp = fopen( $csv, 'wb' );
fwrite( $fp, "\xEF\xBB\xBF" );
fputcsv( $fp, array( 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct', 'explanation', 'language' ) );
fputcsv( $fp, array( 'বাংলাদেশের রাজধানী?', 'ঢাকা', 'খুলনা', 'বরিশাল', 'সিলেট', 'A', "ঢাকা, বাংলাদেশের রাজধানী।\nআরও একটি লাইন।", 'bn' ) );
fclose( $fp );
$data = call_importer( 'parse_csv', array( $csv ) );
unlink( $csv );
must( ! is_wp_error( $data ), 'Valid UTF-8 CSV should parse.' );
must( 1 === count( $data['rows'] ), 'Expected one data row.' );
must( 'বাংলাদেশের রাজধানী?' === $data['rows'][0]['cells'][0], 'Bangla lost.' );
must( false !== strpos( $data['rows'][0]['cells'][6], "\n" ), 'Quoted newline lost.' );
$defaults = call_importer( 'suggested_mapping', array( $data['headers'] ) );
$mapping = call_importer( 'validate_mapping', array( $defaults, count( $data['headers'] ) ) );
must( ! is_wp_error( $mapping ), 'Valid default mapping should pass.' );
$row = call_importer( 'map_row', array( $data['rows'][0]['cells'], $mapping ) );
$validated = call_importer( 'validate_row', array( $row ) );
must( ! is_wp_error( $validated ), 'Valid mapped row should pass.' );
must( 1.0 === $validated['marks'], 'Missing mark default.' );
must( 0.0 === $validated['negative_marks'], 'Missing negative mark default.' );
$row['correct'] = 'Z';
must( is_wp_error( call_importer( 'validate_row', array( $row ) ) ), 'Invalid answer should fail.' );
$row['correct'] = 'A';
$row['negative_marks'] = '1.25';
must( is_wp_error( call_importer( 'validate_row', array( $row ) ) ), 'Invalid negative marks should fail.' );
$map = $defaults;
$map['option_a'] = $map['question'];
must( is_wp_error( call_importer( 'validate_mapping', array( $map, count( $data['headers'] ) ) ) ), 'Duplicate mapped columns should fail.' );
$hash1 = call_importer( 'question_hash', array( '  Hello   World  ' ) );
$hash2 = call_importer( 'question_hash', array( 'hello world' ) );
must( $hash1 === $hash2, 'Whitespace/case duplicate hashing failed.' );
echo "Importer parser/validation smoke tests passed.\n";
// This fixture exercises XLSX sheet resolution and Excel's inline/shared string formats
// on hosts with PHP ZIP + SimpleXML (enabled in the GitHub Actions test matrix).
if ( class_exists( 'ZipArchive' ) && function_exists( 'simplexml_load_string' ) ) {
	$xlsx = tempnam( sys_get_temp_dir(), 'smcq_xlsx_' );
	$archive = new ZipArchive();
	must( true === $archive->open( $xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE ), 'Cannot create XLSX test fixture.' );
	$archive->addFromString( 'xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Questions" sheetId="1" r:id="rId1"/></sheets></workbook>' );
	$archive->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>' );
	$archive->addFromString( 'xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>question</t></si><si><t>option_a</t></si><si><t>option_b</t></si><si><t>option_c</t></si><si><t>option_d</t></si><si><t>correct</t></si></sst>' );
	$archive->addFromString( 'xl/worksheets/sheet2.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>বাংলাদেশের রাজধানী?</t></is></c><c r="B2" t="inlineStr"><is><t>ঢাকা</t></is></c><c r="C2" t="inlineStr"><is><t>খুলনা</t></is></c><c r="D2" t="inlineStr"><is><t>সিলেট</t></is></c><c r="E2" t="inlineStr"><is><t>রাজশাহী</t></is></c><c r="F2" t="inlineStr"><is><t>A</t></is></c></row></sheetData></worksheet>' );
	$archive->close();
	$sheet = call_importer( 'parse_xlsx', array( $xlsx ) );
	unlink( $xlsx );
	must( ! is_wp_error( $sheet ), 'XLSX parser failed: ' . ( is_wp_error( $sheet ) ? $sheet->get_error_message() : '' ) );
	must( 'question' === $sheet['headers'][0], 'XLSX shared string header failed.' );
	must( 'বাংলাদেশের রাজধানী?' === $sheet['rows'][0]['cells'][0], 'XLSX Unicode inline string failed.' );
	echo "XLSX test fixture passed.\n";
} else {
	echo "XLSX test skipped: PHP ZIP/SimpleXML extensions unavailable in this CLI.\n";
}

// Step 14: structured JSON import uses the same mapping and validation as CSV.
$json_file = tempnam( sys_get_temp_dir(), 'smcq_json_' );
file_put_contents( $json_file, json_encode( array( 'questions' => array( array( 'question' => 'বাংলা প্রশ্ন', 'option_a'=>'হ্যাঁ', 'option_b'=>'না', 'option_c'=>'সম্ভবত', 'option_d'=>'জানি না', 'correct'=>'A' ) ) ), JSON_UNESCAPED_UNICODE ) );
$json_data = call_importer( 'parse_json', array( $json_file ) );
unlink( $json_file );
must( ! is_wp_error( $json_data ), 'Structured JSON import should parse.' );
must( 'বাংলা প্রশ্ন' === $json_data['rows'][0]['cells'][0], 'Unicode JSON question lost.' );
$json_mapping = call_importer( 'suggested_mapping', array( $json_data['headers'] ) );
$json_row = call_importer( 'map_row', array( $json_data['rows'][0]['cells'], $json_mapping ) );
must( ! is_wp_error( call_importer( 'validate_row', array( $json_row ) ) ), 'JSON row not valid after mapping.' );
$json_bad = tempnam( sys_get_temp_dir(), 'smcq_bad_' );
file_put_contents( $json_bad, '{"questions":[{"question": {"object": "invalid"}}]}' );
must( is_wp_error( call_importer( 'parse_json', array( $json_bad ) ) ), 'Nested JSON should be rejected.' );
unlink( $json_bad );
echo "Structured JSON tests passed.\n";

// New question types are validated and imported without changing legacy columns.
$multi = call_importer( 'map_row', array( array( 'Pick two', 'Alpha','Beta','Gamma','Delta','A,C','multiple' ),
    array( 'question'=>0,'option_a'=>1,'option_b'=>2,'option_c'=>3,'option_d'=>4,'correct'=>5,'question_type'=>6 ) ) );
$checked = call_importer( 'validate_row', array( $multi ) );
must( ! is_wp_error( $checked ) && 'A,C' === $checked['correct'], 'Multiple-answer import should validate' );
$tf = $multi; $tf['question_type'] = 'true_false'; $tf['correct'] = 'A';
$tf['option_c'] = ''; $tf['option_d'] = '';
must( ! is_wp_error( call_importer( 'validate_row', array( $tf ) ) ), 'True/False needs only two options' );
$bad = $multi; $bad['correct'] = 'A,Z';
must( is_wp_error( call_importer( 'validate_row', array( $bad ) ) ), 'Invalid multiple answer should be rejected' );
echo "Step 14 multiple and true/false importer validation passed.\n";