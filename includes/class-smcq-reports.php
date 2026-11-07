<?php
/**
 * Step 9: private exam reports and secure CSV/XLSX/printable exports.
 * Exports use frozen submitted attempts; never include answer keys or explanations.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SMCQ_Reports {
	const MAX_EXPORT = 5000;
	const SAMPLE_SIZE = 500;
	const PAGE_SIZE = 25;
	const NONCE_ACTION = 'smcq_reports_export';

	public static function init() {
		add_action( 'admin_post_smcq_reports_export', array( __CLASS__, 'download' ) );
		add_shortcode( 'smcq_my_report', array( __CLASS__, 'my_report_shortcode' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
	}

	public static function admin_assets( $hook ) {
		if ( 'smcq_question_page_smcq-exam-reports' === $hook ) {
			wp_enqueue_style( 'smcq-admin', SMCQ_URL . 'assets/admin.css', array(), SMCQ_VERSION );
		}
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'smcq_attempts';
	}

	/** Pure filter validation: trusted scalar values only, UTC date range inclusive. */
	public static function normalize_filters( $input, $personal = false, $current_user = 0 ) {
		$filters = array(
			'exam' => 0, 'student' => 0, 'result' => 'all', 'from' => '', 'to' => '',
		);
		if ( ! is_array( $input ) ) { $input = array(); }
		$filters['exam'] = isset( $input['smcq_exam'] ) && is_scalar( $input['smcq_exam'] ) ? max( 0, (int) $input['smcq_exam'] ) : 0;
		$filters['student'] = $personal ? (int) $current_user : ( isset( $input['smcq_student'] ) && is_scalar( $input['smcq_student'] ) ? max( 0, (int) $input['smcq_student'] ) : 0 );
		$result = isset( $input['smcq_result'] ) && is_scalar( $input['smcq_result'] ) ? (string) $input['smcq_result'] : 'all';
		$filters['result'] = in_array( $result, array( 'all', 'passed', 'failed' ), true ) ? $result : 'all';
		foreach ( array( 'smcq_from' => 'from', 'smcq_to' => 'to' ) as $key => $name ) {
			$value = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
				$chunks = explode( '-', $value );
				if ( checkdate( (int) $chunks[1], (int) $chunks[2], (int) $chunks[0] ) ) {
					$filters[ $name ] = $value;
				}
			}
		}
		return $filters;
	}

	/** The WHERE clause only uses constant SQL and placeholder arguments. */
	public static function sql_filter( $filters ) {
		$sql = " WHERE status = 'submitted'";
		$args = array();
		if ( $filters['exam'] > 0 ) { $sql .= ' AND exam_id = %d'; $args[] = $filters['exam']; }
		if ( $filters['student'] > 0 ) { $sql .= ' AND user_id = %d'; $args[] = $filters['student']; }
		if ( $filters['from'] ) { $sql .= ' AND submitted_at >= %s'; $args[] = $filters['from'] . ' 00:00:00'; }
		if ( $filters['to'] ) { $sql .= ' AND submitted_at < %s'; $args[] = gmdate( 'Y-m-d', strtotime( $filters['to'] . ' +1 day' ) ) . ' 00:00:00'; }
		return array( 'where' => $sql, 'args' => $args );
	}

	/** Bounded submitted results; bind every optional filter and the table identifier. */
	private static function fetch( $filters, $limit, $offset = 0 ) {
		global $wpdb;
		$exam = absint( $filters['exam'] );
		$student = absint( $filters['student'] );
		$from = $filters['from'] ? $filters['from'] . ' 00:00:00' : '';
		$to = $filters['to'] ? gmdate( 'Y-m-d', strtotime( $filters['to'] . ' +1 day' ) ) . ' 00:00:00' : '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,exam_id,user_id,started_at,submitted_at,completion_reason,snapshot,answers,result_json FROM %i
			WHERE status = 'submitted'
			AND (%d = 0 OR exam_id = %d) AND (%d = 0 OR user_id = %d)
			AND (%s = '' OR submitted_at >= %s) AND (%s = '' OR submitted_at < %s)
			ORDER BY id DESC LIMIT %d OFFSET %d",
			self::table(), $exam, $exam, $student, $student, $from, $from, $to, $to,
			max( 1, (int) $limit ), max( 0, (int) $offset )
		), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	private static function count( $filters ) {
		global $wpdb;
		$exam = absint( $filters['exam'] );
		$student = absint( $filters['student'] );
		$from = $filters['from'] ? $filters['from'] . ' 00:00:00' : '';
		$to = $filters['to'] ? gmdate( 'Y-m-d', strtotime( $filters['to'] . ' +1 day' ) ) . ' 00:00:00' : '';
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE status = 'submitted'
			AND (%d = 0 OR exam_id = %d) AND (%d = 0 OR user_id = %d)
			AND (%s = '' OR submitted_at >= %s) AND (%s = '' OR submitted_at < %s)",
			self::table(), $exam, $exam, $student, $student, $from, $from, $to, $to
		) );
	}

	/** Pure table conversion; cached user info / exam titles passed from trusted caller. */
	public static function row_record( $row, $grade, $exam_title = '', $student_name = '', $email = '' ) {
		$start = isset( $row['started_at'] ) ? strtotime( (string) $row['started_at'] . ' UTC' ) : false;
		$end = isset( $row['submitted_at'] ) ? strtotime( (string) $row['submitted_at'] . ' UTC' ) : false;
		$duration = $start !== false && $end !== false ? max( 0, $end - $start ) : 0;
		return array(
			(int) $row['id'], (int) $row['exam_id'], (string) $exam_title, (int) $row['user_id'],
			(string) $student_name, (string) $email, (string) $row['submitted_at'],
			$grade ? (float) $grade['score'] : '', $grade ? (float) $grade['maxMarks'] : '',
			$grade ? (float) $grade['percentage'] : '', $grade ? (float) $grade['accuracy'] : '',
			$grade ? (int) $grade['correct'] : '', $grade ? (int) $grade['wrong'] : '',
			$grade ? (int) $grade['unanswered'] : '',
			$grade ? ( ! empty( $grade['passed'] ) ? 'Passed' : 'Failed' ) : 'Unscored',
			$duration, isset( $row['completion_reason'] ) ? (string) $row['completion_reason'] : '',
		);
	}

	public static function headers() {
		return array( 'Attempt ID', 'Exam ID', 'Exam', 'Student ID', 'Student', 'Email', 'Submitted (UTC)', 'Score', 'Maximum', 'Score (%)', 'Accuracy (%)', 'Correct', 'Wrong', 'Unanswered', 'Result', 'Duration (seconds)', 'Completion Reason' );
	}

	private static function grade( $row ) {
		$g = isset( $row['result_json'] ) ? json_decode( (string) $row['result_json'], true ) : null;
		if ( is_array( $g ) && isset( $g['score'], $g['maxMarks'], $g['percentage'], $g['accuracy'], $g['correct'], $g['wrong'], $g['unanswered'], $g['passed'] ) && is_numeric( $g['score'] ) ) { return $g; }
		$snapshot = isset( $row['snapshot'] ) ? json_decode( (string) $row['snapshot'], true ) : null;
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['questions'] ) || ! is_array( $snapshot['questions'] ) ) { return null; }
		$answers = isset( $row['answers'] ) ? json_decode( (string) $row['answers'], true ) : array();
		return SMCQ_Results::grade( $snapshot, $answers );
	}

	/** Pass/fail filtering uses frozen grading, including legacy attempts missing result_json. */
	public static function matches_result( $grade, $result ) {
		if ( 'all' === $result ) { return true; }
		if ( ! $grade ) { return false; }
		return 'passed' === $result ? ! empty( $grade['passed'] ) : empty( $grade['passed'] );
	}

	private static function select_rows( $filters, $offset, $limit ) {
		// Result status is inside frozen JSON, so paginate AFTER filtering. Scan in bounded chunks.
		// The scan cap is disclosed; DB indexes cover exam/user/time but not pass/fail.
		$matched = array(); $seen = 0; $scanned = 0;
		$scan_limit = max( self::MAX_EXPORT, $offset + $limit );
		$scan_limit = min( 25000, $scan_limit * 5 );
		while ( $scanned < $scan_limit && count( $matched ) < $limit ) {
			$batch = self::fetch( $filters, min( 250, $scan_limit - $scanned ), $scanned );
			if ( ! $batch ) { break; }
			foreach ( $batch as $row ) {
				$g = self::grade( $row );
				if ( ! self::matches_result( $g, $filters['result'] ) ) { continue; }
				if ( $seen++ < $offset ) { continue; }
				$matched[] = array( 'row' => $row, 'grade' => $g );
				if ( count( $matched ) >= $limit ) { break; }
			}
			$scanned += count( $batch );
			if ( count( $batch ) < min( 250, $scan_limit - ( $scanned - count( $batch ) ) ) ) { break; }
		}
		return $matched;
	}

	private static function export_url( $format, $filters, $personal ) {
		$args = array(
			'action' => 'smcq_reports_export', 'smcq_scope' => $personal ? 'me' : 'admin',
			'smcq_format' => $format, 'smcq_exam' => $filters['exam'],
			'smcq_result' => $filters['result'], 'smcq_from' => $filters['from'], 'smcq_to' => $filters['to'],
			'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
		);
		if ( ! $personal ) { $args['smcq_student'] = $filters['student']; }
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	public static function personal_links() {
		if ( ! is_user_logged_in() ) { return ''; }
		$filters = self::normalize_filters( array(), true, get_current_user_id() );
		return '<div class="smcq-report-actions"><strong>' . esc_html__( 'My results export:', 'smart-mcq-manager' ) . '</strong> '
			. '<a href="' . esc_url( self::export_url( 'csv', $filters, true ) ) . '">CSV</a> · '
			. '<a href="' . esc_url( self::export_url( 'xlsx', $filters, true ) ) . '">Excel</a> · '
			. '<a href="' . esc_url( self::export_url( 'print', $filters, true ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Print / Save as PDF', 'smart-mcq-manager' ) . '</a></div>';
	}

	public static function my_report_shortcode() {
		if ( ! is_user_logged_in() ) { return '<p>' . esc_html__( 'Log in to export your examination history.', 'smart-mcq-manager' ) . '</p>'; }
		return '<section class="smcq-my-report"><h2>' . esc_html__( 'Download My Exam Results', 'smart-mcq-manager' ) . '</h2>' . self::personal_links() . '<p>' . esc_html__( 'Only your own submitted attempts are included. Exports are limited to the latest 5,000 matching attempts.', 'smart-mcq-manager' ) . '</p></section>';
	}

	/** Administrator report retains the existing menu slug, so bookmarks keep working. */
	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'smart-mcq-manager' ) ); }
		global $wpdb;
		$filters = self::normalize_filters( wp_unslash( $_GET ) );
		$page = isset( $_GET['smcq_page'] ) ? max( 1, min( 1000, absint( $_GET['smcq_page'] ) ) ) : 1;
		$exams = get_posts( array( 'post_type' => 'smcq_exam', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 100, 'orderby' => 'ID', 'order' => 'DESC' ) );
		echo '<div class="wrap smcq-report-admin"><h1>' . esc_html__( 'MCQ Exam Reports & Exports', 'smart-mcq-manager' ) . '</h1>';
		echo '<form method="get" class="smcq-report-filters"><input type="hidden" name="post_type" value="smcq_question"><input type="hidden" name="page" value="smcq-exam-reports">';
		echo '<label>' . esc_html__( 'Exam', 'smart-mcq-manager' ) . '<select name="smcq_exam"><option value="0">' . esc_html__( 'All examinations', 'smart-mcq-manager' ) . '</option>';
		foreach ( $exams as $exam ) { echo '<option value="' . esc_attr( $exam->ID ) . '" ' . selected( $filters['exam'], $exam->ID, false ) . '>' . esc_html( get_the_title( $exam->ID ) ) . ' (#' . esc_html( $exam->ID ) . ')</option>'; }
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Student ID (optional)', 'smart-mcq-manager' ) . '<input type="number" name="smcq_student" min="1" value="' . esc_attr( $filters['student'] ?: '' ) . '" placeholder="User ID"></label>';
		echo '<label>' . esc_html__( 'Result', 'smart-mcq-manager' ) . '<select name="smcq_result">';
		foreach ( array( 'all' => 'All', 'passed' => 'Passed', 'failed' => 'Failed' ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $filters['result'], false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select></label>';
		echo '<label>' . esc_html__( 'From (UTC)', 'smart-mcq-manager' ) . '<input type="date" name="smcq_from" value="' . esc_attr( $filters['from'] ) . '"></label>';
		echo '<label>' . esc_html__( 'To (UTC)', 'smart-mcq-manager' ) . '<input type="date" name="smcq_to" value="' . esc_attr( $filters['to'] ) . '"></label>';
		echo '<button class="button button-primary" type="submit">' . esc_html__( 'Apply filters', 'smart-mcq-manager' ) . '</button></form>';

		$base_count = self::count( $filters );
		// Status filtering happens after grading, so exact counts may require a costly full scan.
		$sample = self::select_rows( $filters, 0, self::SAMPLE_SIZE );
		$sum = self::sample_summary( $sample );
		echo '<div class="smcq-report-kpis">';
		foreach ( array(
			__( 'Submitted (before grade filter)', 'smart-mcq-manager' ) => number_format_i18n( $base_count ),
			__( 'Sampled attempts', 'smart-mcq-manager' ) => count( $sample ),
			__( 'Average score (%)', 'smart-mcq-manager' ) => $sum['average'],
			__( 'Pass rate (%)', 'smart-mcq-manager' ) => $sum['passRate'],
			__( 'Highest score (%)', 'smart-mcq-manager' ) => $sum['high'],
			__( 'Lowest score (%)', 'smart-mcq-manager' ) => $sum['low'],
		) as $label => $value ) { echo '<div class="smcq-report-kpi"><small>' . esc_html( $label ) . '</small><strong>' . esc_html( $value ) . '</strong></div>'; }
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Metrics and category analysis use up to 500 matching completed attempts; the total is before the pass/fail filter. Exports include at most 5,000 matching attempts. Date filters use UTC.', 'smart-mcq-manager' ) . '</p>';

		$buttons = array( 'csv' => __( 'Download CSV', 'smart-mcq-manager' ), 'xlsx' => __( 'Download Excel', 'smart-mcq-manager' ), 'print' => __( 'Print / Save as PDF', 'smart-mcq-manager' ) );
		echo '<p class="smcq-report-actions">';
		foreach ( $buttons as $format => $label ) { echo '<a class="button" href="' . esc_url( self::export_url( $format, $filters, false ) ) . '"' . ( 'print' === $format ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $label ) . '</a> '; }
		echo '</p>';

		$rows = self::select_rows( $filters, ( $page - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE );
		echo '<div class="smcq-report-table-wrap"><table class="widefat striped"><thead><tr><th>Attempt</th><th>Exam</th><th>Student</th><th>Submitted UTC</th><th>Score</th><th>Accuracy</th><th>Result</th><th>Duration</th></tr></thead><tbody>';
		foreach ( $rows as $entry ) {
			$r = $entry['row']; $grade = $entry['grade']; $u = get_userdata( (int) $r['user_id'] );
			echo '<tr><td>#' . esc_html( $r['id'] ) . '</td><td>' . esc_html( get_the_title( $r['exam_id'] ) ) . '</td><td>' . esc_html( $u ? $u->display_name . ' (#' . $u->ID . ')' : 'Deleted user' ) . '</td><td>' . esc_html( $r['submitted_at'] ) . '</td><td>' . esc_html( $grade ? $grade['score'] . '/' . $grade['maxMarks'] : '—' ) . '</td><td>' . esc_html( $grade ? $grade['accuracy'] . '%' : '—' ) . '</td><td>' . esc_html( $grade ? ( $grade['passed'] ? 'Passed' : 'Failed' ) : 'Unscored' ) . '</td><td>' . esc_html( self::row_record( $r, $grade )[15] ) . 's</td></tr>';
		}
		echo '</tbody></table></div>';
		$base = add_query_arg( array( 'post_type' => 'smcq_question', 'page' => 'smcq-exam-reports', 'smcq_exam' => $filters['exam'], 'smcq_student' => $filters['student'], 'smcq_result' => $filters['result'], 'smcq_from' => $filters['from'], 'smcq_to' => $filters['to'] ), admin_url( 'edit.php' ) );
		// translators: %d: the current report page number.
		echo '<p class="tablenav"><a class="button" href="' . esc_url( add_query_arg( 'smcq_page', max( 1, $page - 1 ), $base ) ) . '"' . ( $page <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ) . '>' . esc_html__( 'Previous', 'smart-mcq-manager' ) . '</a> ' . esc_html( sprintf( __( 'Page %d', 'smart-mcq-manager' ), $page ) ) . ' <a class="button" href="' . esc_url( add_query_arg( 'smcq_page', $page + 1, $base ) ) . '"' . ( count( $rows ) < self::PAGE_SIZE ? ' aria-disabled="true" tabindex="-1"' : '' ) . '>' . esc_html__( 'Next', 'smart-mcq-manager' ) . '</a></p>';
		if ( $sample ) { self::category_table( $sample ); }
		echo '</div>';
	}

	private static function sample_summary( $entries ) {
		$sum=0; $valid=0; $passes=0; $min=null; $max=null;
		foreach ( $entries as $entry ) {
			$g=$entry['grade']; if ( ! $g ) { continue; }
			$p=(float) $g['percentage']; $sum+=$p; ++$valid; if ( $g['passed'] ) { ++$passes; }
			$min=null===$min ? $p : min($min,$p); $max=null===$max ? $p : max($max,$p);
		}
		return array( 'average'=>$valid?round($sum/$valid,2):0, 'passRate'=>$valid?round($passes*100/$valid,2):0, 'low'=>$min===null?'—':round($min,2), 'high'=>$max===null?'—':round($max,2) );
	}

	private static function category_table( $sample ) {
		$rows=array_map( static function($x){return $x['row'];},$sample );
		$analysis=SMCQ_Analytics::summarize($rows,0);
		foreach ( array('subjects'=>__( 'Subject performance (sample)', 'smart-mcq-manager' ),'topics'=>__( 'Topic performance (sample)', 'smart-mcq-manager' )) as $key=>$title ) {
			if ( empty( $analysis[$key] ) ) {continue;}
			echo '<h2>'.esc_html($title).'</h2><div class="smcq-report-table-wrap"><table class="widefat striped"><thead><tr><th>'.esc_html__( 'Subject / Topic', 'smart-mcq-manager' ).'</th><th>'.esc_html__( 'Questions', 'smart-mcq-manager' ).'</th><th>'.esc_html__( 'Answered', 'smart-mcq-manager' ).'</th><th>'.esc_html__( 'Correct', 'smart-mcq-manager' ).'</th><th>'.esc_html__( 'Accuracy', 'smart-mcq-manager' ).'</th></tr></thead><tbody>';
			foreach($analysis[$key] as $g){echo '<tr><td>'.esc_html($g['name']).'</td><td>'.esc_html($g['questions']).'</td><td>'.esc_html($g['attempted']).'</td><td>'.esc_html($g['correct']).'</td><td>'.esc_html(null===$g['accuracy']?'—':$g['accuracy'].'%').'</td></tr>';}
			echo '</tbody></table></div>';
		}
	}

	/** Prevent spreadsheet programs from treating user-supplied text as formulas on CSV open. */
	public static function csv_safe( $value ) {
		$text = (string) $value;
		if ( is_numeric( $value ) && is_finite( (float) $value ) ) { return $text; }
		if ( preg_match( '/^[\x00-\x20]*[=+\-@]/u', $text ) ) { return "'" . $text; }
		return $text;
	}

	/** Protect XML 1.0 validity; XLSX uses inline strings (never formulas). */
	public static function xml_safe( $value ) {
		$text = (string) $value;
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );
		return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	public static function download() {
		$scope = isset( $_GET['smcq_scope'] ) && is_scalar( $_GET['smcq_scope'] ) ? sanitize_key( wp_unslash( $_GET['smcq_scope'] ) ) : '';
		$format = isset( $_GET['smcq_format'] ) && is_scalar( $_GET['smcq_format'] ) ? sanitize_key( wp_unslash( $_GET['smcq_format'] ) ) : '';
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Please log in.', 'smart-mcq-manager' ), '', array( 'response' => 401 ) ); }
		if ( ! in_array( $scope, array( 'admin', 'me' ), true ) || ! in_array( $format, array( 'csv', 'xlsx', 'print' ), true ) ) { wp_die( esc_html__( 'Invalid report request.', 'smart-mcq-manager' ), '', array( 'response' => 400 ) ); }
		if ( 'admin' === $scope && ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'smart-mcq-manager' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( self::NONCE_ACTION );
		$personal = 'me' === $scope;
		$filters = self::normalize_filters( wp_unslash( $_GET ), $personal, get_current_user_id() );
		$results = self::select_rows( $filters, 0, self::MAX_EXPORT );
		$data = array( self::headers() );
		$user_cache = array(); $exam_cache = array();
		foreach ( $results as $entry ) {
			$r=$entry['row']; $uid=(int)$r['user_id']; $eid=(int)$r['exam_id'];
			if(!array_key_exists($uid,$user_cache)){$u=get_userdata($uid); $user_cache[$uid]=$u ? array($u->display_name,$u->user_email) : array('Deleted user','');}
			if(!array_key_exists($eid,$exam_cache)){$exam_cache[$eid]=get_the_title($eid);}
			$data[]=self::row_record($r,$entry['grade'],$exam_cache[$eid],$user_cache[$uid][0],$user_cache[$uid][1]);
		}
		$filename = 'smcq-' . ( $personal ? 'my-results' : 'admin-report' ) . '-' . gmdate('Ymd-His');
		if ( 'csv' === $format ) { self::download_csv($data,$filename); }
		if ( 'xlsx' === $format ) { self::download_xlsx($data,$filename); }
		self::print_page($data,$personal);
	}

	private static function download_csv( $rows, $filename ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
		$out = new SplTempFileObject();
		foreach ( $rows as $row ) { $out->fputcsv( array_map( array( __CLASS__, 'csv_safe' ), $row ) ); }
		$out->rewind();
		echo "\xEF\xBB\xBF";
		while ( ! $out->eof() ) { echo $out->fgets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV attachment bytes, not HTML.
		}
		exit;
	}

	/** Small dependency-free UTF-8 XLSX export using PHP ZipArchive. */
	public static function build_xlsx( $rows, $target ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return false; }
		$zip = new ZipArchive();
		if ( true !== $zip->open( $target, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return false; }
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="MCQ Results" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF174A77"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>' );
		$letters = static function( $i ) { $out = ''; for( $j=$i+1; $j>0; $j=(int)(($j-1)/26) ){ $out=chr(65+(($j-1)%26)).$out; } return $out; };
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews><sheetFormatPr defaultRowHeight="17"/><cols>';
		foreach( $rows[0] as $col => $label ) { $width = in_array($col,array(2,4,5),true) ? 32 : ( $col===6?22:17 ); $sheet .= '<col min="'.($col+1).'" max="'.($col+1).'" width="'.$width.'" customWidth="1"/>'; }
		$sheet .= '</cols><sheetData>';
		foreach ( $rows as $rownum => $row ) {
			$sheet .= '<row r="'.($rownum+1).'">';
			foreach ( $row as $column => $value ) {
				$ref=$letters($column).($rownum+1);
				if ( $rownum>0 && is_numeric($value) && !is_string($value) ) {
					$sheet.='<c r="'.$ref.'" t="n"><v>'.(float)$value.'</v></c>';
				} else {
					$sheet.='<c r="'.$ref.'" t="inlineStr"'.($rownum===0?' s="1"':'').'><is><t xml:space="preserve">'.self::xml_safe($value).'</t></is></c>';
				}
			}
			$sheet.='</row>';
		}
		$sheet.='</sheetData><autoFilter ref="A1:Q'.count($rows).'"/></worksheet>';
		$zip->addFromString('xl/worksheets/sheet1.xml',$sheet);
		return $zip->close();
	}

	private static function download_xlsx( $rows, $filename ) {
		if ( ! class_exists( 'ZipArchive' ) ) { wp_die( esc_html__( 'PHP ZIP extension is required for Excel downloads. Use CSV instead.', 'smart-mcq-manager' ) ); }
		$temp = tempnam( get_temp_dir(), 'smcqreport-' );
		if ( ! $temp || ! self::build_xlsx( $rows, $temp ) ) { if ( $temp ) { wp_delete_file( $temp ); } wp_die( esc_html__( 'Could not generate Excel report.', 'smart-mcq-manager' ) ); }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$bytes = $wp_filesystem ? $wp_filesystem->get_contents( $temp ) : false;
		wp_delete_file( $temp );
		if ( false === $bytes ) { wp_die( esc_html__( 'Unable to read the generated report.', 'smart-mcq-manager' ) ); }
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.xlsx"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary XLSX payload, not HTML.

		exit;
	}

	private static function print_page( $rows, $personal ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header('Content-Type: text/html; charset=UTF-8');
		$title = $personal ? __( 'My MCQ Exam Results', 'smart-mcq-manager' ) : __( 'Administrator MCQ Exam Report', 'smart-mcq-manager' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($title).'</title><style>body{font-family:system-ui,"Noto Sans Bengali",sans-serif;margin:28px;color:#1a2d48}header{display:flex;justify-content:space-between;align-items:center;gap:12px}h1{font-size:21px}button{padding:8px 15px;border:1px solid #123e71;background:#174a77;color:#fff;border-radius:6px;cursor:pointer}.hint{color:#52677e}table{width:100%;border-collapse:collapse;font-size:10px;table-layout:auto}th,td{border:1px solid #ccd6e2;padding:6px;text-align:left;overflow-wrap:anywhere}th{background:#174a77;color:#fff}tr:nth-child(odd){background:#f5f8fc}@page{size:landscape;margin:10mm}@media print{header button,.hint{display:none}body{margin:0}thead{display:table-header-group}tr{break-inside:avoid}}</style></head><body><header><h1>'.esc_html($title).'</h1><button type="button" onclick="window.print()">'.esc_html__( 'Print / Save as PDF', 'smart-mcq-manager' ).'</button></header><p class="hint">'.esc_html__( 'Use your browser’s Print dialog and choose Save as PDF. Showing at most 5,000 matching attempts.', 'smart-mcq-manager' ).'</p><p>'.esc_html( gmdate('Y-m-d H:i') ).' UTC · '.esc_html(count($rows)-1).' '.esc_html__( 'attempts', 'smart-mcq-manager' ).'</p><table><thead><tr>';
		foreach($rows[0] as $cell){echo '<th>'.esc_html($cell).'</th>';}
		echo '</tr></thead><tbody>';
		foreach(array_slice($rows,1) as $row){echo '<tr>';foreach($row as $cell){echo '<td>'.esc_html((string)$cell).'</td>';}echo '</tr>';}
		echo '</tbody></table></body></html>'; exit;
	}
}