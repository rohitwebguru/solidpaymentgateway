<?php
/**
 * Plugin Name: Solid Payments Gateway
 * Plugin URI: http://localhost/testproject/checkout/
 * Author: Rohit Sharma
 * Author URI: https://rohitfullstackdeveloper.com/
 * Description: Woocommerce Payment Gateway Method.
 * Version: 0.1.0
 * License: GPL2
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: solidpg-payment-woo
 *
 * Class WC_Gateway_SolidPG file.
 *
 * @package WooCommerce\SolidPG
 */

define('SOLIDPG_SANDBOX_URL', 'https://test.solidpayments.net/v1/payments'); 
define('SOLIDPG_LIVE_URL', 'https://fcms.flocash.com/ecom/ecommerce.do');
define('MERCHANT_TOKEN', 'OGFjN2E0Yzk5Mjg5ZTFjZDAxOTI4YjM5YzRjMzAyNmN8VzpmQjVkeXJ4WWVAeWhIZUEjcGY=');
define('MERCHANT_ENTITY_ID', '8ac7a4c99289e1cd01928b3ff1b50278');

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include the currencies functions based on the context (admin or public)
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-main-file.php';
} else {
    require_once plugin_dir_path(__FILE__) . 'public/frontend-main-file.php';
}

require_once plugin_dir_path(__FILE__) . 'includes/solidpg-init.php';

// Check WooCommerce existence during activation
function solidpg_check_wc_existence()
{
    global $wpdb; 
    
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Throw an error message
        wp_die('SolidPG Payments Gateway requires WooCommerce to be installed and activated. Please install and activate WooCommerce first.');
    }

    //  Create Thank You Page
    $page_title = __('SolidPG Thankyou Page','solidpg-payments-woo');    
    $is_page_exist = $wpdb->get_results( "SELECT * from $wpdb->posts where post_title='".$page_title."'" );

    if( empty( $is_page_exist ) ){
        $page_content = '[place_order]';
        $page = array(
            'post_title' => $page_title,
            'post_content' => $page_content,
            'post_status' => 'publish',
            'post_type' => 'page',
        );
        $return_page_id = wp_insert_post($page);
        update_option('solidpg_return_page', $return_page_id);
    }    
}

register_activation_hook(__FILE__, 'solidpg_check_wc_existence');

// Hook into the REST API initialization to register our custom endpoint
add_action('rest_api_init', function() {
    register_rest_route('solidpg/v1', '/payment', [
        'methods' => 'POST',
        'callback' => 'handle_solidpg_payment',
        'permission_callback' => '__return_true', // You can add your own permissions check here
    ]);
});

// Define the callback function for the endpoint
function handle_solidpg_payment(WP_REST_Request $request) {
    // Retrieve the data from the request
    $card_number = $request->get_param('card_number');
    $card_holder = $request->get_param('card_holder');
    $expiry_month = $request->get_param('card_expiryMonth');
    $expiry_year = $request->get_param('card_expiryYear');
    $cvv = $request->get_param('card_cvv');
    $amount = $request->get_param('amount');
    $currency = $request->get_param('currency');
    $payment_brand = $request->get_param('paymentBrand');
    $payment_type = $request->get_param('paymentType');
    $return_url = $request->get_param('shopperResultUrl');
    $merchant_token = MERCHANT_TOKEN; 
    $merchant_entity_id = MERCHANT_ENTITY_ID;
    $solidpg_url = SOLIDPG_SANDBOX_URL; 

    // Prepare data for SolidPG API
    $data = [
        'entityId' => $merchant_entity_id,
        'amount' => $amount,
        'currency' => $currency,
        'paymentBrand' => $payment_brand,
        'paymentType' => $payment_type,
        'card.number' => $card_number,
        'card.holder' => $card_holder,
        'card.expiryMonth' => $expiry_month,
        'card.expiryYear' => $expiry_year,
        'card.cvv' => $cvv,
        'shopperResultUrl' => $return_url,
    ];
  
    // Initialize cURL
    $ch = curl_init($solidpg_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $merchant_token,
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    // Execute the request and capture the response
    $response = curl_exec($ch);
    curl_close($ch);

    // Return the response back to the client
    if ($response === false) {
        return new WP_REST_Response('Payment failed', 500);
    }

    return new WP_REST_Response(json_decode($response), 200);
}

