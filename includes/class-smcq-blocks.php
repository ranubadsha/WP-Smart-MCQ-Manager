<?php
/** Native editor blocks wrapping private shortcode APIs. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Blocks {
    public static function init() { add_action( 'init', array( __CLASS__, 'register' ) ); }
    public static function register() {
        if ( ! function_exists( 'register_block_type' ) ) { return; }
        wp_register_script( 'smcq-block-editor', SMCQ_URL . 'assets/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ), SMCQ_VERSION, true );
        $blocks = array(
            'smcq/exam' => array( 'examId' => array( 'type' => 'number', 'default' => 0 ) ),
            'smcq/dashboard' => array(),
            'smcq/practice' => array(),
            'smcq/history' => array(),
        );
        foreach ( $blocks as $name => $attributes ) {
            register_block_type( $name, array(
                'api_version' => 2,
                'editor_script' => 'smcq-block-editor',
                'attributes' => $attributes,
                'render_callback' => array( __CLASS__, 'render' ),
            ) );
        }
    }
    public static function render( $attributes, $content, $block ) {
        $name = is_object( $block ) && isset( $block->name ) ? $block->name : '';
        if ( 'smcq/exam' === $name ) {
            $id = isset( $attributes['examId'] ) ? absint( $attributes['examId'] ) : 0;
            return $id ? do_shortcode( '[smcq_exam id="' . $id . '"]' ) : '<p>' . esc_html__( 'Select an examination ID in the block settings.', 'smart-mcq-manager' ) . '</p>';
        }
        $shortcodes = array( 'smcq/dashboard' => '[smcq_dashboard]', 'smcq/practice' => '[smcq_practice]', 'smcq/history' => '[smcq_history]' );
        return isset( $shortcodes[ $name ] ) ? do_shortcode( $shortcodes[ $name ] ) : '';
    }
}