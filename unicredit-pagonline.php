<?php
/**
 * Plugin Name: UniCredit PagOnline Imprese Gateway
 * Description: Custom WooCommerce payment gateway for UniCredit PagOnline Imprese (hosted payment page, SOAP init()/verify()).
 * Version: 1.0.0
 * Text Domain: unicredit-pagonline
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UNICREDIT_PAGONLINE_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', 'unicredit_pagonline_bootstrap', 11 );
function unicredit_pagonline_bootstrap() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	require_once UNICREDIT_PAGONLINE_DIR . 'includes/class-unicredit-api.php';
	require_once UNICREDIT_PAGONLINE_DIR . 'includes/class-unicredit-gateway.php';
}

add_filter( 'woocommerce_payment_gateways', 'unicredit_pagonline_register_gateway' );
function unicredit_pagonline_register_gateway( $gateways ) {
	$gateways[] = 'WC_Gateway_Unicredit_PagOnline';
	return $gateways;
}

add_filter( 'cron_schedules', 'unicredit_pagonline_cron_schedule' );
function unicredit_pagonline_cron_schedule( $schedules ) {
	$schedules['unicredit_fifteen_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 15 Minutes', 'unicredit-pagonline' ),
	);
	return $schedules;
}

register_activation_hook( __FILE__, 'unicredit_pagonline_activate' );
function unicredit_pagonline_activate() {
	if ( ! wp_next_scheduled( 'unicredit_pagonline_check_pending' ) ) {
		wp_schedule_event( time() + 900, 'unicredit_fifteen_minutes', 'unicredit_pagonline_check_pending' );
	}
}

register_deactivation_hook( __FILE__, 'unicredit_pagonline_deactivate' );
function unicredit_pagonline_deactivate() {
	wp_clear_scheduled_hook( 'unicredit_pagonline_check_pending' );
}
