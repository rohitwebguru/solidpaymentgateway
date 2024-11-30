<?php

class SolidPG_Init
{


    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'solidpg_payment_init'), 11);
        add_filter('woocommerce_payment_gateways', array($this, 'add_to_woo_solidpg_payment_gateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'enable_custom_gateway'));
    }

    public function solidpg_payment_init()
    {
        if (class_exists('WC_Payment_Gateway')) {
            require_once plugin_dir_path(__FILE__) . 'class-wc-payment-gateway-solidpg.php';
            require_once plugin_dir_path(__FILE__) . 'solidpg-order-statuses.php';
            require_once plugin_dir_path(__FILE__) . 'solidpg-currencies.php';
        }
    }

    public function add_to_woo_solidpg_payment_gateway($gateways)
    {
        $gateways[] = 'WC_Gateway_SolidPG'; 
        return $gateways;
    }


    public function enable_custom_gateway($available_gateways)
    {
       
        if (is_checkout()) {
            $available_gateways['solid_payments'] = new WC_Gateway_SolidPG();
        }
        // echo"<pre>"; print_r($available_gateways); exit;
        return $available_gateways;
    }
}

$solidpg_Init = new SolidPG_Init();
