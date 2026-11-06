<?php
/**
 * Step 4: Private exam management and question-set builder.
 * No student access, exam attempts or answer-key REST endpoints are registered.
 *
 * @package SmartMCQManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SMCQ_Exams {
	const POST_TYPE = 'smcq_exam';
	const MAX_QUESTIONS = 200;
	const CANDIDATE_LIMIT = 2000;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_smcq_exam_search_questions', array( __CLASS__, 'search_questions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function register() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'          => __( 'Exams', 'smart-mcq-manager' ),
				'singular_name' => __( 'Exam', 'smart-mcq-manager' ),
				'add_new_item'  => __( 'Add New Exam', 'smart-mcq-manager' ),
				'edit_item'     => __( 'Edit Exam', 'smart-mcq-manager' ),
				'menu_name'     => __( 'Exams', 'smart-mcq-manager' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=smcq_question',
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor' ),
			'map_meta_cap'       => true,
		) );
	}

	public static function meta_boxes() {
		add_meta_box( 'smcq_exam_settings', __( 'Examination Settings', 'smart-mcq-manager' ), array( __CLASS__, 'render_settings' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'smcq_exam_questions', __( 'Question Selection & Generator', 'smart-mcq-manager' ), array( __CLASS__, 'render_questions' ), self::POST_TYPE, 'normal', 'default' );
	}

	/** Pure helper: parse a unique, ordered list of positive IDs. */
	public static function normalize_ids( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}
		if ( ! is_string( $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( preg_split( '/[\s,;]+/', trim( $raw ) ) as $part ) {
			if ( ! preg_match( '/^[0-9]{1,12}$/D', $part ) ) {
				continue;
			}
			$id = (int) $part;
			if ( $id > 0 && ! isset( $ids[ $id ] ) ) {
				$ids[ $id ] = $id;
			}
			if ( count( $ids ) >= self::MAX_QUESTIONS ) {
				break;
			}
		}
		return array_values( $ids );
	}

	/** Pure helper: reject out-of-range/infinite numeric exam settings. */
	public static function decimal( $raw, $default, $min, $max ) {
		if ( ! is_scalar( $raw ) || '' === (string) $raw || ! is_numeric( $raw ) ) {
			return $default;
		}
		$number = (float) $raw;
		return is_finite( $number ) && $number >= $min && $number <= $max ? round( $number, 2 ) : $default;
	}

	private static function meta( $id, $key, $fallback ) {
		$value = get_post_meta( $id, $key, true );
		return '' === $value ? $fallback : $value;
	}

	public static function render_settings( $post ) {
		wp_nonce_field( 'smcq_save_exam', 'smcq_exam_nonce' );
		$duration = self::meta( $post->ID, '_smcq_exam_duration', 60 );
		$pass = self::meta( $post->ID, '_smcq_exam_pass_percent', 40 );
		$attempts = self::meta( $post->ID, '_smcq_exam_attempt_limit', 1 );
		$marking = self::meta( $post->ID, '_smcq_exam_marking', 'question' );
		$positive = self::meta( $post->ID, '_smcq_exam_positive', 1 );
		$negative = self::meta( $post->ID, '_smcq_exam_negative', 0.25 );
		$start = (string) get_post_meta( $post->ID, '_smcq_exam_start', true );
		$end = (string) get_post_meta( $post->ID, '_smcq_exam_end', true );
		?>
		<p class="description"><?php esc_html_e( 'Configure an exam blueprint. Exam time limits, attempt limits, scheduling, and question/option order are enforced for student attempts. Completed attempts are graded automatically using these frozen marking rules.', 'smart-mcq-manager' ); ?></p>
		<div class="smcq-exam-settings-grid">
			<p><label for="smcq_exam_duration"><strong><?php esc_html_e( 'Duration (minutes)', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="number" id="smcq_exam_duration" name="smcq_exam_duration" min="1" max="600" required value="<?php echo esc_attr( $duration ); ?>" /></p>
			<p><label for="smcq_exam_pass_percent"><strong><?php esc_html_e( 'Passing percentage', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="number" id="smcq_exam_pass_percent" name="smcq_exam_pass_percent" min="0" max="100" step="0.01" value="<?php echo esc_attr( $pass ); ?>" /></p>
			<p><label for="smcq_exam_attempt_limit"><strong><?php esc_html_e( 'Attempt limit (0 = unlimited)', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="number" id="smcq_exam_attempt_limit" name="smcq_exam_attempt_limit" min="0" max="1000" value="<?php echo esc_attr( $attempts ); ?>" /></p>
			<p><label for="smcq_exam_marking"><strong><?php esc_html_e( 'Scoring policy', 'smart-mcq-manager' ); ?></strong></label><select class="widefat" id="smcq_exam_marking" name="smcq_exam_marking"><option value="question" <?php selected( $marking, 'question' ); ?>><?php esc_html_e( 'Use individual question marks', 'smart-mcq-manager' ); ?></option><option value="fixed" <?php selected( $marking, 'fixed' ); ?>><?php esc_html_e( 'Use fixed exam marks', 'smart-mcq-manager' ); ?></option></select></p>
			<p><label for="smcq_exam_positive"><strong><?php esc_html_e( 'Fixed marks per correct answer', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="number" id="smcq_exam_positive" name="smcq_exam_positive" min="0.01" max="1000" step="0.01" value="<?php echo esc_attr( $positive ); ?>" /></p>
			<p><label for="smcq_exam_negative"><strong><?php esc_html_e( 'Fixed deduction per wrong answer', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="number" id="smcq_exam_negative" name="smcq_exam_negative" min="0" max="1000" step="0.01" value="<?php echo esc_attr( $negative ); ?>" /></p>
			<p><label for="smcq_exam_start"><strong><?php esc_html_e( 'Scheduled start (optional)', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="datetime-local" id="smcq_exam_start" name="smcq_exam_start" value="<?php echo esc_attr( $start ); ?>" /></p>
			<p><label for="smcq_exam_end"><strong><?php esc_html_e( 'Scheduled end (optional)', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="datetime-local" id="smcq_exam_end" name="smcq_exam_end" value="<?php echo esc_attr( $end ); ?>" /></p>
		</div>
		<p><label><input type="checkbox" name="smcq_exam_shuffle_questions" value="1" <?php checked( self::meta( $post->ID, '_smcq_exam_shuffle_questions', 0 ), 1 ); ?> /> <?php esc_html_e( 'Shuffle question order for each future attempt', 'smart-mcq-manager' ); ?></label></p>
		<p><label><input type="checkbox" name="smcq_exam_shuffle_options" value="1" <?php checked( self::meta( $post->ID, '_smcq_exam_shuffle_options', 0 ), 1 ); ?> /> <?php esc_html_e( 'Shuffle answer order for each future attempt', 'smart-mcq-manager' ); ?></label></p>
		<p><label><input type="checkbox" name="smcq_exam_leaderboard" value="1" <?php checked( self::meta( $post->ID, '_smcq_exam_leaderboard', 0 ), 1 ); ?> /> <?php esc_html_e( 'Publish anonymous top-10 leaderboard after the scheduled end time (requires a closing date)', 'smart-mcq-manager' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Leaderboard is disabled by default. Only logged-in users may see pseudonymous rankings after the scheduled closing time. Shortcode: [smcq_leaderboard id="EXAM_ID"]', 'smart-mcq-manager' ); ?></p>
		<fieldset class="smcq-security-settings"><legend><strong><?php esc_html_e( 'Exam Security (optional)', 'smart-mcq-manager' ); ?></strong></legend>
		<p><label><input type="checkbox" name="smcq_exam_monitor_activity" value="1" <?php checked( self::meta( $post->ID, '_smcq_exam_monitor_activity', 0 ), 1 ); ?> /> <?php esc_html_e( 'Log tab visibility changes, fullscreen exits, and session conflicts (30-day retention)', 'smart-mcq-manager' ); ?></label></p>
		<p><label><input type="checkbox" name="smcq_exam_single_tab" value="1" <?php checked( self::meta( $post->ID, '_smcq_exam_single_tab', 0 ), 1 ); ?> /> <?php esc_html_e( 'Only one active browser tab/device can edit an attempt (manual takeover allowed)', 'smart-mcq-manager' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Monitoring and tab locking are off by default. Visibility events are not proof of cheating. Do not enable single-tab locking if students rely on unsupported browsers or embedded webviews.', 'smart-mcq-manager' ); ?></p></fieldset>
		<p class="description"><?php esc_html_e( 'Dates use the WordPress site timezone (Settings → General). The schedule controls student admission, and the closing time limits each attempt.', 'smart-mcq-manager' ); ?></p>
		<?php
	}

	public static function render_questions( $post ) {
		$ids = get_post_meta( $post->ID, '_smcq_exam_question_ids', true );
		$ids = is_array( $ids ) ? self::normalize_ids( $ids ) : array();
		$subject = (int) self::meta( $post->ID, '_smcq_exam_gen_subject', 0 );
		$topic = (int) self::meta( $post->ID, '_smcq_exam_gen_topic', 0 );
		$source = (int) self::meta( $post->ID, '_smcq_exam_gen_source', 0 );
		$difficulty = (string) self::meta( $post->ID, '_smcq_exam_gen_difficulty', '' );
		$count = (int) self::meta( $post->ID, '_smcq_exam_gen_count', 20 );
		?>
		<p class="description"><?php esc_html_e( 'Only published, complete questions can be assigned. Search to add them individually or generate a fresh random set from the question bank.', 'smart-mcq-manager' ); ?></p>
		<div id="smcq-exam-question-builder" data-max="<?php echo esc_attr( self::MAX_QUESTIONS ); ?>">
			<p><label for="smcq_question_search"><strong><?php esc_html_e( 'Find questions by title or ID', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="search" id="smcq_question_search" placeholder="<?php echo esc_attr__( 'Start typing a question...', 'smart-mcq-manager' ); ?>" autocomplete="off" /></p>
			<div id="smcq_question_search_results" class="smcq-exam-search-results" aria-live="polite"></div>
			<p><strong><?php esc_html_e( 'Selected questions', 'smart-mcq-manager' ); ?>: <span id="smcq_question_count"><?php echo esc_html( count( $ids ) ); ?></span></strong></p>
			<ul id="smcq_selected_list" class="smcq-selected-list">
				<?php foreach ( $ids as $id ) : ?>
					<li data-id="<?php echo esc_attr( $id ); ?>"><span><?php echo esc_html( 'Q-' . $id . ': ' . get_the_title( $id ) ); ?></span> <button type="button" class="button-link smcq-remove-question"><?php esc_html_e( 'Remove', 'smart-mcq-manager' ); ?></button></li>
				<?php endforeach; ?>
			</ul>
			<p><label for="smcq_selected_ids"><strong><?php esc_html_e( 'Question IDs (comma-separated; works without JavaScript)', 'smart-mcq-manager' ); ?></strong></label><textarea class="widefat" rows="2" id="smcq_selected_ids" name="smcq_selected_ids"><?php echo esc_textarea( implode( ',', $ids ) ); ?></textarea></p>
			<p><button type="submit" name="smcq_exam_build_mode" value="manual" class="button button-primary"><?php esc_html_e( 'Save Exam & Selected Questions', 'smart-mcq-manager' ); ?></button></p>
		</div>
		<hr />
		<h3><?php esc_html_e( 'Automatic Question Generator', 'smart-mcq-manager' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Generating replaces the selected list ONLY if enough valid questions are found. Keep the existing selection otherwise. Maximum 200 questions per exam.', 'smart-mcq-manager' ); ?></p>
		<div class="smcq-exam-settings-grid">
			<p><label for="smcq_exam_gen_count"><strong><?php esc_html_e( 'Number of questions', 'smart-mcq-manager' ); ?></strong></label><input class="widefat" type="number" id="smcq_exam_gen_count" name="smcq_exam_gen_count" min="1" max="200" value="<?php echo esc_attr( max( 1, min( self::MAX_QUESTIONS, $count ) ) ); ?>" /></p>
			<?php self::taxonomy_dropdown( 'smcq_subject', 'smcq_exam_gen_subject', $subject, __( 'Subject', 'smart-mcq-manager' ) ); ?>
			<?php self::taxonomy_dropdown( 'smcq_topic', 'smcq_exam_gen_topic', $topic, __( 'Topic', 'smart-mcq-manager' ) ); ?>
			<?php self::taxonomy_dropdown( 'smcq_exam_source', 'smcq_exam_gen_source', $source, __( 'Exam source', 'smart-mcq-manager' ) ); ?>
			<p><label for="smcq_exam_gen_difficulty"><strong><?php esc_html_e( 'Difficulty', 'smart-mcq-manager' ); ?></strong></label><select class="widefat" id="smcq_exam_gen_difficulty" name="smcq_exam_gen_difficulty"><option value=""><?php esc_html_e( 'Any difficulty', 'smart-mcq-manager' ); ?></option><?php foreach ( array( 'easy', 'medium', 'hard' ) as $level ) : ?><option value="<?php echo esc_attr( $level ); ?>" <?php selected( $difficulty, $level ); ?>><?php echo esc_html( ucfirst( $level ) ); ?></option><?php endforeach; ?></select></p>
		</div>
		<p><button type="submit" name="smcq_exam_build_mode" value="generate" class="button button-secondary"><?php esc_html_e( 'Save Exam & Generate Questions', 'smart-mcq-manager' ); ?></button></p>
		<p class="description"><?php esc_html_e( 'Random generation draws from at most the latest 2,000 matching published questions; older questions outside this pool are not sampled. Large question banks will get a more scalable selector in a later version.', 'smart-mcq-manager' ); ?></p>
		<?php
	}

	private static function taxonomy_dropdown( $taxonomy, $name, $selected, $label ) {
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax ) { return; }
		echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
		wp_dropdown_categories( array(
			'show_option_all' => __( 'Any', 'smart-mcq-manager' ),
			'hide_empty'      => false,
			'hierarchical'    => true,
			'taxonomy'        => $taxonomy,
			'selected'        => $selected,
			'name'            => $name,
			'id'              => $name,
			'value_field'     => 'term_id',
			'class'           => 'widefat',
		) );
		echo '</p>';
	}

	private static function posted( $key ) {
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/** Verify a question belongs to the private, published, fully answered bank. */
	public static function valid_question( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'smcq_question' !== $post->post_type || 'publish' !== $post->post_status || '' === trim( $post->post_title ) ) {
			return false;
		}
		$options = get_post_meta( $id, '_smcq_options', true );
		$correct = get_post_meta( $id, '_smcq_correct', true );
		if ( ! is_array( $options ) || ! in_array( $correct, array( 'A', 'B', 'C', 'D' ), true ) ) {
			return false;
		}
		foreach ( array( 'A', 'B', 'C', 'D' ) as $key ) {
			if ( ! isset( $options[ $key ] ) || ! is_string( $options[ $key ] ) || '' === trim( $options[ $key ] ) ) {
				return false;
			}
		}
		return true;
	}

	/** Select from filtered published questions and do not save partial sets. */
	public static function generate( $filters, $wanted ) {
		$wanted = (int) $wanted;
		if ( $wanted < 1 || $wanted > self::MAX_QUESTIONS ) {
			return array();
		}
		$args = array(
			'post_type'              => 'smcq_question',
			'post_status'            => 'publish',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'posts_per_page'         => self::CANDIDATE_LIMIT,
			'orderby'                => 'ID',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'suppress_filters'       => true,
		);
		$tax = array();
		foreach ( array( 'subject' => 'smcq_subject', 'topic' => 'smcq_topic', 'source' => 'smcq_exam_source' ) as $key => $taxonomy ) {
			$term = isset( $filters[ $key ] ) ? (int) $filters[ $key ] : 0;
			if ( $term > 0 ) {
				$tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array( $term ), 'include_children' => true );
			}
		}
		if ( $tax ) { $args['tax_query'] = $tax; }
		$difficulty = isset( $filters['difficulty'] ) ? (string) $filters['difficulty'] : '';
		if ( in_array( $difficulty, array( 'easy', 'medium', 'hard' ), true ) ) {
			$args['meta_query'] = 'easy' === $difficulty ? array(
				'relation' => 'OR',
				array( 'key' => '_smcq_difficulty', 'value' => 'easy' ),
				array( 'key' => '_smcq_difficulty', 'compare' => 'NOT EXISTS' ),
			) : array( array( 'key' => '_smcq_difficulty', 'value' => $difficulty ) );
		}
		$query = new WP_Query( $args );
		$posts = is_array( $query->posts ) ? $query->posts : array();
		$eligible = array();
		foreach ( $posts as $candidate ) {
			$id = is_object( $candidate ) ? (int) $candidate->ID : (int) $candidate;
			if ( self::valid_question( $id ) ) {
				$eligible[] = $id;
			}
		}
		if ( count( $eligible ) < $wanted ) { return array(); }
		shuffle( $eligible );
		return array_slice( $eligible, 0, $wanted );
	}

	/** Only editor saves with verified nonce and post-specific edit permissions change exams. */
	public static function save( $post_id, $post ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['smcq_exam_nonce'] ) || ! is_string( $_POST['smcq_exam_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smcq_exam_nonce'] ) ), 'smcq_save_exam' ) ) { return; }
		$duration_raw = self::posted( 'smcq_exam_duration' );
		$duration = ctype_digit( $duration_raw ) && (int) $duration_raw >= 1 && (int) $duration_raw <= 600 ? (int) $duration_raw : 60;
		$pass = self::decimal( self::posted( 'smcq_exam_pass_percent' ), 40, 0, 100 );
		$attempts_raw = self::posted( 'smcq_exam_attempt_limit' );
		$attempts = ctype_digit( $attempts_raw ) && (int) $attempts_raw <= 1000 ? (int) $attempts_raw : 1;
		$marking = self::posted( 'smcq_exam_marking' );
		$marking = in_array( $marking, array( 'question', 'fixed' ), true ) ? $marking : 'question';
		$positive = self::decimal( self::posted( 'smcq_exam_positive' ), 1, 0.01, 1000 );
		$negative = self::decimal( self::posted( 'smcq_exam_negative' ), 0.25, 0, $positive );
		update_post_meta( $post_id, '_smcq_exam_duration', $duration );
		update_post_meta( $post_id, '_smcq_exam_pass_percent', $pass );
		update_post_meta( $post_id, '_smcq_exam_attempt_limit', $attempts );
		update_post_meta( $post_id, '_smcq_exam_marking', $marking );
		update_post_meta( $post_id, '_smcq_exam_positive', $positive );
		update_post_meta( $post_id, '_smcq_exam_negative', $negative );
		update_post_meta( $post_id, '_smcq_exam_shuffle_questions', '1' === self::posted( 'smcq_exam_shuffle_questions' ) ? 1 : 0 );
		update_post_meta( $post_id, '_smcq_exam_shuffle_options', '1' === self::posted( 'smcq_exam_shuffle_options' ) ? 1 : 0 );
		update_post_meta( $post_id, '_smcq_exam_leaderboard', '1' === self::posted( 'smcq_exam_leaderboard' ) ? 1 : 0 );
		update_post_meta( $post_id, '_smcq_exam_monitor_activity', '1' === self::posted( 'smcq_exam_monitor_activity' ) ? 1 : 0 );
		update_post_meta( $post_id, '_smcq_exam_single_tab', '1' === self::posted( 'smcq_exam_single_tab' ) ? 1 : 0 );

		$start_input = self::posted( 'smcq_exam_start' );
		$end_input = self::posted( 'smcq_exam_end' );
		$start = self::validate_local_date( $start_input );
		$end = self::validate_local_date( $end_input );
		if ( ( $start_input && ! $start ) || ( $end_input && ! $end ) || ( $start && $end && $end <= $start ) ) {
			self::message( __( 'The exam schedule is invalid (end must be later than start). Existing schedule was kept.', 'smart-mcq-manager' ), 'error' );
		} else {
			update_post_meta( $post_id, '_smcq_exam_start', $start );
			update_post_meta( $post_id, '_smcq_exam_end', $end );
		}

		$filters = array(
			'subject' => absint( self::posted( 'smcq_exam_gen_subject' ) ),
			'topic' => absint( self::posted( 'smcq_exam_gen_topic' ) ),
			'source' => absint( self::posted( 'smcq_exam_gen_source' ) ),
			'difficulty' => self::posted( 'smcq_exam_gen_difficulty' ),
		);
		$count_raw = self::posted( 'smcq_exam_gen_count' );
		$count = ctype_digit( $count_raw ) && (int) $count_raw >= 1 && (int) $count_raw <= self::MAX_QUESTIONS ? (int) $count_raw : 20;
		foreach ( array( 'subject', 'topic', 'source' ) as $key ) {
			update_post_meta( $post_id, '_smcq_exam_gen_' . $key, $filters[ $key ] );
		}
		update_post_meta( $post_id, '_smcq_exam_gen_difficulty', in_array( $filters['difficulty'], array( 'easy', 'medium', 'hard' ), true ) ? $filters['difficulty'] : '' );
		update_post_meta( $post_id, '_smcq_exam_gen_count', $count );

		$mode = self::posted( 'smcq_exam_build_mode' );
		if ( 'generate' === $mode ) {
			$ids = self::generate( $filters, $count );
			if ( count( $ids ) === $count ) {
				update_post_meta( $post_id, '_smcq_exam_question_ids', $ids );
				self::message( sprintf( /* translators: %d: selected question count */ __( 'Generated and saved %d valid questions.', 'smart-mcq-manager' ), $count ), 'success' );
			} else {
				self::message( __( 'Not enough eligible questions match these filters. The previously selected question set was retained.', 'smart-mcq-manager' ), 'error' );
			}
		} elseif ( isset( $_POST['smcq_selected_ids'] ) && is_string( $_POST['smcq_selected_ids'] ) ) {
			$raw_ids = self::normalize_ids( wp_unslash( $_POST['smcq_selected_ids'] ) );
			$ids = array_values( array_filter( $raw_ids, array( __CLASS__, 'valid_question' ) ) );
			update_post_meta( $post_id, '_smcq_exam_question_ids', $ids );
			if ( count( $ids ) !== count( $raw_ids ) ) {
				self::message( __( 'Some selected IDs were excluded because they are missing, drafts, or incomplete.', 'smart-mcq-manager' ), 'warning' );
			}
		}
	}

	/** Strict local datetime validation; saved value remains timezone-independent until delivery. */
	public static function validate_local_date( $value ) {
		if ( ! is_string( $value ) || '' === $value ) { return ''; }
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $value : '';
	}

	private static function message( $text, $type ) {
		set_transient( 'smcq_exam_notice_' . get_current_user_id(), array( 'text' => $text, 'type' => $type ), 90 );
	}

	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) { return; }
		$key = 'smcq_exam_notice_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( ! $msg || ! is_array( $msg ) ) { return; }
		delete_transient( $key );
		$type = in_array( $msg['type'], array( 'error', 'warning', 'success' ), true ) ? $msg['type'] : 'info';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $msg['text'] ) . '</p></div>';
	}

	public static function columns( $columns ) {
		$columns['smcq_exam_questions'] = __( 'Questions', 'smart-mcq-manager' );
		$columns['smcq_exam_duration'] = __( 'Duration', 'smart-mcq-manager' );
		$columns['smcq_exam_pass'] = __( 'Pass %', 'smart-mcq-manager' );
		$columns['smcq_exam_ready'] = __( 'Ready', 'smart-mcq-manager' );
		return $columns;
	}

	public static function column_value( $column, $post_id ) {
		$ids = get_post_meta( $post_id, '_smcq_exam_question_ids', true );
		$ids = is_array( $ids ) ? $ids : array();
		switch ( $column ) {
			case 'smcq_exam_questions': echo esc_html( count( $ids ) ); break;
			case 'smcq_exam_duration': echo esc_html( (string) self::meta( $post_id, '_smcq_exam_duration', 60 ) . ' min' ); break;
			case 'smcq_exam_pass': echo esc_html( (string) self::meta( $post_id, '_smcq_exam_pass_percent', 40 ) . '%' ); break;
			case 'smcq_exam_ready':
				$ready = ! empty( $ids );
				foreach ( $ids as $id ) { if ( ! self::valid_question( $id ) ) { $ready = false; break; } }
				echo $ready ? '<span class="smcq-ready">' . esc_html__( 'Ready', 'smart-mcq-manager' ) . '</span>' : '<span class="smcq-incomplete">' . esc_html__( 'Needs questions', 'smart-mcq-manager' ) . '</span>';
				break;
		}
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) { return; }
		wp_enqueue_style( 'smcq-admin', SMCQ_URL . 'assets/admin.css', array(), SMCQ_VERSION );
		wp_enqueue_script( 'smcq-exam-admin', SMCQ_URL . 'assets/exam-admin.js', array(), SMCQ_VERSION, true );
		wp_localize_script( 'smcq-exam-admin', 'smcqExam', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'smcq_exam_search' ),
			'examId' => $screen->post_type === self::POST_TYPE && isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0,
			'max' => self::MAX_QUESTIONS,
			'empty' => __( 'No matching complete questions found.', 'smart-mcq-manager' ),
			'error' => __( 'Search failed. Please retry.', 'smart-mcq-manager' ),
		) );
	}

	/** Authorized, nonce-protected AJAX title search; never exposes answers. */
	public static function search_questions() {
		check_ajax_referer( 'smcq_exam_search', 'nonce' );
		$exam_id = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
		if ( ! current_user_can( 'edit_posts' ) || ( $exam_id && ! current_user_can( 'edit_post', $exam_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-mcq-manager' ) ), 403 );
		}
		$term = isset( $_GET['term'] ) && is_scalar( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$term = function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 90 ) : substr( $term, 0, 90 );
		if ( '' === trim( $term ) ) { wp_send_json_success( array() ); }
		if ( preg_match( '/^(?:Q-)?([0-9]{1,12})$/i', $term, $match ) ) {
			$id = (int) $match[1];
			if ( self::valid_question( $id ) ) {
				wp_send_json_success( array( array( 'id' => $id, 'title' => get_the_title( $id ) ) ) );
			}
		}
		$query = new WP_Query( array(
			'post_type' => 'smcq_question', 'post_status' => 'publish',
			's' => $term, 'posts_per_page' => 30, 'no_found_rows' => true,
			'ignore_sticky_posts' => true, 'suppress_filters' => true,
		) );
		$results = array();
		foreach ( $query->posts as $post ) {
			if ( self::valid_question( $post->ID ) ) {
				$results[] = array( 'id' => (int) $post->ID, 'title' => get_the_title( $post->ID ) );
			}
		}
		wp_send_json_success( $results );
	}
}