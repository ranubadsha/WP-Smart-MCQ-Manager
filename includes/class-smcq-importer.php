<?php
/**
 * Step 3: Private, administrator-only CSV/XLSX/DOCX/JSON question importer.
 * Never exposes the question bank or import preview through public endpoints.
 *
 * @package SmartMCQManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SMCQ_Importer {
	const MAX_BYTES = 3145728; // 3 MiB, before decompression.
	const MAX_ROWS = 500;
	const MAX_COLS = 32;
	const BATCH_SIZE = 40;
	const TTL = 1800;

	/** Bootstrap admin-only hooks. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_smcq_import_preview', array( __CLASS__, 'preview_action' ) );
		add_action( 'admin_post_smcq_import_commit', array( __CLASS__, 'commit_action' ) );
		add_action( 'admin_post_smcq_import_cancel', array( __CLASS__, 'cancel_action' ) );
		add_action( 'admin_post_smcq_import_template', array( __CLASS__, 'template_action' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=smcq_question',
			__( 'Import MCQ Questions', 'smart-mcq-manager' ),
			__( 'Bulk Import', 'smart-mcq-manager' ),
			'manage_options',
			'smcq-import',
			array( __CLASS__, 'page' )
		);
	}

	private static function page_url() {
		return admin_url( 'edit.php?post_type=smcq_question&page=smcq-import' );
	}

	private static function state_key() {
		return 'smcq_imp_state_' . get_current_user_id();
	}

	private static function report_key() {
		return 'smcq_imp_report_' . get_current_user_id();
	}

	private static function authorize( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to import questions.', 'smart-mcq-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** Render the admin wizard. */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to import questions.', 'smart-mcq-manager' ) );
		}
		$state = get_transient( self::state_key() );
		$report = get_transient( self::report_key() );
		$error = isset( $_GET['smcq_error'] ) && is_scalar( $_GET['smcq_error'] ) ? sanitize_key( wp_unslash( $_GET['smcq_error'] ) ) : '';
		$errors = array(
			'bad_file'    => __( 'Could not read this file. Check the format, file size and server PHP extensions.', 'smart-mcq-manager' ),
			'bad_mapping' => __( 'Select a different column for each required field, and choose every required field.', 'smart-mcq-manager' ),
			'expired'     => __( 'The preview expired. Please upload the file again.', 'smart-mcq-manager' ),
		);
		?>
		<div class="wrap smcq-import-wrap">
			<h1><?php esc_html_e( 'Bulk Import MCQ Questions', 'smart-mcq-manager' ); ?></h1>
			<p><?php esc_html_e( 'Upload UTF-8 CSV, Excel XLSX, Word DOCX tables, or structured JSON. Review the column mapping before importing. Existing questions are never overwritten.', 'smart-mcq-manager' ); ?></p>
			<?php if ( isset( $errors[ $error ] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( is_array( $report ) ) : ?>
				<div class="notice notice-success"><p><strong><?php esc_html_e( 'Import completed.', 'smart-mcq-manager' ); ?></strong>
				<?php
				printf(
					/* translators: 1: added count, 2: duplicate count, 3: invalid count */
					esc_html__( 'Added: %1$d; duplicates skipped: %2$d; invalid or failed: %3$d.', 'smart-mcq-manager' ),
					(int) $report['added'], (int) $report['duplicates'], (int) $report['invalid']
				);
				?></p>
				<?php self::render_errors( $report['errors'] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( is_array( $state ) ) : ?>
				<?php if ( empty( $state['mapping'] ) ) { self::render_preview( $state ); } else { self::render_progress( $state ); } ?>
			<?php else : ?>
				<div class="card" style="max-width:850px;padding:16px 24px">
					<h2><?php esc_html_e( '1. Upload questions', 'smart-mcq-manager' ); ?></h2>
					<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="smcq_import_preview" />
						<?php wp_nonce_field( 'smcq_import_preview' ); ?>
						<p><label for="smcq_import_file"><strong><?php esc_html_e( 'Question file (.csv, .xlsx, .docx or .json)', 'smart-mcq-manager' ); ?></strong></label></p>
						<p><input required type="file" id="smcq_import_file" name="smcq_import_file" accept=".csv,.xlsx,.docx,.json,text/csv,application/json,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.wordprocessingml.document" /></p>
						<p class="description"><?php esc_html_e( 'Up to 500 questions and 3 MiB per upload. First worksheet only for XLSX. UTF-8 CSV recommended for maximum compatibility.', 'smart-mcq-manager' ); ?></p>
						<?php submit_button( __( 'Upload & Preview', 'smart-mcq-manager' ) ); ?>
					</form>
				</div>
				<p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=smcq_import_template' ), 'smcq_import_template' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Download Sample CSV Template', 'smart-mcq-manager' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'To preserve Bangla text, save CSV using UTF-8 encoding. If your hosting lacks PHP ZIP and XML extensions, use CSV instead of XLSX.', 'smart-mcq-manager' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Step 1: parse a genuine uploaded file and keep a private short-lived preview. */
	public static function preview_action() {
		self::authorize( 'smcq_import_preview' );
		delete_transient( self::report_key() );
		delete_transient( self::state_key() );
		if ( empty( $_FILES['smcq_import_file'] ) || ! is_array( $_FILES['smcq_import_file'] ) ) {
			self::redirect_error( 'bad_file' );
		}
		$file = $_FILES['smcq_import_file'];
		if ( ! isset( $file['error'], $file['size'], $file['tmp_name'], $file['name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ||
			! is_scalar( $file['size'] ) || (int) $file['size'] < 1 || (int) $file['size'] > self::MAX_BYTES ||
			! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || ! is_string( $file['name'] ) ) {
			self::redirect_error( 'bad_file' );
		}
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'csv', 'xlsx', 'docx', 'json' ), true ) ) {
			self::redirect_error( 'bad_file' );
		}
		$data = 'csv' === $extension ? self::parse_csv( $file['tmp_name'] ) : ( 'json' === $extension ? self::parse_json( $file['tmp_name'] ) : ( 'docx' === $extension ? self::parse_docx( $file['tmp_name'] ) : self::parse_xlsx( $file['tmp_name'] ) ) );
		if ( is_wp_error( $data ) || strlen( serialize( $data ) ) > 2097152 ) {
			self::redirect_error( 'bad_file' );
		}
		$token = wp_generate_password( 40, false, false );
		$state = array(
			'token'      => $token,
			'headers'    => $data['headers'],
			'rows'       => $data['rows'],
			'mapping'    => array(),
			'offset'     => 0,
			'status'     => 'draft',
			'counts'     => array( 'added' => 0, 'duplicates' => 0, 'invalid' => 0 ),
			'errors'     => array(),
		);
		set_transient( self::state_key(), $state, self::TTL );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	private static function redirect_error( $code ) {
		wp_safe_redirect( add_query_arg( 'smcq_error', $code, self::page_url() ) );
		exit;
	}

	private static function field_labels() {
		return array(
			'question'       => __( 'Question *', 'smart-mcq-manager' ),
			'option_a'       => __( 'Option A *', 'smart-mcq-manager' ),
			'option_b'       => __( 'Option B *', 'smart-mcq-manager' ),
			'option_c'       => __( 'Option C *', 'smart-mcq-manager' ),
			'option_d'       => __( 'Option D *', 'smart-mcq-manager' ),
			'correct'        => __( 'Correct Answer (A–D or comma-separated A,C) *', 'smart-mcq-manager' ),
            'question_type'  => __( 'Question type (single/multiple/true_false/passage/math)', 'smart-mcq-manager' ),
            'passage' => __( 'Reading passage text', 'smart-mcq-manager' ),
			'explanation'    => __( 'Explanation', 'smart-mcq-manager' ),
			'subject'        => __( 'Subject', 'smart-mcq-manager' ),
			'topic'          => __( 'Topic', 'smart-mcq-manager' ),
			'exam_source'    => __( 'Exam Source', 'smart-mcq-manager' ),
			'tags'           => __( 'Tags (separate with |)', 'smart-mcq-manager' ),
			'difficulty'     => __( 'Difficulty', 'smart-mcq-manager' ),
			'language'       => __( 'Language', 'smart-mcq-manager' ),
			'exam_year'      => __( 'Exam Year', 'smart-mcq-manager' ),
			'reference'      => __( 'Book / Reference', 'smart-mcq-manager' ),
			'reference_url'  => __( 'Reference URL', 'smart-mcq-manager' ),
			'marks'          => __( 'Marks', 'smart-mcq-manager' ),
			'negative_marks' => __( 'Negative Marks', 'smart-mcq-manager' ),
		);
	}

	private static function required_fields() {
		return array( 'question', 'option_a', 'option_b', 'correct' );
	}

	private static function suggested_mapping( $headers ) {
		$aliases = array( 'answer' => 'correct', 'correct_answer' => 'correct', 'optiona' => 'option_a', 'optionb' => 'option_b', 'optionc' => 'option_c', 'optiond' => 'option_d', 'exam' => 'exam_source', 'year' => 'exam_year', 'negative' => 'negative_marks', 'type' => 'question_type', 'correct_keys' => 'correct' );
		$fields = self::field_labels();
		$mapping = array();
		foreach ( $headers as $index => $header ) {
			$key = strtolower( trim( (string) $header ) );
			$key = str_replace( array( ' ', '-' ), '_', $key );
			$key = isset( $aliases[ $key ] ) ? $aliases[ $key ] : $key;
			if ( isset( $fields[ $key ] ) && ! isset( $mapping[ $key ] ) ) {
				$mapping[ $key ] = (string) $index;
			}
		}
		return $mapping;
	}

	private static function render_preview( $state ) {
		$headers = $state['headers'];
		$defaults = self::suggested_mapping( $headers );
		?>
		<h2><?php esc_html_e( '2. Preview and map columns', 'smart-mcq-manager' ); ?></h2>
		<p><?php
		/* translators: %d: number of imported questions detected in the preview. */
		printf( esc_html__( '%d questions found. Showing the first 5 rows. The question and all four options must be filled in.', 'smart-mcq-manager' ), count( $state['rows'] ) ); ?></p>
		<div style="max-width:100%;overflow:auto"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Source row', 'smart-mcq-manager' ); ?></th>
		<?php foreach ( $headers as $header ) : ?><th><?php echo esc_html( $header ); ?></th><?php endforeach; ?>
		</tr></thead><tbody>
		<?php foreach ( array_slice( $state['rows'], 0, 5 ) as $row ) : ?><tr><td><?php echo esc_html( $row['line'] ); ?></td>
		<?php foreach ( $row['cells'] as $cell ) : ?><td><?php echo esc_html( self::truncate( $cell, 100 ) ); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
		</tbody></table></div>
		<p><?php esc_html_e( 'Match each field with a column. Optional fields may be set to — Ignore —. Duplicate columns for required fields are not allowed.', 'smart-mcq-manager' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="smcq_import_commit" />
			<input type="hidden" name="smcq_token" value="<?php echo esc_attr( $state['token'] ); ?>" />
			<?php wp_nonce_field( 'smcq_import_commit' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<?php foreach ( self::field_labels() as $field => $label ) : ?>
				<tr><th scope="row"><label for="smcq-map-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
				<select id="smcq-map-<?php echo esc_attr( $field ); ?>" name="smcq_map[<?php echo esc_attr( $field ); ?>]">
				<option value=""><?php esc_html_e( '— Ignore —', 'smart-mcq-manager' ); ?></option>
				<?php foreach ( $headers as $i => $column ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( isset( $defaults[ $field ] ) ? $defaults[ $field ] : '', (string) $i ); ?>><?php echo esc_html( $column ); ?> (<?php echo esc_html( $i + 1 ); ?>)</option>
				<?php endforeach; ?>
				</select></td></tr>
			<?php endforeach; ?>
			<tr><th scope="row"><label for="smcq_import_status"><?php esc_html_e( 'New question status', 'smart-mcq-manager' ); ?></label></th>
			<td><select id="smcq_import_status" name="smcq_import_status"><option value="draft"><?php esc_html_e( 'Draft (recommended)', 'smart-mcq-manager' ); ?></option><option value="publish"><?php esc_html_e( 'Published (admin-only question post)', 'smart-mcq-manager' ); ?></option></select></td></tr>
			</tbody></table>
			<?php submit_button( __( 'Confirm Mapping & Start Import', 'smart-mcq-manager' ) ); ?>
		</form>
		<?php self::render_cancel( $state ); ?>
		<?php
	}

	private static function render_progress( $state ) {
		$total = count( $state['rows'] );
		printf( '<h2>%s</h2>', esc_html__( '3. Import in batches', 'smart-mcq-manager' ) );
		printf( '<p>%s</p>', esc_html( sprintf( /* translators: 1: completed rows, 2: total rows */ __( '%1$d / %2$d rows processed.', 'smart-mcq-manager' ), $state['offset'], $total ) ) );
		printf( '<p>%s</p>', esc_html( sprintf( /* translators: 1: added, 2: duplicates, 3: invalid */ __( 'Added: %1$d; duplicates: %2$d; invalid or failed: %3$d.', 'smart-mcq-manager' ), $state['counts']['added'], $state['counts']['duplicates'], $state['counts']['invalid'] ) ) );
		self::render_errors( $state['errors'] );
		?>
		<form id="smcq-import-next" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="smcq_import_commit" />
			<input type="hidden" name="smcq_token" value="<?php echo esc_attr( $state['token'] ); ?>" />
			<?php wp_nonce_field( 'smcq_import_commit' ); ?>
			<?php submit_button( __( 'Continue Next Batch', 'smart-mcq-manager' ) ); ?>
		</form>
		<p class="description"><?php esc_html_e( '40 rows are processed per request to help avoid server timeouts. Continue until complete; you can leave this page and resume within 30 minutes.', 'smart-mcq-manager' ); ?></p>
		<?php self::render_cancel( $state ); ?>
		<?php
	}

	private static function render_cancel( $state ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="smcq_import_cancel" />
			<input type="hidden" name="smcq_token" value="<?php echo esc_attr( $state['token'] ); ?>" />
			<?php wp_nonce_field( 'smcq_import_cancel' ); ?>
			<?php submit_button( __( 'Stop Future Batches (previously added questions remain)', 'smart-mcq-manager' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	private static function render_errors( $errors ) {
		if ( empty( $errors ) ) { return; }
		echo '<p><strong>' . esc_html__( 'First validation errors:', 'smart-mcq-manager' ) . '</strong></p><ul class="ul-disc">';
		foreach ( array_slice( $errors, 0, 15 ) as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul>';
	}

	/** Commit a maximum of 40 mapped and validated rows per request. */
	public static function commit_action() {
		self::authorize( 'smcq_import_commit' );
		$state = get_transient( self::state_key() );
		$token = isset( $_POST['smcq_token'] ) && is_scalar( $_POST['smcq_token'] ) ? sanitize_text_field( wp_unslash( $_POST['smcq_token'] ) ) : '';
		if ( ! is_array( $state ) || ! isset( $state['token'] ) || ! hash_equals( $state['token'], $token ) ) {
			self::redirect_error( 'expired' );
		}
		if ( empty( $state['mapping'] ) ) {
			$raw = isset( $_POST['smcq_map'] ) && is_array( $_POST['smcq_map'] ) ? wp_unslash( $_POST['smcq_map'] ) : array();
			$mapping = self::validate_mapping( $raw, count( $state['headers'] ) );
			if ( is_wp_error( $mapping ) ) {
				self::redirect_error( 'bad_mapping' );
			}
			$state['mapping'] = $mapping;
			$state['status'] = isset( $_POST['smcq_import_status'] ) && 'publish' === $_POST['smcq_import_status'] ? 'publish' : 'draft';
		}
		$length = count( $state['rows'] );
		$end = min( $length, $state['offset'] + self::BATCH_SIZE );
		for ( $index = $state['offset']; $index < $end; $index++ ) {
			$entry = $state['rows'][ $index ];
			$row = self::map_row( $entry['cells'], $state['mapping'] );
			$checked = self::validate_row( $row );
			if ( is_wp_error( $checked ) ) {
				$state['counts']['invalid']++;
				if ( count( $state['errors'] ) < 15 ) {
					$state['errors'][] = sprintf( /* translators: 1: source row, 2: validation error */ __( 'Row %1$d: %2$s', 'smart-mcq-manager' ), $entry['line'], $checked->get_error_message() );
				}
				continue;
			}
			$hash = self::question_hash( $checked['question'] );
			if ( self::is_duplicate( $checked['question'], $hash ) ) {
				$state['counts']['duplicates']++;
				continue;
			}
			$new_id = self::insert_question( $checked, $hash, $state['status'] );
			if ( is_wp_error( $new_id ) ) {
				$state['counts']['invalid']++;
				if ( count( $state['errors'] ) < 15 ) {
					$state['errors'][] = sprintf( /* translators: 1: source row, 2: WordPress error */ __( 'Row %1$d: %2$s', 'smart-mcq-manager' ), $entry['line'], $new_id->get_error_message() );
				}
				continue;
			}
			$state['counts']['added']++;
		}
		$state['offset'] = $end;
		if ( $end === $length ) {
			set_transient( self::report_key(), array_merge( $state['counts'], array( 'errors' => $state['errors'] ) ), self::TTL );
			delete_transient( self::state_key() ); // Delete answers and imported rows as soon as finished.
		} else {
			set_transient( self::state_key(), $state, self::TTL );
		}
		wp_safe_redirect( self::page_url() );
		exit;
	}

	private static function validate_mapping( $raw, $column_count ) {
		$mapping = array();
		$seen = array();
		foreach ( self::field_labels() as $field => $label ) {
			$value = isset( $raw[ $field ] ) && is_scalar( $raw[ $field ] ) ? (string) $raw[ $field ] : '';
			if ( '' === $value ) { continue; }
			if ( ! ctype_digit( $value ) || (int) $value >= $column_count || isset( $seen[ (int) $value ] ) ) {
				return new WP_Error( 'bad_mapping', __( 'Invalid or reused column.', 'smart-mcq-manager' ) );
			}
			$mapping[ $field ] = (int) $value;
			$seen[ (int) $value ] = true;
		}
		foreach ( self::required_fields() as $field ) {
			if ( ! isset( $mapping[ $field ] ) ) {
				return new WP_Error( 'bad_mapping', __( 'Missing a required field.', 'smart-mcq-manager' ) );
			}
		}
		return $mapping;
	}

	private static function map_row( $cells, $mapping ) {
		$row = array();
		foreach ( self::field_labels() as $field => $label ) {
			$row[ $field ] = isset( $mapping[ $field ], $cells[ $mapping[ $field ] ] ) ? (string) $cells[ $mapping[ $field ] ] : '';
		}
		return $row;
	}

	/** Strict validation so malformed rows never enter the question bank. */
	private static function validate_row( $row ) {
		foreach ( self::required_fields() as $field ) {
			$row[ $field ] = sanitize_text_field( trim( $row[ $field ] ) );
			if ( '' === $row[ $field ] ) {
				return new WP_Error( 'required', sprintf( /* translators: %s: field key */ __( 'Missing %s.', 'smart-mcq-manager' ), $field ) );
			}
			if ( strlen( $row[ $field ] ) > 10000 ) {
				return new WP_Error( 'length', __( 'Question or option text is too long.', 'smart-mcq-manager' ) );
			}
		}
        $type = strtolower( trim( $row['question_type'] ) ) ?: 'single';
        if ( ! in_array( $type, array( 'single', 'multiple', 'true_false', 'passage', 'math' ), true ) ) {
            return new WP_Error( 'question_type', __( 'Unsupported imported question type.', 'smart-mcq-manager' ) );
        }
        $row['question_type'] = $type;
        $row['correct'] = strtoupper( preg_replace( '/\s+/', '', $row['correct'] ) );
        $row['passage'] = sanitize_textarea_field( $row['passage'] );
        $options = array( 'A' => $row['option_a'], 'B' => $row['option_b'], 'C' => $row['option_c'], 'D' => $row['option_d'] );
        $answer = SMCQ_Question_Types::canonical( $row['correct'], $type, $options );
        if ( null === $answer || ! SMCQ_Question_Types::ready( $type, $options, $answer, $row['passage'] ) ) {
            return new WP_Error( 'answer', __( 'Invalid options, answer combination, or passage for this question type.', 'smart-mcq-manager' ) );
        }
        $row['correct'] = $answer;
		$row['difficulty'] = strtolower( trim( $row['difficulty'] ) ) ?: 'easy';
		$row['language'] = strtolower( trim( $row['language'] ) ) ?: 'bn';
		if ( ! in_array( $row['difficulty'], array( 'easy', 'medium', 'hard' ), true ) ) {
			return new WP_Error( 'difficulty', __( 'Difficulty must be easy, medium or hard.', 'smart-mcq-manager' ) );
		}
		if ( ! in_array( $row['language'], array( 'bn', 'en', 'mixed' ), true ) ) {
			return new WP_Error( 'language', __( 'Language must be bn, en or mixed.', 'smart-mcq-manager' ) );
		}
		$year = trim( $row['exam_year'] );
		if ( '' !== $year && ( ! ctype_digit( $year ) || (int) $year < 1900 || (int) $year > 2100 ) ) {
			return new WP_Error( 'year', __( 'Exam year must be between 1900 and 2100.', 'smart-mcq-manager' ) );
		}
		$row['exam_year'] = $year;
		$marks = '' === trim( $row['marks'] ) ? '1' : trim( $row['marks'] );
		$negative = '' === trim( $row['negative_marks'] ) ? '0' : trim( $row['negative_marks'] );
		if ( ! is_numeric( $marks ) || ! is_numeric( $negative ) || ! is_finite( (float) $marks ) || ! is_finite( (float) $negative ) || (float) $marks < 0.01 || (float) $marks > 1000 || (float) $negative < 0 || (float) $negative > (float) $marks ) {
			return new WP_Error( 'marks', __( 'Invalid marks or negative marks.', 'smart-mcq-manager' ) );
		}
		$row['marks'] = round( (float) $marks, 2 );
		$row['negative_marks'] = round( (float) $negative, 2 );
		$row['explanation'] = wp_kses_post( $row['explanation'] );
		foreach ( array( 'subject', 'topic', 'exam_source', 'tags', 'reference' ) as $field ) {
			$row[ $field ] = sanitize_text_field( $row[ $field ] );
		}
		$row['reference_url'] = trim( $row['reference_url'] );
		if ( '' !== $row['reference_url'] && ! wp_http_validate_url( $row['reference_url'] ) ) {
			return new WP_Error( 'url', __( 'Invalid reference URL.', 'smart-mcq-manager' ) );
		}
		return $row;
	}

	private static function question_hash( $title ) {
		$title = trim( preg_replace( '/\s+/u', ' ', $title ) );
		$title = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
		return hash( 'sha256', $title );
	}

	private static function is_duplicate( $title, $hash ) {
		global $wpdb;
		// Hash captures normalized titles imported in this or future versions.
		$found = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft') AND m.meta_key = %s AND m.meta_value = %s LIMIT 1",
			'smcq_question', '_smcq_question_hash', $hash
		) );
		if ( $found ) { return true; }
		// Legacy Step 1/2 questions have no hash, so check their exact original title.
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft') AND post_title = %s LIMIT 1",
			'smcq_question', $title
		) );
	}

	private static function insert_question( $row, $hash, $status ) {
		$post_id = wp_insert_post( array(
			'post_type'   => 'smcq_question',
			'post_status' => $status,
			'post_title'  => $row['question'],
			'post_author' => get_current_user_id(),
		), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'insert_failed', __( 'Could not create this question.', 'smart-mcq-manager' ) );
		}
		update_post_meta( $post_id, '_smcq_options', array( 'A' => $row['option_a'], 'B' => $row['option_b'], 'C' => $row['option_c'], 'D' => $row['option_d'] ) );
        update_post_meta( $post_id, '_smcq_type', $row['question_type'] );
        update_post_meta( $post_id, '_smcq_passage', $row['passage'] );
        if ( 'multiple' === $row['question_type'] ) {
            update_post_meta( $post_id, '_smcq_correct_keys', explode( ',', $row['correct'] ) );
            update_post_meta( $post_id, '_smcq_correct', explode( ',', $row['correct'] )[0] );
        } else {
            update_post_meta( $post_id, '_smcq_correct', $row['correct'] );
        }
		update_post_meta( $post_id, '_smcq_explanation', $row['explanation'] );
		update_post_meta( $post_id, '_smcq_difficulty', $row['difficulty'] );
		update_post_meta( $post_id, '_smcq_language', $row['language'] );
		if ( $row['exam_year'] ) { update_post_meta( $post_id, '_smcq_exam_year', (int) $row['exam_year'] ); }
		update_post_meta( $post_id, '_smcq_reference', $row['reference'] );
		update_post_meta( $post_id, '_smcq_reference_url', esc_url_raw( $row['reference_url'], array( 'http', 'https' ) ) );
		update_post_meta( $post_id, '_smcq_marks', $row['marks'] );
		update_post_meta( $post_id, '_smcq_negative_marks', $row['negative_marks'] );
		update_post_meta( $post_id, '_smcq_question_hash', $hash );
		foreach ( array( 'subject' => 'smcq_subject', 'topic' => 'smcq_topic', 'exam_source' => 'smcq_exam_source', 'tags' => 'smcq_question_tag' ) as $field => $taxonomy ) {
			$names = 'tags' === $field ? array_slice( array_map( 'trim', explode( '|', $row[ $field ] ) ), 0, 20 ) : array( trim( $row[ $field ] ) );
			$ids = array();
			foreach ( $names as $name ) {
				if ( '' === $name ) { continue; }
				$term = term_exists( $name, $taxonomy );
				if ( ! $term ) { $term = wp_insert_term( $name, $taxonomy ); }
				if ( ! is_wp_error( $term ) && $term ) { $ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term ); }
			}
			if ( $ids ) { wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $taxonomy ); }
		}
		return $post_id;
	}

	public static function cancel_action() {
		self::authorize( 'smcq_import_cancel' );
		$state = get_transient( self::state_key() );
		$token = isset( $_POST['smcq_token'] ) && is_scalar( $_POST['smcq_token'] ) ? sanitize_text_field( wp_unslash( $_POST['smcq_token'] ) ) : '';
		if ( is_array( $state ) && isset( $state['token'] ) && hash_equals( $state['token'], $token ) ) {
			delete_transient( self::state_key() );
		}
		wp_safe_redirect( self::page_url() );
		exit;
	}

	public static function template_action() {
		self::authorize( 'smcq_import_template' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="smart-mcq-import-template.csv"' );
		$file = new SplTempFileObject();
		$file->fputcsv( array_keys( self::field_labels() ) );
		$file->fputcsv( array( 'What is the capital of Bangladesh?', 'Dhaka', 'Khulna', 'Rajshahi', 'Sylhet', 'A', 'single', '', 'Dhaka is the capital.', 'General Knowledge', 'Bangladesh', 'BCS', 'Geography|Bangladesh', 'easy', 'en', '2025', 'General Knowledge Book', 'https://example.org/', '1', '0.25' ) );
		$file->fputcsv( array( 'বাংলাদেশের রাজধানী কোনটি?', 'ঢাকা', 'চট্টগ্রাম', 'খুলনা', 'সিলেট', 'A', 'single', '', 'ঢাকা বাংলাদেশের রাজধানী।', 'সাধারণ জ্ঞান', 'বাংলাদেশ বিষয়াবলি', 'বিসিএস', 'বাংলাদেশ', 'easy', 'bn', '2026', '', '', '1', '0' ) );
		$file->rewind();
		echo "\xEF\xBB\xBF"; // UTF-8 BOM for spreadsheet applications.
		while ( ! $file->eof() ) { echo $file->fgets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV attachment bytes, not HTML.
		}
		exit;
	}

	private static function truncate( $text, $limit ) {
		return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( (string) $text, 0, $limit, '…', 'UTF-8' ) : substr( (string) $text, 0, $limit );
	}

	/** Decode structured, UTF-8 JSON arrays into the existing preview-and-map importer. */
	private static function parse_json( $path ) {
		if ( ! is_file( $path ) || filesize( $path ) > self::MAX_BYTES ) { return new WP_Error( 'file', 'Invalid JSON file size.' ); }
		$raw = file_get_contents( $path );
		if ( false === $raw || ! preg_match( '//u', $raw ) ) { return new WP_Error( 'utf8', 'Invalid UTF-8 JSON.' ); }
		$data = json_decode( $raw, true, 16 );
		if ( ! is_array( $data ) ) { return new WP_Error( 'json', 'JSON must be a list of question objects.' ); }
		if ( isset( $data['questions'] ) ) { $data = $data['questions']; }
		if ( ! is_array( $data ) || ! $data || count( $data ) > self::MAX_ROWS || array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) {
			return new WP_Error( 'json_shape', 'Provide a list of up to 500 question objects.' );
		}
		$headers = array_keys( self::field_labels() );
		$rows = array();
		foreach ( $data as $i => $record ) {
			if ( ! is_array( $record ) ) { return new WP_Error( 'json_item', 'Every question must be an object.' ); }
			$cells = array();
			foreach ( $headers as $key ) {
				$value = isset( $record[ $key ] ) ? $record[ $key ] : '';
				if ( ! is_scalar( $value ) && null !== $value ) { return new WP_Error( 'json_value', 'Nested objects or lists are not permitted in question fields.' ); }
				$cells[] = null === $value ? '' : (string) $value;
			}
			$rows[] = array( 'line' => $i + 1, 'cells' => $cells );
		}
		return self::finalize_rows( $headers, $rows );
	}

    /** DOCX table import. Expects the first row of the first table to contain column names. */
    public static function parse_docx( $path ) {
        if ( ! class_exists( 'ZipArchive' ) || ! class_exists( 'DOMDocument' ) ) {
            return new WP_Error( 'extensions', 'DOCX import needs PHP ZIP and DOM extensions.' );
        }
        if ( ! is_file( $path ) || filesize( $path ) > self::MAX_BYTES ) { return new WP_Error( 'size', 'Invalid DOCX size.' ); }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path ) ) { return new WP_Error( 'zip', 'Cannot open DOCX.' ); }
        try {
            if ( $zip->numFiles > 750 ) { return new WP_Error( 'entries', 'Too many DOCX components.' ); }
            $stat = $zip->statName( 'word/document.xml' );
            if ( ! $stat || $stat['size'] > 8 * 1024 * 1024 ) { return new WP_Error( 'xml_size', 'Missing or oversized Word document.' ); }
            $xml = $zip->getFromName( 'word/document.xml' );
            return false !== $xml ? self::parse_docx_xml( $xml ) : new WP_Error( 'xml_missing', 'Missing Word document XML.' );
        } finally { $zip->close(); }
    }
    /** Public pure-ish parser for deterministic XML regression testing. No external entities. */
    public static function parse_docx_xml( $xml ) {
        if ( ! class_exists( 'DOMDocument' ) || ! is_string( $xml ) || strlen( $xml ) > 8 * 1024 * 1024 || stripos( $xml, '<!DOCTYPE' ) !== false || stripos( $xml, '<!ENTITY' ) !== false ) {
            return new WP_Error( 'docx_xml', 'Invalid Word document XML.' );
        }
        $doc = new DOMDocument();
        $before = libxml_use_internal_errors( true );
        $valid = $doc->loadXML( $xml, LIBXML_NONET );
        libxml_clear_errors(); libxml_use_internal_errors( $before );
        if ( ! $valid ) { return new WP_Error( 'docx_parse', 'Could not read Word document XML.' ); }
        $xp = new DOMXPath( $doc );
        $xp->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
        $tables = $xp->query( '//w:body/w:tbl' );
        if ( ! $tables || ! $tables->length ) { return new WP_Error( 'docx_table', 'Add a Word table with field names in the first row.' ); }
        $headers = array(); $rows = array(); $line = 0;
        foreach ( $xp->query( './w:tr', $tables->item(0) ) as $tr ) {
            ++$line; $cells = array();
            foreach ( $xp->query( './w:tc', $tr ) as $tc ) {
                $chunks = array();
                foreach ( $xp->query( './/w:t', $tc ) as $text ) { $chunks[] = $text->textContent; }
                $cells[] = trim( implode( ' ', $chunks ) );
                if ( count( $cells ) > self::MAX_COLS ) { return new WP_Error( 'docx_columns', 'Too many Word table columns.' ); }
            }
            if ( ! $headers ) { $headers = $cells; continue; }
            if ( ! array_filter( $cells, 'strlen' ) ) { continue; }
            $rows[] = array( 'line' => $line, 'cells' => $cells );
            if ( count( $rows ) > self::MAX_ROWS ) { return new WP_Error( 'docx_rows', 'Too many Word table rows.' ); }
        }
        return self::finalize_rows( $headers, $rows );
    }

	/** Parse bounded RFC 4180 CSV with PHP's SPL CSV reader, including quoted line breaks. */
	private static function parse_csv( $path ) {
		if ( ! is_file( $path ) || filesize( $path ) > self::MAX_BYTES ) {
			return new WP_Error( 'file', 'Invalid CSV file size.' );
		}
		try { $fp = new SplFileObject( $path, 'rb' ); }
		catch ( RuntimeException $error ) { return new WP_Error( 'file', 'Cannot open CSV.' ); }
		$sample = $fp->fgets();
		if ( false === $sample || '' === $sample ) { return new WP_Error( 'empty', 'Empty CSV.' ); }
		$sample = preg_replace( '/^\xEF\xBB\xBF/', '', $sample );
		if ( false !== strpos( $sample, "\0" ) ) { return new WP_Error( 'encoding', 'Invalid CSV encoding.' ); }
		$delimiter = ',';
		$highest = -1;
		foreach ( array( ',', ';', "\t" ) as $candidate ) {
			$count = count( str_getcsv( $sample, $candidate, '"', '' ) );
			if ( $count > $highest ) { $delimiter = $candidate; $highest = $count; }
		}
		$fp->rewind();
		$headers = $fp->fgetcsv( $delimiter, '"', '' );
		if ( ! is_array( $headers ) ) { return new WP_Error( 'empty', 'Missing header row.' ); }
		$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] );
		$lines = array();
		$number = 1;
		while ( ! $fp->eof() ) {
			$cells = $fp->fgetcsv( $delimiter, '"', '' );
			if ( false === $cells ) { break; }
			++$number;
			if ( 1 === count( $cells ) && ( null === $cells[0] || '' === trim( $cells[0] ) ) ) { continue; }
			$lines[] = array( 'line' => $number, 'cells' => $cells );
			if ( count( $lines ) > self::MAX_ROWS ) { return new WP_Error( 'limit', 'Too many rows.' ); }
		}
		return self::finalize_rows( $headers, $lines );
	}

	/** Read common XLSX files without executing spreadsheet formulas or macros. */
	private static function parse_xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'extensions', 'XLSX requires PHP ZIP and SimpleXML.' );
		}
		try {
			$fp = new SplFileObject( $path, 'rb' );
			$magic = $fp->fread( 4 );
		} catch ( RuntimeException $error ) {
			return new WP_Error( 'file', 'Cannot read XLSX file.' );
		}
		if ( "PK\x03\x04" !== $magic ) { return new WP_Error( 'format', 'Not a valid XLSX archive.' ); }
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) { return new WP_Error( 'zip', 'Could not open XLSX.' ); }
		try {
			if ( $zip->numFiles > 500 ) { return new WP_Error( 'zip_count', 'Too many XLSX components.' ); }
			$total = 0;
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$stat = $zip->statIndex( $i );
				if ( ! $stat || (int) $stat['size'] > 12 * 1024 * 1024 ) { return new WP_Error( 'zip_size', 'Oversized XLSX component.' ); }
				$total += (int) $stat['size'];
				if ( $total > 24 * 1024 * 1024 ) { return new WP_Error( 'zip_total', 'Oversized XLSX.' ); }
			}
			$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
			$strings = array();
			$xml = self::zip_xml( $zip, 'xl/sharedStrings.xml', false );
			if ( is_wp_error( $xml ) ) { return $xml; }
			if ( $xml ) {
				foreach ( $xml->children( $ns )->si as $item ) {
					$text = '';
					$item->registerXPathNamespace( 's', $ns );
					foreach ( $item->xpath( './/s:t' ) as $chunk ) { $text .= (string) $chunk; }
					$strings[] = $text;
					if ( count( $strings ) > 15000 ) { return new WP_Error( 'strings', 'Too many shared strings.' ); }
				}
			}
			$sheet_path = 'xl/worksheets/sheet1.xml';
			$workbook = self::zip_xml( $zip, 'xl/workbook.xml', false );
			$relationships = self::zip_xml( $zip, 'xl/_rels/workbook.xml.rels', false );
			if ( is_wp_error( $workbook ) || is_wp_error( $relationships ) ) { return new WP_Error( 'workbook', 'Invalid workbook XML.' ); }
			if ( $workbook && $relationships ) {
				$sheets = $workbook->children( $ns )->sheets;
				if ( $sheets && isset( $sheets->sheet[0] ) ) {
					$attrs = $sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
					$rel_id = (string) $attrs['id'];
					foreach ( $relationships->children( 'http://schemas.openxmlformats.org/package/2006/relationships' )->Relationship as $rel ) {
						$attributes = $rel->attributes();
						if ( (string) $attributes['Id'] === $rel_id ) {
							$target = (string) $attributes['Target'];
							if ( 1 === preg_match( '~^(?:/?xl/)?worksheets/[A-Za-z0-9_/-]+\.xml$~', $target ) && false === strpos( $target, '..' ) ) {
								$sheet_path = ( 0 === strpos( $target, '/xl/' ) ) ? substr( $target, 1 ) : ( 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target );
							}
							break;
						}
					}
					}
				}
			$sheet = self::zip_xml( $zip, $sheet_path, true );
			if ( is_wp_error( $sheet ) ) { return $sheet; }
			$headers = array();
			$rows = array();
			$source_row = 0;
			foreach ( $sheet->children( $ns )->sheetData->row as $row ) {
				$source_row++;
				$index = (int) $row['r'];
				if ( ! $index ) { $index = $source_row; }
				$cells = array();
				$position = 0;
				foreach ( $row->c as $cell ) {
					$reference = (string) $cell['r'];
					if ( preg_match( '/^([A-Z]+)\d+$/', $reference, $match ) ) {
						$position = self::column_number( $match[1] );
					}
					if ( $position >= self::MAX_COLS ) { return new WP_Error( 'cols', 'Too many XLSX columns.' ); }
					if ( isset( $cell->f ) ) { return new WP_Error( 'formula', 'Formula cells are not supported; paste values instead.' ); }
					$type = (string) $cell['t'];
					$value = (string) $cell->v;
					if ( 's' === $type ) { $value = isset( $strings[ (int) $value ] ) ? $strings[ (int) $value ] : ''; }
					if ( 'inlineStr' === $type ) {
						$value = '';
						$inline = $cell->is;
						if ( $inline ) {
							$inline->registerXPathNamespace( 's', $ns );
							foreach ( $inline->xpath( './/s:t' ) as $chunk ) { $value .= (string) $chunk; }
						}
					}
					$cells[ $position ] = $value;
					$position++;
				}
				if ( ! $cells ) { continue; }
				$width = max( array_keys( $cells ) ) + 1;
				$dense = array();
				for ( $j = 0; $j < $width; $j++ ) { $dense[] = isset( $cells[ $j ] ) ? $cells[ $j ] : ''; }
				if ( ! $headers ) { $headers = $dense; continue; }
				if ( ! array_filter( $dense, 'strlen' ) ) { continue; }
				$rows[] = array( 'line' => $index, 'cells' => $dense );
				if ( count( $rows ) > self::MAX_ROWS ) { return new WP_Error( 'limit', 'Too many XLSX rows.' ); }
			}
			return self::finalize_rows( $headers, $rows );
		} finally {
			$zip->close();
		}
	}

	private static function column_number( $letters ) {
		$number = 0;
		foreach ( str_split( $letters ) as $letter ) {
			$number = $number * 26 + ord( $letter ) - 64;
		}
		return $number - 1;
	}

	private static function zip_xml( $zip, $name, $required ) {
		$contents = $zip->getFromName( $name );
		if ( false === $contents ) { return $required ? new WP_Error( 'xml_missing', 'Missing XLSX sheet.' ) : null; }
		if ( false !== stripos( $contents, '<!DOCTYPE' ) || false !== stripos( $contents, '<!ENTITY' ) ) { return new WP_Error( 'xml_dtd', 'Unsupported XLSX XML.' ); }
		$previous = libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $contents, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return false === $xml ? new WP_Error( 'xml', 'Invalid XLSX XML.' ) : $xml;
	}

	private static function finalize_rows( $headers, $rows ) {
		if ( count( $headers ) < 6 || count( $headers ) > self::MAX_COLS || ! $rows || count( $rows ) > self::MAX_ROWS ) {
			return new WP_Error( 'shape', 'Invalid file dimensions.' );
		}
		$width = count( $headers );
		foreach ( $headers as $header ) {
			if ( ! is_string( $header ) || ! preg_match( '//u', $header ) || strlen( $header ) > 180 ) { return new WP_Error( 'header', 'Invalid header encoding or length.' ); }
		}
		foreach ( $rows as &$row ) {
			if ( count( $row['cells'] ) > $width ) { return new WP_Error( 'columns', 'Data row has extra columns.' ); }
			$row['cells'] = array_pad( $row['cells'], $width, '' );
			foreach ( $row['cells'] as $cell ) {
				if ( ! is_string( $cell ) || strlen( $cell ) > 16000 || ! preg_match( '//u', $cell ) || false !== strpos( $cell, "\0" ) ) {
					return new WP_Error( 'value', 'Invalid UTF-8, NUL byte, or cell too long.' );
				}
			}
		}
		unset( $row );
		return array( 'headers' => array_values( $headers ), 'rows' => $rows );
	}
}