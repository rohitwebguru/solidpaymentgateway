<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class SolidPG_Blocks_Integration extends AbstractPaymentMethodType {
    public function get_name() {
        return 'solidpg'; // Gateway ID
    }

    public function get_payment_method_script_handles() {
        return ['solidpg-blocks-script'];
    }

    public function is_active() {
        // Add logic to determine if the gateway should be active
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
        // Perform any necessary initialization here.
        // For example, enqueue scripts or register actions/filters.
    }
}