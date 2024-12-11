<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class SolidPG_Blocks_Integration extends AbstractPaymentMethodType {
    public function get_name() {
        error_log('solidpg_name');
        return 'solidpg'; // Gateway ID
    }

    public function get_payment_method_script_handles() {
        // Register the JS script for the payment gateway
        wp_register_script(
            'solidpg-blocks-script',
            plugin_dir_url(__FILE__) . '../public/js/solidpg-blocks.js',
            ['wp-element', 'wc-blocks-registry'],
            '1.0',
            true
        );
    
        // Register the CSS file
        wp_register_style(
            'solidpg-blocks-style',
            plugin_dir_url(__FILE__) . '../public/css/style.css',
            [],
            '1.0'
        );
    
        // Ensure the frontend-main-file.php is included and the button HTML is passed to JS
        include_once plugin_dir_path(__FILE__) . '../public/frontend-main-file.php';
    
        // Get the button HTML from the add_custom_button function
        $button_html = $this->add_custom_button();
    
        // Localize the script to pass the button HTML to JS
        wp_localize_script(
            'solidpg-blocks-script', // The handle of the script
            'SolidPGButtonData',     // The JavaScript object that will contain the localized data
            [
                'button_html' => $button_html, // Add the button HTML here
            ]
        );
        wp_enqueue_script('solidpg-blocks-script');
        return ['solidpg-blocks-script'];
    }
    
    public function add_custom_button() {
        // This is the PHP function that generates the button HTML
        return '<button id="solidpg-custom-button" class="solidpg-button">Custom SolidPG Button</button>';
    }
    

    public function is_active() {
        error_log('SolidPG is_active called');
        return true;
    }

    public function get_supported_features() {
        return ['refunds']; // Add features your gateway supports
    }

    /**
     * Initialize the integration (required by IntegrationInterface).
     *
     * @return void
     */
    public function initialize() {
        error_log('Initializing SolidPG Gateway');

        add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
            $registry->register($this);
        });

        // Enqueue frontend assets
        // add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }
  
    /**
     * Enqueue Frontend Scripts for the Gateway.
     *
     * @return void
     */
    // public function enqueue_frontend_scripts() {
    //     // Include the frontend-main-file.php for rendering custom gateway frontend
    //     include_once plugin_dir_path(__FILE__) . '../public/frontend-main-file.php';
    
    //     // Optionally enqueue CSS or JavaScript for your gateway
    //     wp_enqueue_script(
    //         'solidpg-blocks-script',
    //         plugin_dir_url(__FILE__) . '../public/js/solidpg-blocks.js',
    //         ['jquery'],
    //         '1.0',
    //         true
    //     );
    
    //     wp_enqueue_style(
    //         'solidpg-blocks-style',
    //         plugin_dir_url(__FILE__) . '../public/css/style.css',
    //         [],
    //         '1.0'
    //     );
    // }    
}