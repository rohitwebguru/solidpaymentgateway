<?php

class SolidPG_Init
{


    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'solidpg_payment_init'), 11);
        add_filter('woocommerce_payment_gateways', array($this, 'add_to_woo_solidpg_payment_gateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'enable_custom_gateway'));
        add_action('wp_enqueue_scripts',array($this, 'solidpg_payment_scripts'));
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
            $available_gateways['solidpg'] = new WC_Gateway_SolidPG();
        }

        return $available_gateways;
    }

    public function solidpg_payment_scripts(){
        wp_register_script(
            'solidpg-blocks-script',
            plugins_url('/public/js/solidpg-blocks.js', __FILE__),
            ['wp-element', 'wp-i18n', 'wc-blocks-registry'],
            '1.0',
            true
        );
    }

}

$solidpg_Init = new SolidPG_Init();
