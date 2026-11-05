<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Questions {
    public static function register() {
        register_post_type( 'smcq_question', array(
            'labels' => array(
                'name' => __( 'MCQ Questions', 'smart-mcq-manager' ),
                'singular_name' => __( 'MCQ Question', 'smart-mcq-manager' ),
                'add_new_item' => __( 'Add MCQ Question', 'smart-mcq-manager' ),
                'edit_item' => __( 'Edit MCQ Question', 'smart-mcq-manager' ),
                'menu_name' => __( 'Smart MCQ', 'smart-mcq-manager' ),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => array( 'title' ),
            'map_meta_cap' => true,
        ) );
        register_taxonomy( 'smcq_subject', array( 'smcq_question' ), array(
            'labels' => array( 'name' => __( 'Subjects', 'smart-mcq-manager' ), 'singular_name' => __( 'Subject', 'smart-mcq-manager' ) ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => false,
        ) );
        register_taxonomy( 'smcq_topic', array( 'smcq_question' ), array(
            'labels' => array( 'name' => __( 'Topics', 'smart-mcq-manager' ), 'singular_name' => __( 'Topic', 'smart-mcq-manager' ) ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => false,
        ) );
    }
    public static function meta_boxes() {
        add_meta_box( 'smcq_details', __( 'Answers and Explanation', 'smart-mcq-manager' ), array( __CLASS__, 'render' ), 'smcq_question', 'normal', 'high' );
    }
    public static function render( $post ) {
        wp_nonce_field( 'smcq_save_question', 'smcq_question_nonce' );
        $options = get_post_meta( $post->ID, '_smcq_options', true );
        $options = is_array( $options ) ? $options : array();
        $correct = get_post_meta( $post->ID, '_smcq_correct', true );
        $explanation = get_post_meta( $post->ID, '_smcq_explanation', true );
        echo '<p>' . esc_html__( 'Enter the question in the Title field above. Add four options here.', 'smart-mcq-manager' ) . '</p>';
        foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
            printf( '<p><label for="smcq_option_%1$s"><strong>%1$s.</strong></label> <input class="widefat" id="smcq_option_%1$s" name="smcq_options[%1$s]" type="text" value="%2$s" /></p>', esc_attr( $letter ), esc_attr( isset( $options[ $letter ] ) ? $options[ $letter ] : '' ) );
        }
        echo '<p><label for="smcq_correct"><strong>' . esc_html__( 'Correct answer', 'smart-mcq-manager' ) . '</strong></label> ';
        echo '<select id="smcq_correct" name="smcq_correct"><option value="">' . esc_html__( 'Select', 'smart-mcq-manager' ) . '</option>';
        foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
            printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $letter ), selected( $correct, $letter, false ) );
        }
        echo '</select></p>';
        echo '<p><label for="smcq_explanation"><strong>' . esc_html__( 'Explanation', 'smart-mcq-manager' ) . '</strong></label></p>';
        printf( '<textarea class="widefat" id="smcq_explanation" name="smcq_explanation" rows="6">%s</textarea>', esc_textarea( $explanation ) );
    }
    public static function save( $post_id, $post ) {
        if ( ! isset( $_POST['smcq_question_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smcq_question_nonce'] ) ), 'smcq_save_question' ) ) { return; }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
        if ( ! isset( $_POST['smcq_options'] ) || ! is_array( $_POST['smcq_options'] ) ) { return; }
        $raw = wp_unslash( $_POST['smcq_options'] );
        $options = array();
        foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
            $options[ $letter ] = isset( $raw[ $letter ] ) && is_scalar( $raw[ $letter ] ) ? sanitize_text_field( $raw[ $letter ] ) : '';
        }
        $correct = isset( $_POST['smcq_correct'] ) && is_scalar( $_POST['smcq_correct'] ) ? sanitize_text_field( wp_unslash( $_POST['smcq_correct'] ) ) : '';
        if ( ! in_array( $correct, array( 'A', 'B', 'C', 'D' ), true ) || '' === $options[ $correct ] ) { $correct = ''; }
        $explanation = isset( $_POST['smcq_explanation'] ) && is_scalar( $_POST['smcq_explanation'] ) ? sanitize_textarea_field( wp_unslash( $_POST['smcq_explanation'] ) ) : '';
        update_post_meta( $post_id, '_smcq_options', $options );
        update_post_meta( $post_id, '_smcq_correct', $correct );
        update_post_meta( $post_id, '_smcq_explanation', $explanation );
    }
    public static function columns( $columns ) {
        $columns['smcq_correct'] = __( 'Correct', 'smart-mcq-manager' );
        return $columns;
    }
    public static function column_value( $column, $post_id ) {
        if ( 'smcq_correct' === $column ) {
            $correct = get_post_meta( $post_id, '_smcq_correct', true );
            echo esc_html( $correct ? $correct : '—' );
        }
    }
    public static function notice() {
        $screen = get_current_screen();
        if ( ! $screen || 'smcq_question' !== $screen->post_type ) { return; }
        echo '<div class="notice notice-info"><p>' . esc_html__( 'Step 1: Add a question title, four answer options, the correct answer, explanation, and subject/topic. Exam delivery will be added in a future step.', 'smart-mcq-manager' ) . '</p></div>';
    }
}
