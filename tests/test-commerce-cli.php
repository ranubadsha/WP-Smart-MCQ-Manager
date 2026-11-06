<?php
/** Standalone Step 11 pure and mock WooCommerce entitlement checks. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
require_once dirname( __DIR__ ) . '/includes/class-smcq-commerce.php';

$GLOBALS['mcq_meta'] = array();
$GLOBALS['mcq_products'] = array();
$GLOBALS['mcq_orders'] = array();
$GLOBALS['mcq_subscription'] = false;
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['mcq_meta'][ $id ][ $key ] ) ? $GLOBALS['mcq_meta'][ $id ][ $key ] : '';
}
function wc_get_product( $id ) { return isset( $GLOBALS['mcq_products'][ $id ] ) ? $GLOBALS['mcq_products'][ $id ] : false; }
function get_post_status( $id ) { return isset( $GLOBALS['mcq_products'][ $id ] ) ? 'publish' : false; }
function wc_get_orders( $args ) {
	$out = array();
	foreach ( $GLOBALS['mcq_orders'] as $order ) {
		if ( (int) $order->get_user_id() === (int) $args['customer_id'] && in_array( 'wc-' . $order->get_status(), $args['status'], true ) ) {
			$out[] = $order;
		}
	}
	return array_slice( $out, ( $args['paged'] - 1 ) * $args['limit'], $args['limit'] );
}
function wcs_user_has_subscription( $user, $product, $status ) { return $GLOBALS['mcq_subscription'] && $user === 14 && $product === 5 && $status === 'active'; }
class WP_Error {
	public $code;
	public function __construct( $code, $msg, $data = null ) { $this->code = $code; }
}
function __( $text, $domain ) { return $text; }

class FakeProduct {
	private $type;
	public function __construct( $type = 'simple' ) { $this->type = $type; }
	public function is_type( $type ) { return $this->type === $type; }
}
class FakeItem {
	private $id, $product, $qty;
	public function __construct( $id, $product, $qty ) { $this->id = $id; $this->product = $product; $this->qty = $qty; }
	public function get_id() { return $this->id; }
	public function get_product_id() { return $this->product; }
	public function get_variation_id() { return 0; }
	public function get_quantity() { return $this->qty; }
}
class FakeDate {
	private $value;
	public function __construct( $value ) { $this->value = $value; }
	public function getTimestamp() { return $this->value; }
}
class FakeOrder {
	private $user, $status, $paid, $items, $refunded;
	public function __construct( $user, $status, $paid, $items, $refunded = array() ) {
		$this->user=$user; $this->status=$status; $this->paid=$paid; $this->items=$items; $this->refunded=$refunded;
	}
	public function get_user_id() { return $this->user; }
	public function get_status() { return $this->status; }
	public function get_date_paid() { return $this->paid ? new FakeDate( $this->paid ) : null; }
	public function get_date_completed() { return null; }
	public function get_items( $kind ) { return $this->items; }
	public function get_qty_refunded_for_item( $id ) { return isset( $this->refunded[$id] ) ? -$this->refunded[$id] : 0; }
}
function eq( $actual, $expected, $label ) { if ( $actual !== $expected ) { throw new RuntimeException( $label . ': ' . var_export( $actual, true ) . ' !== ' . var_export( $expected, true ) ); } }

eq( SMCQ_Commerce::product_ids( '001, 123,123; 45, -3, nope, 0, 7foo, 98' ), array( 123, 45, 98 ), 'safe deduplicated product IDs' );
eq( SMCQ_Commerce::product_ids( array( 1, 2, 2 ) ), array( 1, 2 ), 'IDs from previous saved arrays' );
eq( SMCQ_Commerce::product_ids( new stdClass() ), array(), 'object input safely rejected' );
eq( SMCQ_Commerce::valid_duration( '30' ), 30, 'valid day count' );
eq( SMCQ_Commerce::valid_duration( '-10' ), 0, 'negative duration rejected' );
eq( SMCQ_Commerce::valid_duration( array( '3' ) ), 0, 'array duration rejected' );
eq( SMCQ_Commerce::valid_entitlement( 'completed', 86400, 2, 86400 * 2 ), true, 'within window' );
eq( SMCQ_Commerce::valid_entitlement( 'completed', 86400, 2, 86400 * 3 ), false, 'expires exactly on boundary' );
eq( SMCQ_Commerce::valid_entitlement( 'processing', 86400, 0, 86400 * 20 ), true, 'lifetime' );
eq( SMCQ_Commerce::valid_entitlement( 'refunded', 86400, 0, 86400 * 2 ), false, 'refunded status refused' );
eq( SMCQ_Commerce::valid_entitlement( 'on-hold', 86400, 0, 86400 * 2 ), false, 'unpaid on-hold refused' );
eq( SMCQ_Commerce::valid_entitlement( 'completed', 86400 * 4, 0, 86400 * 3 ), false, 'future paid timestamp refused' );

// Exam 100 requires either product 5 or 6; exam 101 references product 5 as a shared package.
$GLOBALS['mcq_meta'][100] = array( '_smcq_exam_access' => 'premium', '_smcq_exam_products' => array( 5, 6 ), '_smcq_exam_access_days' => 30 );
$GLOBALS['mcq_meta'][101] = array( '_smcq_exam_access' => 'premium', '_smcq_exam_products' => array( 5 ), '_smcq_exam_access_days' => 0 );
$GLOBALS['mcq_products'] = array( 5 => new FakeProduct(), 6 => new FakeProduct() );
$item = new FakeItem( 10, 5, 1 );
$GLOBALS['mcq_orders'] = array( new FakeOrder( 14, 'completed', time() - 100, array( $item ) ) );
eq( SMCQ_Commerce::allowed( 100, 14 ), true, 'paid student may access premium exam' );
eq( SMCQ_Commerce::allowed( 101, 14 ), true, 'one product grants access to a package of exams' );
eq( SMCQ_Commerce::allowed( 100, 99 ), false, 'different account cannot use order' );
eq( SMCQ_Commerce::allowed( 100, 0 ), false, 'guest rejected' );
eq( SMCQ_Commerce::allowed( 999, 99 ), true, 'free exam unaffected' );
$GLOBALS['mcq_orders'] = array( new FakeOrder( 14, 'completed', time() - 100, array( $item ), array( 10 => 1 ) ) );
eq( SMCQ_Commerce::allowed( 100, 14 ), false, 'fully refunded line item revoked' );
$GLOBALS['mcq_orders'] = array( new FakeOrder( 14, 'on-hold', time() - 100, array( $item ) ) );
eq( SMCQ_Commerce::allowed( 100, 14 ), false, 'on-hold order denied' );
$GLOBALS['mcq_orders'] = array( new FakeOrder( 14, 'completed', time() - 40 * DAY_IN_SECONDS, array( $item ) ) );
eq( SMCQ_Commerce::allowed( 100, 14 ), false, 'expired 30-day exam access denied' );
eq( SMCQ_Commerce::allowed( 101, 14 ), true, 'lifetime access unaffected' );
$GLOBALS['mcq_meta'][102] = array( '_smcq_exam_access' => 'premium', '_smcq_exam_products' => array(), '_smcq_exam_access_days' => 0 );
eq( SMCQ_Commerce::allowed( 102, 14 ), false, 'premium with no products fails closed' );
$GLOBALS['mcq_products'][5] = new FakeProduct( 'subscription' );
eq( SMCQ_Commerce::allowed( 101, 14 ), false, 'cancelled subscription cannot use old order' );
$GLOBALS['mcq_subscription'] = true;
eq( SMCQ_Commerce::allowed( 101, 14 ), true, 'active WooCommerce Subscription qualifies' );
eq( SMCQ_Commerce::allowed( 101, 99 ), false, 'active subscription cannot be shared' );
echo "Step 11 WooCommerce premium access helper tests passed.\n";