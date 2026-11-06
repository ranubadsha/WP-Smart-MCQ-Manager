<?php
/** Standalone Step 9 tests; no WordPress bootstrap needed. */
define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/includes/class-smcq-reports.php';
function assert_same($wanted,$actual,$label){ if($wanted!==$actual){ throw new RuntimeException($label . ': expected ' . var_export($wanted,true) . ', got ' . var_export($actual,true)); } }
$f=SMCQ_Reports::normalize_filters(array('smcq_exam'=>'42','smcq_student'=>'7','smcq_from'=>'2026-11-01','smcq_to'=>'2026-11-30','smcq_result'=>'passed'));
assert_same(array('exam'=>42,'student'=>7,'result'=>'passed','from'=>'2026-11-01','to'=>'2026-11-30'),$f,'filter sanitize');
$s=SMCQ_Reports::sql_filter($f);
assert_same(array(42,7,'2026-11-01 00:00:00','2026-12-01 00:00:00'),$s['args'],'exclusive until');
assert_same(false, strpos($s['where'],'passed')!==false,'result only after grade');
$f=SMCQ_Reports::normalize_filters(array('smcq_student'=>999,'smcq_result'=>'admin','smcq_from'=>'2026-02-31','smcq_to'=>'hello','smcq_exam'=>'-10'),true,8);
assert_same(8,$f['student'],'personal cannot choose another student');
assert_same('all',$f['result'],'result enum validated');
assert_same('',$f['from'],'invalid dates ignored');
assert_same(0,$f['exam'],'negative exam sanitized');
assert_same("'=2+3",SMCQ_Reports::csv_safe('=2+3'),'formula neutralized');
assert_same("' +SUM(2,3)",SMCQ_Reports::csv_safe(' +SUM(2,3)'),'prefixed whitespace neutralized');
assert_same('-0.25',SMCQ_Reports::csv_safe(-0.25),'numeric negative preserved');
assert_same("'-1+3",SMCQ_Reports::csv_safe('-1+3'),'malicious numeric prefix neutralized');
assert_same('&lt;বাংলা &amp; English&gt;',SMCQ_Reports::xml_safe('<বাংলা & English>'),'utf8+xml escaping');
assert_same(true,SMCQ_Reports::matches_result(array('passed'=>true),'passed'),'grade pass');
assert_same(false,SMCQ_Reports::matches_result(array('passed'=>true),'failed'),'grade fail filter');
assert_same(false,SMCQ_Reports::matches_result(null,'failed'),'invalid unscored omitted from fail filter');
$r=array('id'=>5,'exam_id'=>9,'user_id'=>10,'submitted_at'=>'2026-11-06 00:02:05','started_at'=>'2026-11-06 00:00:00','completion_reason'=>'manual');
$grade=array('score'=>3.75,'maxMarks'=>5,'percentage'=>75,'accuracy'=>80,'correct'=>4,'wrong'=>1,'unanswered'=>0,'passed'=>true);
$row=SMCQ_Reports::row_record($r,$grade,'বাংলা পরীক্ষা','Jane','jane@example.org');
assert_same('বাংলা পরীক্ষা',$row[2],'UTF8 exam title');
assert_same(125,$row[15],'elapsed seconds');
assert_same('Passed',$row[14],'passed status');
assert_same(17,count(SMCQ_Reports::headers()),'column count');
assert_same(17,count($row),'row count');
// Verify the generated XLSX sheet XML even on test hosts without the ZIP PHP extension.
if(!class_exists('ZipArchive')) {
    class ZipArchive {
        const CREATE=1;const OVERWRITE=8;
        public $entries=array();private $filename='';
        public function open($file,$flags){$this->filename=$file;return true;}
        public function addFromString($name,$data){$this->entries[$name]=$data;return true;}
        public function close(){file_put_contents($this->filename,json_encode($this->entries));return true;}
    }
}
$temp=tempnam(sys_get_temp_dir(),'smcq-test');
assert_same(true,SMCQ_Reports::build_xlsx(array(SMCQ_Reports::headers(),$row),$temp),'xlsx builder');
$raw=file_get_contents($temp);
$json=json_decode($raw,true);
if(is_array($json)) {
    $xml=isset($json['xl/worksheets/sheet1.xml']) ? $json['xl/worksheets/sheet1.xml'] : '';
} else {
    // On CI with PHP ZIP enabled, inspect through ZipArchive.
    $archive=new ZipArchive();
    if(true!==$archive->open($temp)){throw new RuntimeException('Unable to open XLSX');}
    $xml=$archive->getFromName('xl/worksheets/sheet1.xml');
    $archive->close();
}
if(strpos($xml,'বাংলা পরীক্ষা')===false||strpos($xml,'<autoFilter ref=')===false){throw new RuntimeException('Excel XML missing UTF8 values or filter');}
@unlink($temp);
echo "Step 9 reports and export helper tests passed.\n";