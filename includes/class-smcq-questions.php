<?php
/**
 * Step 2-3: Admin question bank. Stores the original Step 1 keys unchanged.
 * Future examination endpoints MUST enforce access restrictions to answer keys.
 *
 * @package SmartMCQManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SMCQ_Questions {
	const POST_TYPE = 'smcq_question';

	/** Register the plugin's hooks. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/** Register a private question post type and its classifications. */
	public static function register() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'          => __( 'MCQ Questions', 'smart-mcq-manager' ),
				'singular_name' => __( 'MCQ Question', 'smart-mcq-manager' ),
				'add_new_item'  => __( 'Add MCQ Question', 'smart-mcq-manager' ),
				'edit_item'     => __( 'Edit MCQ Question', 'smart-mcq-manager' ),
				'menu_name'     => __( 'Smart MCQ', 'smart-mcq-manager' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'menu_icon'           => 'dashicons-welcome-learn-more',
			'supports'            => array( 'title', 'revisions' ),
			'map_meta_cap'       => true,
            'capability_type' => array( 'smcq_question', 'smcq_questions' ),
            'capabilities' => array( 'create_posts' => 'create_smcq_questions' ),
		) );

		$taxonomies = array(
			'smcq_subject'      => array( __( 'Subjects', 'smart-mcq-manager' ), __( 'Subject', 'smart-mcq-manager' ), true ),
			'smcq_topic'        => array( __( 'Topics', 'smart-mcq-manager' ), __( 'Topic', 'smart-mcq-manager' ), true ),
			'smcq_exam_source' => array( __( 'Exam Sources', 'smart-mcq-manager' ), __( 'Exam Source', 'smart-mcq-manager' ), true ),
			'smcq_question_tag' => array( __( 'Question Tags', 'smart-mcq-manager' ), __( 'Question Tag', 'smart-mcq-manager' ), false ),
		);
		foreach ( $taxonomies as $taxonomy => $labels ) {
			register_taxonomy( $taxonomy, array( self::POST_TYPE ), array(
				'labels' => array(
					'name'          => $labels[0],
					'singular_name' => $labels[1],
				),
				'hierarchical'      => $labels[2],
				'public'            => false,
				'publicly_queryable' => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
                'capabilities' => array( 'manage_terms' => 'publish_smcq_questions', 'edit_terms' => 'publish_smcq_questions', 'delete_terms' => 'publish_smcq_questions', 'assign_terms' => 'edit_smcq_questions' ),
			) );
		}
	}

	/** Register editor panels. */
	public static function meta_boxes() {
		add_meta_box( 'smcq_details', __( 'Answer Options & Explanation', 'smart-mcq-manager' ), array( __CLASS__, 'render_answers' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'smcq_advanced', __( 'Question Details', 'smart-mcq-manager' ), array( __CLASS__, 'render_advanced' ), self::POST_TYPE, 'normal', 'default' );
	}

	/** Keep the Step 1 A-D options, correct key, and explanation meta keys. */
	public static function render_answers( $post ) {
		wp_nonce_field( 'smcq_save_question', 'smcq_question_nonce' );
		$options = get_post_meta( $post->ID, '_smcq_options', true );
		$options = is_array( $options ) ? $options : array();
		$type = SMCQ_Question_Types::type( get_post_meta( $post->ID, '_smcq_type', true ) );
		$correct = (string) get_post_meta( $post->ID, '_smcq_correct', true );
		$multi = get_post_meta( $post->ID, '_smcq_correct_keys', true );
		$multi = is_array( $multi ) ? $multi : array();
		$explanation = (string) get_post_meta( $post->ID, '_smcq_explanation', true );

		echo '<p><label for="smcq_type"><strong>' . esc_html__( 'Question type', 'smart-mcq-manager' ) . '</strong></label> <select id="smcq_type" name="smcq_type">';
		foreach ( SMCQ_Question_Types::types() as $slug => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $slug ), selected( $type, $slug, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		echo '<p class="description">' . esc_html__( 'True/False uses only choices A and B. Multiple-answer questions need at least two correct options. Existing questions remain Single Answer.', 'smart-mcq-manager' ) . '</p>';
		echo '<div class="smcq-option-grid">';
		foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
			$value = isset( $options[ $letter ] ) && is_scalar( $options[ $letter ] ) ? $options[ $letter ] : '';
			printf(
				'<p><label for="smcq_option_%1$s"><strong>%1$s.</strong></label><input class="widefat smcq-option" id="smcq_option_%1$s" name="smcq_options[%1$s]" type="text" value="%2$s" /></p>',
				esc_attr( $letter ),
				esc_attr( $value )
			);
		}
		echo '</div>';
		echo '<p><label for="smcq_correct"><strong>' . esc_html__( 'Correct answer', 'smart-mcq-manager' ) . '</strong></label> ';
		echo '<select id="smcq_correct" name="smcq_correct"><option value="">' . esc_html__( 'Select an answer', 'smart-mcq-manager' ) . '</option>';
		foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $letter ), selected( $correct, $letter, false ) );
		}
		echo '</select></p>';
		echo '<p id="smcq_multi_choices"><strong>' . esc_html__( 'Correct choices (select two or more for Multiple)', 'smart-mcq-manager' ) . '</strong> ';
		foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
			printf( '<label style="margin-right:16px"><input type="checkbox" name="smcq_correct_keys[]" value="%s" %s> %s</label>', esc_attr( $letter ), checked( in_array( $letter, $multi, true ), true, false ), esc_html( $letter ) );
		}
		echo '</p>';
		echo '<p id="smcq-answer-readiness" class="description" role="status" aria-live="polite"></p>';
		echo '<p><strong>' . esc_html__( 'Answer explanation', 'smart-mcq-manager' ) . '</strong></p>';
		wp_editor( $explanation, 'smcq_explanation', array(
			'textarea_name' => 'smcq_explanation',
			'textarea_rows' => 7,
			'media_buttons' => false,
			'teeny'         => true,
		) );
	}

	/** Additional searchable and filterable exam question metadata. */
	public static function render_advanced( $post ) {
		$difficulty = (string) get_post_meta( $post->ID, '_smcq_difficulty', true );
		$language = (string) get_post_meta( $post->ID, '_smcq_language', true );
		$year = (string) get_post_meta( $post->ID, '_smcq_exam_year', true );
		$reference = (string) get_post_meta( $post->ID, '_smcq_reference', true );
		$url = (string) get_post_meta( $post->ID, '_smcq_reference_url', true );
		$marks = get_post_meta( $post->ID, '_smcq_marks', true );
		$negative = get_post_meta( $post->ID, '_smcq_negative_marks', true );
		$marks = '' === $marks ? '1' : $marks;
		$negative = '' === $negative ? '0' : $negative;
		?>
		<div class="smcq-fields">
			<p><label for="smcq_difficulty"><strong><?php esc_html_e( 'Difficulty', 'smart-mcq-manager' ); ?></strong></label>
				<select id="smcq_difficulty" name="smcq_difficulty">
					<?php foreach ( self::difficulties() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $difficulty ?: 'easy', $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<p><label for="smcq_language"><strong><?php esc_html_e( 'Language', 'smart-mcq-manager' ); ?></strong></label>
				<select id="smcq_language" name="smcq_language">
					<?php foreach ( self::languages() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $language ?: 'bn', $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<p><label for="smcq_exam_year"><strong><?php esc_html_e( 'Exam year', 'smart-mcq-manager' ); ?></strong></label>
				<input id="smcq_exam_year" type="number" min="1900" max="2100" name="smcq_exam_year" value="<?php echo esc_attr( $year ); ?>" placeholder="2026" /></p>
			<p><label for="smcq_reference"><strong><?php esc_html_e( 'Book / reference', 'smart-mcq-manager' ); ?></strong></label>
				<input id="smcq_reference" type="text" name="smcq_reference" maxlength="255" value="<?php echo esc_attr( $reference ); ?>" /></p>
			<p><label for="smcq_reference_url"><strong><?php esc_html_e( 'Reference URL', 'smart-mcq-manager' ); ?></strong></label>
				<input id="smcq_reference_url" type="url" name="smcq_reference_url" value="<?php echo esc_url( $url ); ?>" placeholder="https://" /></p>
			<p><label for="smcq_marks"><strong><?php esc_html_e( 'Marks', 'smart-mcq-manager' ); ?></strong></label>
				<input id="smcq_marks" type="number" step="0.01" min="0.01" max="1000" name="smcq_marks" value="<?php echo esc_attr( $marks ); ?>" /></p>
			<p><label for="smcq_negative_marks"><strong><?php esc_html_e( 'Negative marks', 'smart-mcq-manager' ); ?></strong></label>
				<input id="smcq_negative_marks" type="number" step="0.01" min="0" max="1000" name="smcq_negative_marks" value="<?php echo esc_attr( $negative ); ?>" /></p>
		</div>
		<p><label for="smcq_passage"><strong><?php esc_html_e( 'Reading passage (passage questions)', 'smart-mcq-manager' ); ?></strong></label><textarea id="smcq_passage" class="widefat" name="smcq_passage" rows="5"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_smcq_passage', true ) ); ?></textarea></p>
		<p><label for="smcq_media_id"><strong><?php esc_html_e( 'WordPress Media Library attachment ID (image/audio questions)', 'smart-mcq-manager' ); ?></strong></label><input type="number" min="0" id="smcq_media_id" name="smcq_media_id" value="<?php echo esc_attr( (int) get_post_meta( $post->ID, '_smcq_media_id', true ) ); ?>" /> <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Media Library', 'smart-mcq-manager' ); ?></a></p>
		<p class="description"><?php esc_html_e( 'Only published WordPress media attachments matching the question type are used. Do not upload confidential questions as publicly accessible attachments.', 'smart-mcq-manager' ); ?></p>
		<p class="description"><?php esc_html_e( 'Math questions accept Unicode and plain-text formulas. LaTeX rendering is not included yet.', 'smart-mcq-manager' ); ?></p>
		<p class="description"><?php esc_html_e( 'Use the Subject, Topic, Exam Source and Question Tags panels to classify this question. Marking values are stored for future exam-generation steps.', 'smart-mcq-manager' ); ?></p>
		<?php
	}

	/** Verify safe editor requests, then update only the relevant question. */
	public static function save( $post_id, $post ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['smcq_question_nonce'] ) || ! is_string( $_POST['smcq_question_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smcq_question_nonce'] ) ), 'smcq_save_question' ) ) {
			return;
		}
		if ( ! isset( $_POST['smcq_options'] ) || ! is_array( $_POST['smcq_options'] ) ) {
			return;
		}

		$raw = wp_unslash( $_POST['smcq_options'] );
		$options = array();
		foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
			$options[ $letter ] = isset( $raw[ $letter ] ) && is_scalar( $raw[ $letter ] ) ? sanitize_text_field( (string) $raw[ $letter ] ) : '';
		}
		$type = SMCQ_Question_Types::type( self::posted_string( 'smcq_type' ) );
		$raw_multi = isset( $_POST['smcq_correct_keys'] ) && is_array( $_POST['smcq_correct_keys'] ) ? wp_unslash( $_POST['smcq_correct_keys'] ) : array();
		$multi = array_values( array_intersect( array( 'A', 'B', 'C', 'D' ), array_filter( $raw_multi, 'is_string' ) ) );
		$multi_correct = implode( ',', $multi );
		$correct = self::posted_string( 'smcq_correct' );
		if ( ! in_array( $correct, array( 'A', 'B', 'C', 'D' ), true ) || '' === $options[ $correct ] ) {
			$correct = '';
		}
		$explanation = ( isset( $_POST['smcq_explanation'] ) && is_string( $_POST['smcq_explanation'] ) ) ? wp_kses_post( wp_unslash( $_POST['smcq_explanation'] ) ) : '';
		update_post_meta( $post_id, '_smcq_type', $type );
		update_post_meta( $post_id, '_smcq_correct_keys', $multi );
		update_post_meta( $post_id, '_smcq_passage', sanitize_textarea_field( isset( $_POST['smcq_passage'] ) && is_string( $_POST['smcq_passage'] ) ? wp_unslash( $_POST['smcq_passage'] ) : '' ) );
		$media_id = absint( self::posted_string( 'smcq_media_id' ) );
		$media_url = SMCQ_Question_Types::media_url( $media_id, $type );
		update_post_meta( $post_id, '_smcq_media_id', $media_url ? $media_id : 0 );
		update_post_meta( $post_id, '_smcq_options', $options );
		update_post_meta( $post_id, '_smcq_correct', $correct );
		update_post_meta( $post_id, '_smcq_explanation', $explanation );

		$difficulty = self::posted_string( 'smcq_difficulty' );
		$language = self::posted_string( 'smcq_language' );
		update_post_meta( $post_id, '_smcq_difficulty', array_key_exists( $difficulty, self::difficulties() ) ? $difficulty : 'easy' );
		update_post_meta( $post_id, '_smcq_language', array_key_exists( $language, self::languages() ) ? $language : 'bn' );

		$year = self::posted_string( 'smcq_exam_year' );
		if ( '' !== $year && ctype_digit( $year ) && (int) $year >= 1900 && (int) $year <= 2100 ) {
			update_post_meta( $post_id, '_smcq_exam_year', (int) $year );
		} else {
			delete_post_meta( $post_id, '_smcq_exam_year' );
		}
		update_post_meta( $post_id, '_smcq_reference', self::posted_string( 'smcq_reference' ) );
		$url = self::posted_string( 'smcq_reference_url' );
		update_post_meta( $post_id, '_smcq_reference_url', esc_url_raw( $url, array( 'http', 'https' ) ) );

		$marks = self::valid_decimal( self::posted_string( 'smcq_marks' ), 1.0, 0.01, 1000.0 );
		$negative = self::valid_decimal( self::posted_string( 'smcq_negative_marks' ), 0.0, 0.0, $marks );
		update_post_meta( $post_id, '_smcq_marks', $marks );
		update_post_meta( $post_id, '_smcq_negative_marks', $negative );
	}

	/** Obtain safe scalar POST text values only. */
	private static function posted_string( $field ) {
		return isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ? sanitize_text_field( (string) wp_unslash( $_POST[ $field ] ) ) : '';
	}

	/** Clamp an input to the domain, rejecting nonnumeric or nonfinite data. */
	private static function valid_decimal( $raw, $fallback, $minimum, $maximum ) {
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return $fallback;
		}
		$value = (float) $raw;
		return is_finite( $value ) && $value >= $minimum && $value <= $maximum ? round( $value, 2 ) : $fallback;
	}

	private static function difficulties() {
		return array(
			'easy'   => __( 'Easy', 'smart-mcq-manager' ),
			'medium' => __( 'Medium', 'smart-mcq-manager' ),
			'hard'   => __( 'Hard', 'smart-mcq-manager' ),
		);
	}

	private static function languages() {
		return array(
			'bn'    => __( 'Bangla', 'smart-mcq-manager' ),
			'en'    => __( 'English', 'smart-mcq-manager' ),
			'mixed' => __( 'Bangla & English', 'smart-mcq-manager' ),
		);
	}

	/** Add columns to identify valid questions and their classification. */
	public static function columns( $columns ) {
		$columns['smcq_type']       = __( 'Type', 'smart-mcq-manager' );
		$columns['smcq_id']         = __( 'Question ID', 'smart-mcq-manager' );
		$columns['smcq_ready']      = __( 'Ready', 'smart-mcq-manager' );
		$columns['smcq_correct']    = __( 'Correct', 'smart-mcq-manager' );
		$columns['smcq_difficulty'] = __( 'Difficulty', 'smart-mcq-manager' );
		$columns['smcq_language']   = __( 'Language', 'smart-mcq-manager' );
		$columns['smcq_exam_year']  = __( 'Exam Year', 'smart-mcq-manager' );
		return $columns;
	}

	public static function column_value( $column, $post_id ) {
		switch ( $column ) {
			case 'smcq_type':
				echo esc_html( SMCQ_Question_Types::types()[ SMCQ_Question_Types::type( get_post_meta( $post_id, '_smcq_type', true ) ) ] );
				break;
			case 'smcq_id':
				echo esc_html( 'Q-' . str_pad( (string) $post_id, 6, '0', STR_PAD_LEFT ) );
				break;
			case 'smcq_ready':
				$options = get_post_meta( $post_id, '_smcq_options', true );
				$correct = get_post_meta( $post_id, '_smcq_correct', true );
				$type = SMCQ_Question_Types::type( get_post_meta( $post_id, '_smcq_type', true ) );
				$ready = '' !== trim( (string) get_the_title( $post_id ) ) && SMCQ_Question_Types::ready( $type, $options, SMCQ_Question_Types::correct_from_meta( $post_id ), (string) get_post_meta( $post_id, '_smcq_passage', true ), SMCQ_Question_Types::media_url( get_post_meta( $post_id, '_smcq_media_id', true ), $type ) );
				echo $ready ? '<span class="smcq-ready">' . esc_html__( 'Ready', 'smart-mcq-manager' ) . '</span>' : '<span class="smcq-incomplete">' . esc_html__( 'Incomplete', 'smart-mcq-manager' ) . '</span>';
				break;
			case 'smcq_correct':
				echo esc_html( SMCQ_Question_Types::correct_from_meta( $post_id ) ?: '—' );
				break;
			case 'smcq_difficulty':
				$value = get_post_meta( $post_id, '_smcq_difficulty', true ) ?: 'easy';
				echo esc_html( isset( self::difficulties()[ $value ] ) ? self::difficulties()[ $value ] : '—' );
				break;
			case 'smcq_language':
				$value = get_post_meta( $post_id, '_smcq_language', true ) ?: 'bn';
				echo esc_html( isset( self::languages()[ $value ] ) ? self::languages()[ $value ] : '—' );
				break;
			case 'smcq_exam_year':
				echo esc_html( get_post_meta( $post_id, '_smcq_exam_year', true ) ?: '—' );
				break;
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['smcq_exam_year'] = 'smcq_exam_year';
		return $columns;
	}

	/** Native WordPress post-list filters (no custom public API). */
	public static function filters( $post_type, $which = 'top' ) {
		if ( self::POST_TYPE !== $post_type || 'top' !== $which ) {
			return;
		}
		foreach ( array( 'smcq_subject', 'smcq_topic', 'smcq_exam_source' ) as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( ! $tax ) { continue; }
			$name = 'smcq_filter_' . $taxonomy;
			$selected = isset( $_GET[ $name ] ) ? absint( wp_unslash( $_GET[ $name ] ) ) : 0;
			wp_dropdown_categories( array(
				'show_option_all' => sprintf( /* translators: %s: taxonomy label */ __( 'All %s', 'smart-mcq-manager' ), $tax->labels->name ),
				'taxonomy'       => $taxonomy,
				'name'           => $name,
				'value_field'    => 'term_id',
				'selected'       => $selected,
				'hierarchical'   => $tax->hierarchical,
				'hide_empty'     => false,
			) );
		}
		self::filter_select( 'smcq_filter_difficulty', self::difficulties(), __( 'All difficulties', 'smart-mcq-manager' ) );
		self::filter_select( 'smcq_filter_language', self::languages(), __( 'All languages', 'smart-mcq-manager' ) );
		$year = isset( $_GET['smcq_filter_year'] ) ? absint( wp_unslash( $_GET['smcq_filter_year'] ) ) : '';
		printf( '<label class="screen-reader-text" for="smcq_filter_year">%s</label><input id="smcq_filter_year" type="number" name="smcq_filter_year" min="1900" max="2100" value="%s" placeholder="%s" style="width:95px" />',
			esc_html__( 'Exam year', 'smart-mcq-manager' ), esc_attr( $year ?: '' ), esc_attr__( 'Exam year', 'smart-mcq-manager' ) );
	}

	private static function filter_select( $name, $choices, $all_label ) {
		$selected = isset( $_GET[ $name ] ) && is_scalar( $_GET[ $name ] ) ? sanitize_key( wp_unslash( $_GET[ $name ] ) ) : '';
		printf( '<label class="screen-reader-text" for="%1$s">%2$s</label><select id="%1$s" name="%1$s"><option value="">%2$s</option>', esc_attr( $name ), esc_html( $all_label ) );
		foreach ( $choices as $value => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public static function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$meta_query = (array) $query->get( 'meta_query', array() );
		foreach ( array( 'smcq_filter_difficulty' => array( '_smcq_difficulty', self::difficulties() ), 'smcq_filter_language' => array( '_smcq_language', self::languages() ) ) as $filter => $settings ) {
			$value = isset( $_GET[ $filter ] ) && is_scalar( $_GET[ $filter ] ) ? sanitize_key( wp_unslash( $_GET[ $filter ] ) ) : '';
			if ( isset( $settings[1][ $value ] ) ) {
				$meta_query[] = array( 'key' => $settings[0], 'value' => $value, 'compare' => '=' );
			}
		}
		$year = isset( $_GET['smcq_filter_year'] ) && is_scalar( $_GET['smcq_filter_year'] ) ? absint( wp_unslash( $_GET['smcq_filter_year'] ) ) : 0;
		if ( $year >= 1900 && $year <= 2100 ) {
			$meta_query[] = array( 'key' => '_smcq_exam_year', 'value' => $year, 'compare' => '=', 'type' => 'NUMERIC' );
		}
		if ( 'smcq_exam_year' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_smcq_exam_year' );
			$query->set( 'orderby', 'meta_value_num' );
		}
		if ( $meta_query ) { $query->set( 'meta_query', $meta_query ); }
		$tax_query = (array) $query->get( 'tax_query', array() );
		foreach ( array( 'smcq_subject', 'smcq_topic', 'smcq_exam_source' ) as $taxonomy ) {
			$name = 'smcq_filter_' . $taxonomy;
			$term = isset( $_GET[ $name ] ) && is_scalar( $_GET[ $name ] ) ? absint( wp_unslash( $_GET[ $name ] ) ) : 0;
			if ( $term ) {
				$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array( $term ), 'include_children' => true );
			}
		}
		if ( $tax_query ) { $query->set( 'tax_query', $tax_query ); }
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) { return; }
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) { return; }
		wp_enqueue_style( 'smcq-admin', SMCQ_URL . 'assets/admin.css', array(), SMCQ_VERSION );
		if ( 'edit.php' !== $hook ) {
			wp_enqueue_script( 'smcq-admin', SMCQ_URL . 'assets/admin.js', array(), SMCQ_VERSION, true );
		}
	}

	public static function notice() {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) { return; }
		if ( 'edit' === $screen->base ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Filter questions by subject, topic, exam source, difficulty, language and year. Incomplete questions are flagged and should not be used in exams.', 'smart-mcq-manager' ) . '</p></div>';
		}
	}
}