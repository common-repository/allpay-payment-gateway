<?php
/* Allpay Payment Gateway Class */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Allpay extends WC_Payment_Gateway {

	function __construct() {

		$this->id = "allpay-payment-gateway";

		$this->method_title = __( 'Bank cards payments Allpay', 'allpay-payment-gateway' );

		$this->method_description = __( 'Allpay Payment Gateway Plug-in for WooCommerce', 'allpay-payment-gateway' );

		$this->title = __( 'Bank cards payments Allpay', 'allpay-payment-gateway' );

		$this->icon = null;

		$this->has_fields = true;

		$this->init_form_fields();

		$this->init_settings();
		
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );

		add_action( 'woocommerce_api_allpay' , array($this, 'webhook') );
		
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	} 

	public function webhook() {  
		$chunks = [];
		$wh_params = ['amount', 'order_id', 'currency', 'status', 'card_mask', 'card_brand', 'foreign_card', 'add_field_1', 'add_field_2'];
		foreach($wh_params as $k) {
			if(isset($_POST[$k])) {
				$chunks[$k] = sanitize_text_field($_POST[$k]);
			}
		} 
		$sign 		= $this->get_signature($chunks); 
		$order_id 	= (int)$_REQUEST['order_id'];
		$status 	= (int)$_REQUEST['status'];
		if($order_id > 0 && $status == 1 && $sign == $_REQUEST['sign']) {
			$customer_order = new WC_Order( $order_id );
			$customer_order->payment_complete();
			$customer_order->reduce_order_stock(); 			
		} 
		exit();
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'allpay-payment-gateway' ),
				'label'		=> __( 'Enable this payment gateway', 'allpay-payment-gateway' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'allpay-payment-gateway' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'allpay-payment-gateway' ),
				'default'	=> __( 'Credit card', 'allpay-payment-gateway' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'allpay-payment-gateway' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'allpay-payment-gateway' ),
				'default'	=> __( 'Pay securely using your credit card.', 'allpay-payment-gateway' ),
				'css'		=> 'max-width:350px;'
			),
			'api_login' => array(
				'title'		=> __( 'API login', 'allpay-payment-gateway' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Allpay API Login', 'allpay-payment-gateway' ),
			),
			'api_key' => array(
				'title'		=> __( 'API key', 'allpay-payment-gateway' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'Allpay API Key', 'allpay-payment-gateway' ),
			),
			'installment_n' => array(
				'title' => __( 'Installment max payments', 'allpay-payment-gateway' ),
				'type' => 'number',
				'description' => __( 'Maximum number of installment payments. Up to 12, zero to disable.', 'allpay-payment-gateway' ),
				'desc_tip'	=> __( 'Allows client to choose number of payments. Valid for credit cards only (no debit cards)', 'allpay-payment-gateway' ),
				'default' => 0
			),
			'installment_min_order' => array(
				'title' => __( 'Installment min order amount', 'allpay-payment-gateway' ),
				'type' => 'number',
				'description' => __( 'Minimum order amount for installments. Zero for orders of any amount.', 'allpay-payment-gateway' ),
				'desc_tip'	=> __( 'Enables installment option when payment amount equals or above this value', 'allpay-payment-gateway' ),
				'default' => 1000
			), 
			'installment_first_payment' => array(
				'title' => __( 'First payment amount', 'allpay-payment-gateway' ),
				'type' => 'number',
				'description' => __( 'First Installment payment. Zero for auto.', 'allpay-payment-gateway' ),
				'desc_tip'	=> __( 'Makes first payment amount fixed. If set to 0, the system will calculate the first payment', 'allpay-payment-gateway' ),
				'default' => 0
			)
		);		
	}
	
	public function process_payment( $order_id ) {
		global $woocommerce;
		
		$customer_order = new WC_Order( $order_id );

		$environment_url = 'https://allpay.to/app/?show=getpayment&mode=api2';
		
		$user_id = get_current_user_id();

		$first_name = $customer_order->get_shipping_first_name();
		if (trim($first_name) == '') {
			$first_name = $customer_order->get_billing_first_name();
		}
		if (trim($first_name) == '' && $user_id) { 
			$first_name = get_user_meta($user_id, 'first_name', true);
		}
		
		$last_name = $customer_order->get_shipping_last_name();
		if (empty($last_name)) {
			$last_name = $customer_order->get_billing_last_name();
		}
		if (trim($last_name) == '' && $user_id) { 
			$last_name = get_user_meta($user_id, 'last_name', true);
		}
		
		$full_name = trim($first_name . ' ' . $last_name);

		$request = array(
			"login"           		=> $this->api_login,
			"amount"             	=> $customer_order->order_total,
			"currency"				=> get_woocommerce_currency(),
			"lang"					=> $this->get_lang(),
			"order_id"        		=> str_replace( "#", "", $customer_order->get_order_number() ),
			"client_name"			=> $full_name,
			"client_phone"			=> $customer_order->billing_phone,
			"client_email"			=> $customer_order->billing_email,
			"notifications_url"		=> get_home_url() . '/?wc-api=allpay',
			"success_url"			=> $customer_order->get_checkout_order_received_url(),
			"backlink_url"			=> home_url()
		);

		if($this->installment_n > 0 && ((int)$this->installment_min_order == 0 || $this->installment_min_order <= $customer_order->order_total)) {
			$request['tash'] = (int)$this->installment_n;
			if($this->installment_first_payment > 0) {
				$request['tash_first_payment'] = (float)$this->installment_first_payment;
			}
			if($this->installment_fixed == 'yes') {
				$request['tash_fixed'] = 1;				
			}
		}

		$request['sign'] = $this->get_signature($request);
	
		$response = wp_remote_post( $environment_url, array(
			'method'    => 'POST',
			'body'      => http_build_query( $request ),
			'timeout'   => 90,
			'sslverify' => false,
		) );
		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'allpay-payment-gateway' ) );
		}
		if ( empty( $response['body'] ) ) {
			throw new Exception( __( 'Allpay\'s Response was empty.', 'allpay-payment-gateway' ) );
		}
		$response = json_decode($response['body']);
		if($response->error_code > 0) {
			throw new Exception( $response->error_msg );
		}
		return array(
			'result'   => 'success',
			'redirect' => $response->payment_url,
		);
		exit(); 

	}

	public function get_signature($params) {
		ksort($params);
		$chunks = [];
		foreach($params as $k => $v) {
			$v = trim($v);
			if($v !== '' && $k != 'sign') {
				$chunks[] = $v;
			}  
		}
		$signature = implode(':', $chunks) . ':' . $this->api_key;
		$signature = hash('sha256', $signature);
		return $signature;  
	} 
	
	public function validate_fields() {
		return true;
	}
	
	public function do_ssl_check() {	
		$is_ssl_checkout_enabled = apply_filters('woocommerce_force_ssl_checkout', false);
		if (!$is_ssl_checkout_enabled) {
			$message = 'Allpay Payment Gateway is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are forcing the checkout pages to be secured.';
			echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
		}
	}

	public function get_lang() {
		$langs = [
			'en' => 'ENG',
			'ru' => 'RUS',
			'he' => 'HEB'
		];
		$lng = 'en';
		if ( defined('ICL_LANGUAGE_CODE') ) {
			$lng =  ICL_LANGUAGE_CODE;
		} elseif ( function_exists('pll_current_language') ) {
			$lng = pll_current_language();
		} else {
			$locale = get_locale();
			$locale_parts = explode('_', $locale);
			$lng = strtolower($locale_parts[0]);			
		}
		if(isset($langs[$lng])) {
			return $langs[$lng];
		} 
		return 'ENG';
	}

} 