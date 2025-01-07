<?php
/**
 * Plugin Name: Solid Payments Gateway
 * Plugin URI: http://localhost/testproject/checkout/
 * Author: Rohit Sharma
 * Author URI: https://rohitfullstackdeveloper.com/
 * Description: Woocommerce Payment Gateway Method.
 * Version: 0.1.0
 * License: GPL2
 *  o
 *
 * Class WC_Gateway_SolidPG file.
 *
 * @package WooCommerce\SolidPG
 */

class WC_Gateway_SolidPG extends WC_Payment_Gateway
{

    /**
     * Gateway instructions that will be added to the thank you page and emails.
     *
     * @var string
     */
    public $instructions;

    /**
     * Enable for shipping methods.
     *
     * @var array
     */
    public $enable_for_methods;

    /**
     * Enable for virtual products.
     *
     * @var bool
     */
    public $enable_for_virtual;

    /**
     * Constructor for the gateway.
     */

    public function __construct()
    {
        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // Get settings.
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->enable_for_methods = $this->get_option('enable_for_methods', array());
        $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';
       
    
        // Actions.
         $this->setup_properties(); 
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);

        // Customer Emails.
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        // Hook to display sandbox settings in the admin panel
        add_action('woocommerce_admin_order_data_after_order', array($this, 'output_sandbox_settings'));
        add_action('woocommerce_order_refunded', array($this, 'custom_refund_callback'), 10, 2);
      
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
        $this->id = 'solidpg';
        // $this->plugin_id = 'woocommerce_' . $this->id . '_';
        $this->icon = apply_filters('woocommerce_solodpg_icon', plugins_url('../public/images/solid-payment-logo.png', __FILE__));
        $this->method_title = __('Solid Payments', 'solidpg-payment-woo');        
        $this->method_description = __('Have your customers pay with Solid.', 'solidpg-payment-woo');
        $this->has_fields = false;
        // $this->supports = array('products');
        // $this->enabled = 'yes';
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'solidpg-payment-woo'),
                'label' => __('Enable Solid Payments', 'solidpg-payment-woo'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes',
            ),            
            'merchant_token' => array(
                'title' => __('Merchant Token', 'solidpg-payment-woo'),
                'type' => 'text',
                'description' => __('Enter your Merchant Token', 'solidpg-payment-woo'),
                'id' => 'merchant_token',
            ),
            'merchant_entity_id' => array(
                'title' => __('Merchant Entity ID', 'solidpg-payment-woo'),
                'type' => 'text',
                'description' => __('Enter your Merchant Entity ID', 'solidpg-payment-woo'),
                'id' => 'merchant_entity_id',
            ),
            'sandbox_enabled' => array(
                'title' => __('Sandbox Mode', 'solidpg-payment-woo'),
                'type' => 'checkbox',
                'label' => __('Enable Sandbox Mode', 'solidpg-payment-woo'),
                'default' => 'yes',
            ),
        );
    }

    
    public function custom_refund_callback($order_id, $refund_id) {
        // Get the order object
        $order = wc_get_order($order_id);
    
        if (!$order) {
            error_log('Order not found for refund callback.');
            return;
        }
    
        // Get refund object
        $refund = wc_get_order($refund_id);
    
        if (!$refund) {
            error_log('Refund not found.');
            return;
        }
    
        $refund_amount = $refund->get_amount();
        $currency = $order->get_currency(); 
        $transaction_id = $order->get_transaction_id(); 
    
        $solidpg_order_id = get_post_meta($order_id, 'solidpg_api_id', true);
        error_log("order details: " . $solidpg_order_id);
        error_log("refund_amount: " . $refund_amount);
        error_log("currency: " . $currency);
        error_log("transaction_id: " . $transaction_id);
        error_log("order: " . $order);
        if (!$solidpg_order_id) {
            error_log('This Order has not been placed with Solid Pyament Gateway');
            return;
        }
        $flocash_settings = get_option('woocommerce_solidpg_settings', array());

        if ($flocash_settings['sandbox_enabled'] == 'yes') {
            $url = SOLIDPG_SANDBOX_URL;
        }else{
            $url = SOLIDPG_LIVE_URL;
        }
        // Prepare refund API request data
        $api_url = `$url/$solidpg_order_id`;
        $entityId = $flocash_settings['merchant_entity_id'];
        $api_token = $flocash_settings['merchant_token'];
        $api_data = [
            'entityId' => $entityId,
            'amount' => $refund_amount,
            'currency' => $currency,
            'paymentType' => 'RF',
        ];
     
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($api_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_token,
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    
        // Execute the request and capture the response
        $response = curl_exec($ch);
        curl_close($ch);
    
        // Handle the API response
        if (is_wp_error($response)) {
            error_log('Refund API Error: ' . $response);
        } else {
            $response_body = wp_remote_retrieve_body($response);
            error_log('Refund API Response: ' . $response_body);
        }
    }
    
  
    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available()
    {
        $order = null;
        $needs_shipping = false;

        $available = parent::is_available();

        // Test if shipping is needed first.
        if (WC()->cart && WC()->cart->needs_shipping()) {
            $needs_shipping = true;
        } elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
            $order_id = absint(get_query_var('order-pay'));
            $order = wc_get_order($order_id);

            // Test if order needs shipping.
            if ($order && 0 < count($order->get_items())) {
                foreach ($order->get_items() as $item) {
                    $_product = $item->get_product();
                    if ($_product && $_product->needs_shipping()) {
                        $needs_shipping = true;
                        break;
                    }
                }
            }
        }

        $needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

        // Virtual order, with virtual disabled.
        if (!$this->enable_for_virtual && !$needs_shipping) {
            return false;
        }

        // Only apply if all packages are being shipped via chosen method, or order is virtual.
        if (!empty($this->enable_for_methods) && $needs_shipping) {
            $order_shipping_items = is_object($order) ? $order->get_shipping_methods() : false;
            $chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

            if ($order_shipping_items) {
                $canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids($order_shipping_items);
            } else {
                $canonical_rate_ids = $this->get_canonical_package_rate_ids($chosen_shipping_methods_session);
            }

            if (!count($this->get_matching_rates($canonical_rate_ids))) {
                return false;
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            wc_get_logger()->info('SolidPG availability check: ' . ($available ? 'Available' : 'Unavailable'), array('source' => 'solidpg'));
        }

        return parent::is_available();
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings()
    {
        if (is_admin()) {
            // phpcs:disable WordPress.Security.NonceVerification
            if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
                return false;
            }
            if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
                return false;
            }
            if (!isset($_REQUEST['section']) || 'solidpg-payment-woo' !== $_REQUEST['section']) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }

        return false;
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options()
    {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if (!$this->is_accessing_settings()) {
            return array();
        }

        $data_store = WC_Data_Store::load('shipping-zone');
        $raw_zones = $data_store->get_zones();

        foreach ($raw_zones as $raw_zone) {
            $zones[] = new WC_Shipping_Zone($raw_zone);
        }

        $zones[] = new WC_Shipping_Zone(0);

        $options = array();
        foreach (WC()->shipping()->load_shipping_methods() as $method) {

            $options[$method->get_method_title()] = array();

            // Translators: %1$s shipping method name.
            $options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'solidpg-payment-woo'), $method->get_method_title());

            foreach ($zones as $zone) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

                    if ($shipping_method_instance->id !== $method->id) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf(__('%1$s (#%2$s)', 'solidpg-payment-woo'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf(__('%1$s &ndash; %2$s', 'solidpg-payment-woo'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'solidpg-payment-woo'), $option_instance_title);

                    $options[$method->get_method_title()][$option_id] = $option_title;
                }
            }
        }

        return $options;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     */
    private function get_canonical_order_shipping_item_rate_ids($order_shipping_items)
    {

        $canonical_rate_ids = array();

        foreach ($order_shipping_items as $order_shipping_item) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        }

        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     */
    private function get_canonical_package_rate_ids($chosen_package_rate_ids)
    {

        $shipping_packages = WC()->shipping()->get_packages();
        $canonical_rate_ids = array();

        if (!empty($chosen_package_rate_ids) && is_array($chosen_package_rate_ids)) {
            foreach ($chosen_package_rate_ids as $package_key => $chosen_package_rate_id) {
                if (!empty($shipping_packages[$package_key]['rates'][$chosen_package_rate_id])) {
                    $chosen_rate = $shipping_packages[$package_key]['rates'][$chosen_package_rate_id];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @since  3.4.0
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     */
    private function get_matching_rates($rate_ids)
    {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique(array_merge(array_intersect($this->enable_for_methods, $rate_ids), array_intersect($this->enable_for_methods, array_unique(array_map('wc_get_string_before_colon', $rate_ids)))));
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_total() > 0) {
            $this->solidpg_payment_processing( $order );
        } else {
            $order->payment_complete();
        }

        // Remove cart.
        WC()->cart->empty_cart();

        // Return thankyou redirect.
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    private function solidpg_payment_processing($order)
    {
        $amount = intval($order->get_total());        
        $currency_code = $order->get_currency();
        $customer_id = $order->get_customer_id();
        $order_id = $order->get_id();        
        $total_quantity = 0;
        // Get and Loop Over Order Items
        foreach ($order->get_items() as $item_id => $item) {
            $product_name = $item->get_name();
            $item_price = $item->get_subtotal();
            $item_quantity = $item->get_quantity();
            $total_quantity+= $item_quantity;
        }
        $flocash_settings = get_option('woocommerce_solidpg_settings', array());
      
        if ($flocash_settings['sandbox_enabled'] == 'yes') {
            $url = SOLIDPG_SANDBOX_URL;
        }else{
            $url = SOLIDPG_LIVE_URL;
        }
        // $url = 'https://test.solidpayments.net/v1/payments';

        $response = wp_remote_post($url, array('timeout' => 30));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }

        if (200 !== wp_remote_retrieve_response_code($response)) {
            $order->update_status(apply_filters('woocommerce_solidpg_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order), __('Payments pending.', 'solidpg-payment-woo'));
        }

        if (200 === wp_remote_retrieve_response_code($response)) {
            $response_body = wp_remote_retrieve_body($response);
            var_dump($response_body['message']);
            if ('Thank you! Your payment was successful' === $response_body['message']) {
                $order->payment_complete();

                // Remove cart.
                WC()->cart->empty_cart();

                return array(
                    'resultwaah' => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            }
        }
    }
    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
    }

    /**
     * Change payment complete order status to completed for solidpg orders.
     *
     * @since  3.1.0
     * @param  string         $status Current order status.
     * @param  int            $order_id Order ID.
     * @param  WC_Order|false $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
        if ($order && 'solidpg-payment-woo' === $order->get_payment_method()) {
            $status = 'completed';
        }
        return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool     $sent_to_admin  Sent to admin.
     * @param bool     $plain_text Email format: plain text or HTML.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }
}
