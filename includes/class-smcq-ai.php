<?php
/** Optional, administrator-only AI draft creation; no student data is transmitted. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_AI {
    const MAX_QUESTIONS = 5;
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_smcq_ai_generate', array( __CLASS__, 'generate' ) );
    }
    public static function menu() {
        add_submenu_page( 'edit.php?post_type=smcq_question', __( 'AI Draft Questions', 'smart-mcq-manager' ), __( 'AI Drafts', 'smart-mcq-manager' ), 'manage_options', 'smcq-ai', array( __CLASS__, 'page' ) );
    }
    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        $available = defined( 'SMCQ_OPENAI_API_KEY' ) && is_string( SMCQ_OPENAI_API_KEY ) && '' !== trim( SMCQ_OPENAI_API_KEY );
        echo '<div class="wrap"><h1>' . esc_html__( 'AI-Assisted Question Drafts', 'smart-mcq-manager' ) . '</h1>';
        echo '<p>' . esc_html__( 'Optional feature. The question topic and requested language are sent to the configured API provider. No student records or exam answers are included. Generated questions are unpublished drafts and MUST be reviewed before use.', 'smart-mcq-manager' ) . '</p>';
        if ( ! $available ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'Set SMCQ_OPENAI_API_KEY in wp-config.php to enable drafts. API usage may incur charges.', 'smart-mcq-manager' ) . '</p></div>'; }
        if ( isset( $_GET['smcq_created'] ) ) { echo '<div class="notice notice-info"><p>' . esc_html( (int) $_GET['smcq_created'] ) . ' ' . esc_html__( 'new question drafts created for editorial review.', 'smart-mcq-manager' ) . '</p></div>'; }
        if ( isset( $_GET['smcq_ai_error'] ) ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'The API request or response validation failed. No unverified answer key was published.', 'smart-mcq-manager' ) . '</p></div>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'smcq_ai_generate' );
        echo '<input type="hidden" name="action" value="smcq_ai_generate" />';
        echo '<p><label>' . esc_html__( 'Topic (do not enter private student data)', 'smart-mcq-manager' ) . '<br><input name="topic" required maxlength="150" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Language', 'smart-mcq-manager' ) . ' <select name="language"><option value="en">English</option><option value="bn">বাংলা</option></select></label></p>';
        echo '<p><label>' . esc_html__( 'Questions (maximum 5)', 'smart-mcq-manager' ) . ' <input type="number" name="count" min="1" max="5" value="3" /></label></p>';
        submit_button( __( 'Generate Unpublished Drafts', 'smart-mcq-manager' ), 'primary', 'submit', false, $available ? array() : array( 'disabled' => 'disabled' ) );
        echo '</form></div>';
    }
    /** Validate untrusted model JSON before creating any WordPress draft. */
    public static function clean_records( $value, $max = 5 ) {
        if ( ! is_array( $value ) || ! isset( $value['questions'] ) || ! is_array( $value['questions'] ) ) { return array(); }
        $valid = array();
        foreach ( array_slice( $value['questions'], 0, min( self::MAX_QUESTIONS, (int) $max ) ) as $item ) {
            if ( ! is_array( $item ) ) { continue; }
            $title = isset( $item['question'] ) && is_string( $item['question'] ) ? trim( $item['question'] ) : '';
            $options = array();
            foreach ( array( 'A','B','C','D' ) as $key ) { $options[$key] = isset( $item['options'][$key] ) && is_string( $item['options'][$key] ) ? trim( $item['options'][$key] ) : ''; }
            $correct = isset( $item['correct'] ) && is_string( $item['correct'] ) ? strtoupper( $item['correct'] ) : '';
            if ( strlen( $title ) < 8 || strlen( $title ) > 1000 || strlen( implode( '', $options ) ) > 4000 || ! SMCQ_Question_Types::ready( 'single', $options, $correct ) ) { continue; }
            $explanation = isset( $item['explanation'] ) && is_string( $item['explanation'] ) ? trim( $item['explanation'] ) : '';
            $valid[] = array( 'question' => $title, 'options' => $options, 'correct' => $correct, 'explanation' => substr( $explanation, 0, 4000 ) );
        }
        return $valid;
    }
    public static function response_json( $data ) {
        if ( ! is_array( $data ) || ! isset( $data['output'] ) || ! is_array( $data['output'] ) ) { return array(); }
        foreach ( $data['output'] as $item ) {
            foreach ( isset( $item['content'] ) && is_array( $item['content'] ) ? $item['content'] : array() as $part ) {
                if ( isset( $part['type'], $part['text'] ) && 'output_text' === $part['type'] && is_string( $part['text'] ) ) {
                    $decoded = json_decode( $part['text'], true, 16 );
                    if ( is_array( $decoded ) ) { return $decoded; }
                }
            }
        }
        return array();
    }
    public static function generate() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'smcq_ai_generate' );
        $topic = isset( $_POST['topic'] ) && is_scalar( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
        $language = isset( $_POST['language'] ) && 'bn' === $_POST['language'] ? 'bn' : 'en';
        $count = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 0;
        $key = defined( 'SMCQ_OPENAI_API_KEY' ) ? (string) SMCQ_OPENAI_API_KEY : '';
        $back = admin_url( 'edit.php?post_type=smcq_question&page=smcq-ai' );
        if ( strlen( $topic ) < 3 || strlen( $topic ) > 150 || $count < 1 || $count > self::MAX_QUESTIONS || ! $key ) { wp_safe_redirect( add_query_arg( 'smcq_ai_error', 'invalid', $back ) ); exit; }
        // Prevent costly repeat calls. Never cache or log API key or generated responses.
        if ( get_transient( 'smcq_ai_wait_' . get_current_user_id() ) ) { wp_safe_redirect( add_query_arg( 'smcq_ai_error', 'rate', $back ) ); exit; }
        set_transient( 'smcq_ai_wait_' . get_current_user_id(), true, 60 );
        $request = array( 'model' => defined( 'SMCQ_OPENAI_MODEL' ) ? SMCQ_OPENAI_MODEL : 'gpt-4.1-mini',
            'input' => 'Create ' . $count . ' accurate single-answer MCQ questions for educators on topic: ' . $topic . '. Language: ' . $language . '. Return a JSON object with a questions array. Each record: question (string), options (object with A B C D strings), correct (one of A B C D), explanation (string). Do not return markdown. Do not invent source citations. Return only JSON.',
            'max_output_tokens' => 2500 );
        $response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
            'timeout' => 40, 'redirection' => 0, 'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
            'body' => wp_json_encode( $request ), 'sslverify' => true ) );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) { wp_safe_redirect( add_query_arg( 'smcq_ai_error', 'api', $back ) ); exit; }
        $body = wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > 600000 ) { wp_safe_redirect( add_query_arg( 'smcq_ai_error', 'size', $back ) ); exit; }
        $records = self::clean_records( self::response_json( json_decode( $body, true, 32 ) ), $count );
        $added = 0;
        foreach ( $records as $record ) {
            $id = wp_insert_post( array( 'post_type' => 'smcq_question', 'post_title' => sanitize_text_field( $record['question'] ), 'post_status' => 'draft', 'post_author' => get_current_user_id() ), true );
            if ( is_wp_error( $id ) || ! $id ) { continue; }
            update_post_meta( $id, '_smcq_type', 'single' );
            update_post_meta( $id, '_smcq_options', array_map( 'sanitize_text_field', $record['options'] ) );
            update_post_meta( $id, '_smcq_correct', $record['correct'] );
            update_post_meta( $id, '_smcq_explanation', sanitize_textarea_field( $record['explanation'] ) );
            update_post_meta( $id, '_smcq_ai_draft', 1 );
            ++$added;
        }
        wp_safe_redirect( add_query_arg( 'smcq_created', $added, $back ) ); exit;
    }
}