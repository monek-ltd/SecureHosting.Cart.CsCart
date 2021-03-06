<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

if (!defined('BOOTSTRAP')) { die('Access denied'); }

use Tygh\Tygh;

if (defined('PAYMENT_NOTIFICATION')) {
	
	$pp_response = array();
	$pp_response['order_status'] = 'F';
	$pp_response['reason_text'] = __('text_transaction_declined');
    $order_id = !empty($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : 0;

    if ($mode == 'success' && !empty($_REQUEST['order_id'])) {
		$order_info = fn_get_order_info($order_id);
	
		if (empty($processor_data)) {
			$processor_data = fn_get_processor_data($order_info['payment_id']);
		}
	
        $post_data = array();
        $post_data_values = array(
            
            'order_id',
            'status',
            'payment',
            'transactionreference'
        );

        foreach ($post_data_values as $post_data_value) {
            if (isset($_REQUEST[$post_data_value])) {
                $post_data[] = $_REQUEST[$post_data_value];
            }
        }
		 

		$digest = base64_encode(sha1(implode('', $post_data) . $processor_data['processor_params']['shared_secret'], true));

		if($_REQUEST['status'] == 'success') {
			$pp_response['order_status'] = 'P';
			$pp_response['reason_text'] = __('transaction_approved');
			$pp_response['transaction_id'] = $_REQUEST['transactionreference'];
		}
		
	}
	
	if (fn_check_payment_script('secure.php', $order_id)) {
		
        fn_finish_payment($order_id, $pp_response);
        fn_order_placement_routines('route', $order_id);
    }
	
} else {
	
	
	$gbp=0.72;
	
    if ($processor_data['processor_params']['mode'] == 'test') {
        $payment_url = 'https://test.secure-server-hosting.com/secutran/secuitems.php';
    } else {
        $payment_url = 'https://www.secure-server-hosting.com/secutran/secuitems.php';
    }

    
    $confirm_url = fn_url("payment_notification.success?payment=secure&status=success&order_id=$order_id", AREA, 'current');
    $cancel_url = fn_url("payment_notification.fail?payment=secure&status=fail&order_id=$order_id", AREA, 'current');
	$callbackurl = fn_url('', 'C', 'http');
	$callbackData = "cs-api|Secuerhosting|action|callback|order_id|$order_id";
	$products = $order_info['products'];
        $secuitems = '';
        foreach ($products as $product) {
            
            $secuitems .= '[' . $product['product_id'] . '||' . htmlentities($product['product_code'], ENT_QUOTES) . '|' . number_format(($product['price']*$gbp), 2) . '|' . $product['amount'] . '|' . number_format($product['display_subtotal']*$gbp, 2) . ']';
        }
       // print_r($secuitems);die;
	 $transactiontax = 0.0;
        if (!empty($order_info['taxes'][6]['tax_subtotal'])) {
            
                $transactiontax =$order_info['taxes'][6]['tax_subtotal']*$gbp;
            
        }
		
		$shippingcharge = 0.0;
         if (!empty($order_info['shipping_cost'])) {
            $shippingcharge = $order_info['shipping_cost']*$gbp;
        }
      $amount = fn_format_price(($order_info['total']*$gbp), $processor_data['processor_params']['currency']);
    /** @var \Tygh\Location\Manager $location_manager */
    $location_manager = Tygh::$app['location'];

    $post_data = [
        'mid'         => $processor_data['processor_params']['merchant_id'],
        'lang'        => $processor_data['processor_params']['language'],
        'orderid'     => time() . $order_id,
        'orderDesc'   => '#' . $order_id,
        'orderAmount' => $amount,
        'currency'    => $processor_data['processor_params']['currency'],
        'payerEmail'  => $order_info['email'],
        'payerPhone'  => $order_info['phone'],
		'secuitems'  => $secuitems,
		'transactiontax'  => $transactiontax,
		'shippingcharge'  => $shippingcharge,
		'callbackurl'  => $callbackurl,
		'callbackdata'  => $callbackData,
		'secuString'  => '',
        'trType'      => '1',
        'confirmUrl'  => $confirm_url,
        'cancelUrl'   => $cancel_url,
        'billState'   => $location_manager->getLocationField($order_info, 'state', '', BILLING_ADDRESS_PREFIX),
        'billZip'     => $location_manager->getLocationField($order_info, 'zipcode', '', BILLING_ADDRESS_PREFIX),
        'billCity'    => $location_manager->getLocationField($order_info, 'city', '', BILLING_ADDRESS_PREFIX),
        'billAddress' => $location_manager->getLocationField($order_info, 'address', '', BILLING_ADDRESS_PREFIX),
        'shipCountry' => $location_manager->getLocationField($order_info, 'country', '', SHIPPING_ADDRESS_PREFIX),
        'shipState'   => $location_manager->getLocationField($order_info, 'state', '', SHIPPING_ADDRESS_PREFIX),
        'shipZip'     => $location_manager->getLocationField($order_info, 'zipcode', '', SHIPPING_ADDRESS_PREFIX),
        'shipCity'    => $location_manager->getLocationField($order_info, 'city', '', SHIPPING_ADDRESS_PREFIX),
        'shipAddress' => $location_manager->getLocationField($order_info, 'address', '', SHIPPING_ADDRESS_PREFIX),
        'filename'    => $processor_data['processor_params']['shreference'].'/'.$processor_data['processor_params']['filename'],
        'shreference' => $processor_data['processor_params']['shreference'],
        'checkcode'   => $processor_data['processor_params']['checkcode'],
		'first_name'  => $location_manager->getLocationField($order_info, 'firstname', '', BILLING_ADDRESS_PREFIX),
		'last_name'  => $location_manager->getLocationField($order_info, 'lastname', '', BILLING_ADDRESS_PREFIX),
		
    ];

	$post_data['digest'] = base64_encode(sha1(implode('', $post_data) . $processor_data['processor_params']['shared_secret'], true));

    fn_create_payment_form($payment_url, $post_data, 'Securehosting', false);
}
exit;
