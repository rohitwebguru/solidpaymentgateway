<?php

class SolidPG_Currencies
{

    public function __construct()
    {
        add_filter('woocommerce_currencies', array($this, 'solidpg_add_ugx_currencies'));
        add_filter('woocommerce_currency_symbol', array($this, 'solidpg_add_ugx_currencies_symbol'), 10, 2);

    }

    public function solidpg_add_ugx_currencies($currencies)
    {
        $currencies['UGX'] = __('Ugandan Shillings', 'solidpg-payment-woo');
        return $currencies;
    }

    public function solidpg_add_ugx_currencies_symbol($currency_symbol, $currency)
    {
        switch ($currency) {
            case 'UGX':
                $currency_symbol = 'UGX';
                break;
        }
        return $currency_symbol;
    }

}

$solidpg_currencies = new SolidPG_Currencies();
