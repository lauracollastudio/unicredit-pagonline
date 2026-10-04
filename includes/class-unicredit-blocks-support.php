<?php
/**
 * Registers this gateway with the WooCommerce Checkout block.
 *
 * A classic WC_Payment_Gateway only plugs into the legacy [woocommerce_checkout]
 * shortcode automatically. The block-based checkout (wp:woocommerce/checkout,
 * the WooCommerce default since 8.3) requires a separate registration: this PHP
 * side (payment method data + script handles) plus assets/js/blocks.js on the
 * frontend. Without this, the gateway stays invisible on block checkouts even
 * though it's fully enabled and is_available() returns true.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_Unicredit_PagOnline_Blocks_Support extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

	protected $name = 'unicredit_pagonline';
	private $gateway;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_unicredit_pagonline_settings', array() );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
	}

	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-unicredit-pagonline-blocks',
			plugins_url( 'assets/js/blocks.js', UNICREDIT_PAGONLINE_MAIN_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			filemtime( UNICREDIT_PAGONLINE_DIR . 'assets/js/blocks.js' ),
			true
		);
		return array( 'wc-unicredit-pagonline-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->gateway ? $this->gateway->title : '',
			'description' => $this->gateway ? $this->gateway->description : '',
			'supports'    => $this->gateway ? array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ) : array(),
		);
	}
}
