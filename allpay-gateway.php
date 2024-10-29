<?php
/**
 * Plugin Name: Allpay payment gateway
 * Plugin URI: https://www.allpay.co.il/integrations/woocommerce
 * Description: Allpay Payment Gateway for WooCommerce.
 * Author: Allpay
 * Author URI: https://allpay.co.il
 * Version: 1.0.4
 * Text Domain: allpay-payment-gateway
 * Domain Path: /languages
 * Tested up to: 6.4
 * WC tested up to: 8.4.0
 * WC requires at least: 3.0
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Include our Gateway Class and register Payment Gateway with WooCommerce

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'allpay_init', 0 );

function allpay_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// plugin translation
	load_plugin_textdomain( 'allpay-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );  

	include_once( 'allpay.php' );

	add_filter( 'woocommerce_payment_gateways', 'allpay_gateway' );
	function allpay_gateway( $methods ) {
		$methods[] = 'WC_Allpay';
		return $methods;
	}
	
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'allpay_action_links' );
function allpay_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=allpay-payment-gateway' ) . '">' . __( 'Settings', 'allpay-payment-gateway' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}