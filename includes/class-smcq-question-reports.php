<?php
/** Student-submitted question issue reports, visible only to site administrators. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Question_Reports {
	const POST_TYPE = 'smcq_question_report';
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}
	public static function register() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array( 'name' => __( 'Question Issue Reports', 'smart-mcq-manager' ), 'singular_name' => __( 'Question Issue Report', 'smart-mcq-manager' ) ),
			'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
			'show_ui' => true, 'show_in_rest' => false, 'show_in_menu' => 'edit.php?post_type=smcq_question',
			'supports' => array( 'title', 'editor' ), 'map_meta_cap' => false,
			'capabilities' => array(
				'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options',
				'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'read_private_posts' => 'manage_options',
				'delete_posts' => 'manage_options', 'delete_others_posts' => 'manage_options',
				'publish_posts' => 'manage_options', 'create_posts' => 'do_not_allow',
			),
		) );
	}
	public static function routes() {
		register_rest_route( 'smcq/v1', '/attempts/(?P<id>\\d+)/report-question', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'report' ),
			'permission_callback' => static function () { return is_user_logged_in(); },
		) );
	}
	public static function reasons() { return array( 'incorrect_key', 'unclear_wording', 'incorrect_explanation', 'missing_information', 'other' ); }
	/** Pure validation of controlled issue categories, text and question ID. */
	public static function validate( $qid, $reason, $details ) {
		return is_int( $qid ) && $qid > 0 && in_array( $reason, self::reasons(), true ) && is_string( $details ) && strlen( $details ) >= 15 && strlen( $details ) <= 2000;
	}
	public static function report( $request ) {
		global $wpdb;
		$user = get_current_user_id();
		$attempt = absint( $request['id'] );
		$qid = $request->get_param( 'questionId' );
		$reason = $request->get_param( 'reason' );
		$details = $request->get_param( 'details' );
		if ( ! self::validate( $qid, $reason, $details ) ) {
			return new WP_Error( 'smcq_report_invalid', __( 'Enter a reason and a description of 15–2,000 characters.', 'smart-mcq-manager' ), array( 'status' => 400 ) );
		}
		$table = $wpdb->prefix . 'smcq_attempts';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT snapshot FROM %i WHERE id = %d AND user_id = %d AND status = 'submitted'", $table, $attempt, $user ), ARRAY_A );
		if ( ! $row ) { return new WP_Error( 'smcq_report_not_allowed', __( 'Only completed, owned attempts can be reported.', 'smart-mcq-manager' ), array( 'status' => 403 ) ); }
		$snapshot = json_decode( $row['snapshot'], true );
		$present = false;
		foreach ( isset( $snapshot['questions'] ) && is_array( $snapshot['questions'] ) ? $snapshot['questions'] : array() as $q ) {
			if ( (int) $q['id'] === $qid ) { $present = true; break; }
		}
		if ( ! $present ) { return new WP_Error( 'smcq_report_not_in_exam', __( 'This question is not in your attempt.', 'smart-mcq-manager' ), array( 'status' => 403 ) ); }
		$dedupe = 'smcq_report_' . hash( 'sha256', $user . ':' . $attempt . ':' . $qid );
		if ( get_transient( $dedupe ) ) { return new WP_Error( 'smcq_report_duplicate', __( 'You already submitted an issue for this question recently.', 'smart-mcq-manager' ), array( 'status' => 429 ) ); }
		$perday = 'smcq_report_rate_' . $user;
		if ( (int) get_transient( $perday ) >= 5 ) { return new WP_Error( 'smcq_report_limit', __( 'Daily question report limit reached.', 'smart-mcq-manager' ), array( 'status' => 429 ) ); }
		$id = wp_insert_post( array(
			'post_type' => self::POST_TYPE, 'post_status' => 'private',
			'post_title' => sprintf( 'Question #%d — %s', $qid, $reason ),
			'post_content' => sanitize_textarea_field( $details ), 'post_author' => $user,
			'meta_input' => array( '_smcq_report_question_id' => $qid, '_smcq_report_attempt_id' => $attempt ),
		), true );
		if ( is_wp_error( $id ) || ! $id ) { return new WP_Error( 'smcq_report_failed', __( 'Could not save the report.', 'smart-mcq-manager' ), array( 'status' => 500 ) ); }
		set_transient( $dedupe, 1, WEEK_IN_SECONDS );
		set_transient( $perday, (int) get_transient( $perday ) + 1, DAY_IN_SECONDS );
		return rest_ensure_response( array( 'submitted' => true ) );
	}
    public static function register_exporter( $exporters ) {
        $exporters['smcq-question-reports'] = array( 'exporter_friendly_name' => __( 'MCQ Question Reports', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'export_data' ) );
        return $exporters;
    }
    public static function register_eraser( $erasers ) {
        $erasers['smcq-question-reports'] = array( 'eraser_friendly_name' => __( 'MCQ Question Reports', 'smart-mcq-manager' ), 'callback' => array( __CLASS__, 'erase_data' ) );
        return $erasers;
    }
    private static function own_reports( $email, $page, $delete = false ) {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) { return array(); }
        return get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'author' => $user->ID, 'posts_per_page' => 20, 'paged' => $delete ? 1 : max( 1, (int) $page ), 'orderby' => 'ID', 'order' => 'ASC' ) );
    }
    public static function export_data( $email, $page = 1 ) {
        $rows = self::own_reports( $email, $page );
        $items = array();
        foreach ( $rows as $post ) {
            $items[] = array( 'group_id' => 'smcq-question-reports', 'group_label' => __( 'MCQ Question Reports', 'smart-mcq-manager' ), 'item_id' => 'smcq-issue-' . $post->ID, 'data' => array(
                array( 'name' => __( 'Question ID', 'smart-mcq-manager' ), 'value' => get_post_meta( $post->ID, '_smcq_report_question_id', true ) ),
                array( 'name' => __( 'Report', 'smart-mcq-manager' ), 'value' => $post->post_content ),
            ) );
        }
        return array( 'data' => $items, 'done' => count( $rows ) < 20 );
    }
    public static function erase_data( $email, $page = 1 ) {
        $rows = self::own_reports( $email, $page, true );
        $removed = false;
        foreach ( $rows as $post ) { if ( wp_delete_post( $post->ID, true ) ) { $removed = true; } }
        return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => count( $rows ) < 20 );
    }

	public static function columns( $columns ) {
		$columns['question'] = __( 'Question', 'smart-mcq-manager' );
		return $columns;
	}
	public static function column( $column, $post_id ) {
		if ( 'question' !== $column ) { return; }
		$id = (int) get_post_meta( $post_id, '_smcq_report_question_id', true );
		if ( $id && get_post_type( $id ) === 'smcq_question' ) {
			printf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $id ) ), esc_html( 'Q-' . $id ) );
		} else { echo '—'; }
	}
}