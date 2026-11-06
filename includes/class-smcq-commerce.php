<?php
/**
 * Step 11: WooCommerce product-based premium exam access.
 * No payment data is stored by Smart MCQ; WooCommerce owns checkout and orders.
 *
 * @package SmartMCQManager
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SMCQ_Commerce {
	const META_MODE = '_smcq_exam_access';
	const META_PRODUCTS = '_smcq_exam_products';
	const META_DAYS = '_smcq_exam_access_days';
	const MAX_PRODUCTS = 30;
	const MAX_ORDER_PAGES = 40; // 40 x 50 orders; explicit documented safety bound.

	public static function init() {
		add_action( 'add_meta_boxes_smcq_exam', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_smcq_exam', array( __CLASS__, 'save' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/** Reject malformed IDs instead of turning arbitrary strings into integers. */
	public static function product_ids( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}
		if ( ! is_string( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( preg_split( '/[\s,;]+/', trim( $raw ) ) as $part ) {
			if ( ! preg_match( '/^[1-9][0-9]{0,11}$/D', $part ) ) {
				continue;
			}
			$id = (int) $part;
			if ( $id > 0 ) {
				$out[ $id ] = $id;
			}
			if ( count( $out ) >= self::MAX_PRODUCTS ) {
				break;
			}
		}
		return array_values( $out );
	}

	public static function valid_duration( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[0-9]{1,5}$/D', (string) $value ) ) {
			return 0;
		}
		return min( 36500, (int) $value );
	}

	public static function premium( $exam_id ) {
		return 'premium' === get_post_meta( $exam_id, self::META_MODE, true );
	}

	public static function meta_box() {
		add_meta_box( 'smcq_premium_exam', __( 'Premium Access & WooCommerce', 'smart-mcq-manager' ), array( __CLASS__, 'render' ), 'smcq_exam', 'normal', 'default' );
	}

	public static function render( $post ) {
		$mode = self::premium( $post->ID ) ? 'premium' : 'free';
		$ids = self::product_ids( get_post_meta( $post->ID, self::META_PRODUCTS, true ) );
		$days = self::valid_duration( get_post_meta( $post->ID, self::META_DAYS, true ) );
		wp_nonce_field( 'smcq_commerce_save', 'smcq_commerce_nonce' );
		echo '<p><label for="smcq_exam_access"><strong>' . esc_html__( 'Access', 'smart-mcq-manager' ) . '</strong></label><br />';
		echo '<select id="smcq_exam_access" name="smcq_exam_access">';
		echo '<option value="free" ' . selected( 'free', $mode, false ) . '>' . esc_html__( 'Free examination', 'smart-mcq-manager' ) . '</option>';
		echo '<option value="premium" ' . selected( 'premium', $mode, false ) . '>' . esc_html__( 'Premium — requires a qualifying WooCommerce purchase', 'smart-mcq-manager' ) . '</option>';
		echo '</select></p>';
		echo '<p><label for="smcq_exam_products"><strong>' . esc_html__( 'Qualifying WooCommerce product IDs (comma-separated)', 'smart-mcq-manager' ) . '</strong></label><br />';
		echo '<input class="widefat" id="smcq_exam_products" name="smcq_exam_products" value="' . esc_attr( implode( ',', $ids ) ) . '" placeholder="123, 456" /></p>';
		echo '<p class="description">' . esc_html__( 'Buying ANY listed product unlocks this exam. List the same product on several exams to sell a bundle. Use published simple/virtual products; set the price in WooCommerce.', 'smart-mcq-manager' ) . '</p>';
		echo '<p><label for="smcq_exam_access_days"><strong>' . esc_html__( 'Access validity in days (0 = no expiry)', 'smart-mcq-manager' ) . '</strong></label><br />';
		echo '<input type="number" min="0" max="36500" id="smcq_exam_access_days" name="smcq_exam_access_days" value="' . esc_attr( $days ) . '" /></p>';
		echo '<p class="description">' . esc_html__( 'Duration starts when payment is confirmed. Refunded, cancelled, pending and on-hold orders do not qualify. For genuine recurring billing, WooCommerce Subscriptions is required; only active subscriptions qualify.', 'smart-mcq-manager' ) . '</p>';
		if ( ! self::woocommerce_active() ) {
			echo '<p><strong>' . esc_html__( 'WooCommerce is not active: premium exams will remain locked.', 'smart-mcq-manager' ) . '</strong></p>';
		}
	}

	public static function save( $post_id, $post ) {
		if ( ! $post || 'smcq_exam' !== $post->post_type || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['smcq_commerce_nonce'] ) || ! is_string( $_POST['smcq_commerce_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smcq_commerce_nonce'] ) ), 'smcq_commerce_save' ) ) {
			return;
		}
		$mode = isset( $_POST['smcq_exam_access'] ) && is_string( $_POST['smcq_exam_access'] ) ? sanitize_key( wp_unslash( $_POST['smcq_exam_access'] ) ) : 'free';
		$raw_ids = isset( $_POST['smcq_exam_products'] ) && is_string( $_POST['smcq_exam_products'] ) ? wp_unslash( $_POST['smcq_exam_products'] ) : '';
		$days = isset( $_POST['smcq_exam_access_days'] ) && is_scalar( $_POST['smcq_exam_access_days'] ) ? wp_unslash( $_POST['smcq_exam_access_days'] ) : 0;
		// Fail closed: a premium exam cannot become free when product IDs are missing.
		update_post_meta( $post_id, self::META_MODE, 'premium' === $mode ? 'premium' : 'free' );
		update_post_meta( $post_id, self::META_PRODUCTS, self::product_ids( $raw_ids ) );
		update_post_meta( $post_id, self::META_DAYS, self::valid_duration( $days ) );
	}

	public static function notice() {
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) || self::woocommerce_active() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'smcq_exam' === $screen->post_type ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Smart MCQ: WooCommerce is inactive. Free exams still work; premium exam access requires WooCommerce.', 'smart-mcq-manager' ) . '</p></div>';
		}
	}

	public static function woocommerce_active() {
		return function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_product' );
	}

	/** Pure decision for order status and time-limited entitlements; now and paid_at are Unix UTC seconds. */
	public static function valid_entitlement( $status, $paid_at, $days, $now ) {
		if ( ! in_array( $status, array( 'processing', 'completed' ), true ) || ! is_numeric( $paid_at ) || (int) $paid_at <= 0 || (int) $paid_at > (int) $now ) {
			return false;
		}
		return 0 === (int) $days || (int) $now < (int) $paid_at + (int) $days * DAY_IN_SECONDS;
	}

	/** Includes subscription products only when the official extension confirms an active subscription. */
	public static function is_subscription_product( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
			return false;
		}
		foreach ( array( 'subscription', 'variable-subscription', 'subscription_variation' ) as $type ) {
			if ( $product->is_type( $type ) ) {
				return true;
			}
		}
		return false;
	}

	/** A product that has been fully refunded is not a qualifying purchase, even if order stays completed. */
	public static function has_unrefunded_item( $order, $product_id ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return false;
		}
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) || ! method_exists( $item, 'get_quantity' ) ) {
				continue;
			}
			if ( (int) $item->get_product_id() !== (int) $product_id && ( ! method_exists( $item, 'get_variation_id' ) || (int) $item->get_variation_id() !== (int) $product_id ) ) {
				continue;
			}
			$refunded = method_exists( $order, 'get_qty_refunded_for_item' ) && method_exists( $item, 'get_id' ) ? abs( (float) $order->get_qty_refunded_for_item( $item->get_id() ) ) : 0;
			if ( (float) $item->get_quantity() > $refunded ) {
				return true;
			}
		}
		return false;
	}

	/** Server-only payment verification using WooCommerce CRUD (works with HPOS). */
	public static function allowed( $exam_id, $user_id ) {
		if ( ! self::premium( $exam_id ) ) {
			return true;
		}
		if ( $user_id < 1 || ! self::woocommerce_active() ) {
			return false;
		}
		$ids = self::product_ids( get_post_meta( $exam_id, self::META_PRODUCTS, true ) );
		if ( ! $ids ) {
			return false;
		}
		$days = self::valid_duration( get_post_meta( $exam_id, self::META_DAYS, true ) );
		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			if ( self::is_subscription_product( $product ) ) {
				if ( function_exists( 'wcs_user_has_subscription' ) && wcs_user_has_subscription( $user_id, $id, 'active' ) ) {
					return true;
				}
				// Do not treat a cancelled/expired subscription's old paid order as lifetime access.
				continue;
			}
			$products[ $id ] = true;
		}
		if ( ! $products ) {
			return false;
		}
		// Restrict orders to the logged-in customer's ID: guest email matches cannot grant access.
		// Evaluate the live order/refund state on each check, so revocation is immediate.
		for ( $page = 1; $page <= self::MAX_ORDER_PAGES; ++$page ) {
			$orders = wc_get_orders( array(
				'customer_id' => (int) $user_id,
				'status' => array( 'wc-processing', 'wc-completed' ),
				'limit' => 50,
				'paged' => $page,
				'orderby' => 'date',
				'order' => 'DESC',
				'return' => 'objects',
			) );
			if ( ! is_array( $orders ) || ! $orders ) {
				break;
			}
			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_user_id' ) || (int) $order->get_user_id() !== (int) $user_id ) {
					continue;
				}
				$paid = $order->get_date_paid();
				if ( ! $paid && 'completed' === $order->get_status() ) {
					$paid = $order->get_date_completed();
				}
				$paid_at = $paid && method_exists( $paid, 'getTimestamp' ) ? $paid->getTimestamp() : 0;
				if ( ! self::valid_entitlement( $order->get_status(), $paid_at, $days, time() ) ) {
					continue;
				}
				foreach ( array_keys( $products ) as $id ) {
					if ( self::has_unrefunded_item( $order, $id ) ) {
						return true;
					}
				}
			}
			if ( count( $orders ) < 50 ) {
				break;
			}
		}
		return false;
	}

	public static function check( $exam_id, $user_id ) {
		return self::allowed( $exam_id, $user_id ) ? true : new WP_Error( 'smcq_purchase_required', __( 'This is a premium exam. Purchase a qualifying product to participate.', 'smart-mcq-manager' ), array( 'status' => 403 ) );
	}

	/** Render a public product link, without leaking payment/order details. */
	public static function paywall( $exam_id ) {
		if ( ! self::woocommerce_active() ) {
			return '<div class="smcq-paywall"><p>' . esc_html__( 'Premium examination access is temporarily unavailable. Please contact the site administrator.', 'smart-mcq-manager' ) . '</p></div>';
		}
		$ids = self::product_ids( get_post_meta( $exam_id, self::META_PRODUCTS, true ) );
		$html = '<section class="smcq-paywall"><h3>' . esc_html__( 'Premium Examination', 'smart-mcq-manager' ) . '</h3><p>' . esc_html__( 'Purchase an exam pass or package, then return here while logged in.', 'smart-mcq-manager' ) . '</p><ul>';
		$found = false;
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== get_post_status( $id ) || ! $product->is_purchasable() ) {
				continue;
			}
			$found = true;
			$html .= '<li><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $product->get_name() ) . '</a> — ' . wp_kses_post( $product->get_price_html() ) . '</li>';
		}
		$html .= '</ul>';
		if ( ! $found ) {
			$html .= '<p>' . esc_html__( 'No purchase option is currently available. Contact the administrator.', 'smart-mcq-manager' ) . '</p>';
		}
		return $html . '</section>';
	}
}