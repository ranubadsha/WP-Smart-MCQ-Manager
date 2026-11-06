<?php
/** Standalone regression checks for editorial grouping, AI response validation and daily selection. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/tmp/' ); }
function wp_strip_all_tags( $input ) { return strip_tags( $input ); }
function absint( $value ) { return abs( (int) $value ); }
function __($v,$d='') { return $v; }
require_once __DIR__ . '/../includes/class-smcq-question-types.php';
require_once __DIR__ . '/../includes/class-smcq-editorial.php';
require_once __DIR__ . '/../includes/class-smcq-ai.php';
require_once __DIR__ . '/../includes/class-smcq-practice.php';
require_once __DIR__ . '/../includes/class-smcq-gamification.php';
require_once __DIR__ . '/../includes/class-smcq-live-monitor.php';
function check( $actual, $expected, $message ) { if ( $actual !== $expected ) { throw new RuntimeException($message.' '.var_export($actual,true).' vs '.var_export($expected,true)); } }
$duplicate = SMCQ_Editorial::duplicates( array( array('id'=>1,'title'=>'  What   is truth? '),array('id'=>2,'title'=>'what is truth'),array('id'=>3,'title'=>'Different') ) );
check(count($duplicate),1,'One duplicate group');
check(array_values($duplicate)[0],array(1,2),'Duplicate IDs');
check(count(SMCQ_Editorial::keys())>=14,true,'Question history fields');
$options=array('A'=>'Alpha','B'=>'Bravo','C'=>'Charlie','D'=>'Delta');
$source=array('questions'=>array(array('question'=>'What answer is correct?', 'options'=>$options,'correct'=>'A','explanation'=>'Because A.'),array('question'=>'Bad','options'=>$options,'correct'=>'Z')));
$records=SMCQ_AI::clean_records($source);
check(count($records),1,'Reject untrusted model records');
$api=array('output'=>array(array('content'=>array(array('type'=>'output_text','text'=>json_encode($source))))));
check(count(SMCQ_AI::response_json($api)['questions']),2,'Parse structured response');
check(SMCQ_AI::response_json(array('output'=>array())),array(),'Reject broken API response');
$daily=SMCQ_Practice::daily_select(array(1,2,3,2,4,5), '2026-11-07',3);
check(count($daily),3,'Daily pool size');
check($daily,SMCQ_Practice::daily_select(array(5,4,3,2,1),'2026-11-07',3),'Daily deterministic ordering');
if (class_exists('DOMDocument')) {
    class WP_Error { public function __construct($code,$message){} }
    require_once __DIR__.'/../includes/class-smcq-importer.php';
    $fields=array('question','option_a','option_b','option_c','option_d','correct');
    $row1='';$row2='';
    foreach($fields as $field) {$row1.='<w:tc><w:p><w:r><w:t>'.$field.'</w:t></w:r></w:p></w:tc>';}
    foreach(array('Question?','A text','B text','C text','D text','A') as $field) {$row2.='<w:tc><w:p><w:r><w:t>'.$field.'</w:t></w:r></w:p></w:tc>';}
    $xml='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr>'.$row1.'</w:tr><w:tr>'.$row2.'</w:tr></w:tbl></w:body></w:document>';
    $parsed=SMCQ_Importer::parse_docx_xml($xml);
    check($parsed['rows'][0]['cells'][0],'Question?','DOCX table question');
} else { echo "DOCX XML fixture skipped (PHP DOM unavailable).\n"; }
echo "Editorial, AI and daily practice tests passed.\n";

$badges=SMCQ_Gamification::badges( array( array('percentage'=>92,'passed'=>true),array('percentage'=>60,'passed'=>false) ) );
check(in_array('90% Club',$badges['badges'],true),true,'Achievement grading');
check(SMCQ_Live_Monitor::stats(array(array('status'=>'active','total'=>4),array('status'=>'submitted','total'=>9))),array('active'=>4,'submitted'=>9),'Live monitor aggregation');
echo "Gamification and monitor tests passed.\n";