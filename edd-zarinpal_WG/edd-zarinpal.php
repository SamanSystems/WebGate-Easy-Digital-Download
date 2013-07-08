<?php
/**
	Plugin Name: Zarinpal Zarin Gate for EDD
	Version: 1.5
	Description: این افزونه درگاه <a href="https://zarinpal.com/" target="_blank">زرین پال</a> را به افزونه Easy Digital Downloads اضافه می&zwnj;کند. این افزونه با نسخه 1.4.1.1 سازگار است.
	Plugin URI: https://zarinpal.com/Labs/Details/WP-EDD
	
	Author: M.Amini
	Author URI: http://haftir.ir
**/
@session_start();

function zp_wg_edd_rial ($formatted, $currency, $price) {
	return $price . 'ریال';
}
add_filter( 'edd_rial_currency_filter_after', 'edd_rial', 10, 3 );

function zp_wg_add_gateway ($gateways) {
	$gateways['zarinpal'] = array('admin_label' => 'زرین&zwnj;پال', 'checkout_label' => 'پرداخت آنلاین با زرین&zwnj;پال');
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'zp_wg_add_gateway' );

function zp_wg_cc_form () {
	do_action( 'zp_wg_cc_form_action' );
}
add_filter( 'edd_zarinpal_cc_form', 'zp_wg_cc_form' );

function zp_wg_process_payment ($purchase_data) {
	global $edd_options;
	
	if (edd_is_test_mode()) {
		$api = '1';
	} else {
		$api = $edd_options['zarinpal_api'];
	}
	
	$payment_data = array( 
		'price' => $purchase_data['price'], 
		'date' => $purchase_data['date'], 
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending'
	);
	$payment = edd_insert_payment($payment_data);
	
	if ($payment) {
		$_SESSION['zarinpal_payment'] = $payment;
		$return = urlencode(add_query_arg('order', 'zarinpal', get_permalink($edd_options['success_page'])));
		$price = $payment_data['price'] / 10 ;
		$_SESSION['zarinpal_fi'] = $price;
		$desc='پرداخت سفارش شاره'.$payment ;
		$client = new SoapClient('https://www..zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
		$res = $client->PaymentRequest(
		array(
					'MerchantID' 	=> $api ,
					'Amount' 		=> $price ,
					'Description' 	=> urlencode($desc) ,
					'Email' 		=> $payment_data['user_email'] ,
					'Mobile' 		=> '' ,
					'CallbackURL' 	=> urldecode($return)
					));
		
        $redirect_page = "https://www.zarinpal.com/pg/Transactions/StartPay/" . $res->Authority; 
		wp_redirect($redirect_page);
		exit;
	} else {
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_zarinpal', 'zp_wg_process_payment');

function zp_wg_verify() {
	global $edd_options;
	if (isset($_GET['order']) and $_GET['order'] == 'zarinpal' and $_POST['au'] and $_POST['refid']) {
		$payment = $_SESSION['zarinpal_payment'];
		if (edd_is_test_mode()) {
			$api = '1';
		} else {
			$api = $edd_options['zarinpal_api'];
		}
		$au = $_POST['au'];
		$refID = $_POST['refid'];
		$amount = $_SESSION['zarinpal_fi'];
		$client = new SoapClient('https://www..zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
		$res = $client->PaymentVerification(
		array(
				'MerchantID'	 => $api ,
				'Authority' 	 => $au ,
				'Amount'		 => $amount
				)
		);
		if (intval($res->status) == 100) {
			edd_update_payment_status($payment, 'publish');
		}
	}
}
add_action('init', 'zp_wg_verify');

function zp_wg_add_settings ($settings) {
	$zarinpal_settings = array (
		array (
			'id'		=>	'zarinpal-zg_settings',
			'name'		=>	'<strong>پیکربندی زرین&zwnj;پال</strong>',
			'desc'		=>	'پیکربندی زرین&zwnj;پال با تنظیمات فروشگاه',
			'type'		=>	'header'
		),
		array (
			'id'		=>	'zarinpal_api',
			'name'		=>	'مرچنت کد ',
			'desc'		=>	'در صورت فعال کردن حالت آزمایشی نیاز به این کلید نیست',
			'type'		=>	'text',
			'size'		=>	'regular'
		)
	);
	return array_merge( $settings, $zarinpal_settings );
}
add_filter('edd_settings_gateways', 'zp_wg_add_settings');
?>
