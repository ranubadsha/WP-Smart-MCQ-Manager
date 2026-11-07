<?php
/** Private, printable completion certificates for passed attempts. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Certificates {
	public static function init() { add_shortcode( 'smcq_certificate', array( __CLASS__, 'shortcode' ) ); }
	public static function eligible( $attempt ) {
		if ( ! is_array( $attempt ) || ! isset( $attempt['status'], $attempt['result_json'] ) || 'submitted' !== $attempt['status'] ) { return false; }
		$score = json_decode( (string) $attempt['result_json'], true );
		return is_array( $score ) && ! empty( $score['passed'] );
	}
	public static function shortcode( $attributes ) {
		if ( ! is_user_logged_in() ) { return '<p>' . esc_html__( 'Please log in to view your certificate.', 'smart-mcq-manager' ) . '</p>'; }
		$attributes = shortcode_atts( array( 'attempt_id' => 0 ), $attributes, 'smcq_certificate' );
		$id = absint( $attributes['attempt_id'] );
		if ( ! $id ) { return '<p>' . esc_html__( 'Provide a completed examination attempt ID in the shortcode.', 'smart-mcq-manager' ) . '</p>'; }
		global $wpdb;
		$table = $wpdb->prefix . 'smcq_attempts';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,exam_id,user_id,status,submitted_at,result_json FROM %i WHERE id=%d AND user_id=%d AND status='submitted'", $table, $id, get_current_user_id() ), ARRAY_A );
		if ( ! self::eligible( $row ) ) { return '<p>' . esc_html__( 'No eligible certificate was found for your account.', 'smart-mcq-manager' ) . '</p>'; }
		$result = json_decode( $row['result_json'], true );
		$account = wp_get_current_user();
		$display = trim( $account->display_name ) ?: $account->user_login;
		$ref = strtoupper( substr( hash_hmac( 'sha256', $id . '|' . $row['user_id'] . '|' . $row['exam_id'], wp_salt( 'auth' ) ), 0, 12 ) );
		ob_start();
		?>
		<section class="smcq-certificate" style="padding:38px;border:7px double #334155;max-width:850px;margin:24px auto;text-align:center;background:white;color:#0f172a">
			<h2><?php esc_html_e( 'Certificate of Completion', 'smart-mcq-manager' ); ?></h2>
			<p><?php esc_html_e( 'This certificate recognizes that', 'smart-mcq-manager' ); ?></p>
			<h3><?php echo esc_html( $display ); ?></h3>
			<p><?php esc_html_e( 'successfully passed', 'smart-mcq-manager' ); ?></p>
			<h3><?php echo esc_html( get_the_title( (int) $row['exam_id'] ) ); ?></h3>
			<p><?php echo esc_html( sprintf( /* translators: 1: score, 2: maximum */ __( 'Score: %1$s / %2$s', 'smart-mcq-manager' ), $result['score'], $result['maxMarks'] ) ); ?></p>
			<p><?php echo esc_html( sprintf( /* translators: %s: identifier */ __( 'Reference: %s', 'smart-mcq-manager' ), $ref ) ); ?></p>
			<p><?php echo esc_html( (string) $row['submitted_at'] ); ?></p>
		</section>
		<p class="smcq-certificate-print"><button type="button" onclick="window.print()" class="button"><?php esc_html_e( 'Print / Save as PDF', 'smart-mcq-manager' ); ?></button></p>
		<?php
		return ob_get_clean();
	}
}