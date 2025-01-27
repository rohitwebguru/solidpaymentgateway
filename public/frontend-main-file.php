<?php

class SolidPG_Payment_Gateway_Frontend
{

    public function __construct()
    {
        add_shortcode('place_order', array($this, 'place_order'));
        add_action('woocommerce_review_order_before_submit', array($this, 'add_custom_button'));
        // add_action('woocommerce_after_checkout_billing_form', array($this, 'add_custom_payment_fields'));
        // add_action('woocommerce_thankyou',  array($this, 'add_custom_thankyou_content'), 10, 1);
        add_action('woocommerce_checkout_process', array($this, 'validate_custom_payment_fields'));
        add_action('wp_enqueue_scripts', array($this, 'solidpg_enqueue_frontend_style'));
     }


    
    
    public function solidpg_enqueue_frontend_style()
    {
        // Enqueue the style from your plugin's public/css folder
        wp_enqueue_style(
            'solidpg-payment-style',
            plugin_dir_url(__FILE__) . 'css/style.css'
        );

        // Fetch the plugin settings
        $solidpg_settings = get_option('woocommerce_solidpg_settings', array());
        $merchant_token = isset($solidpg_settings['merchant_token']) ? $solidpg_settings['merchant_token'] : '';
        $merchant_entity_id = isset($solidpg_settings['merchant_entity_id']) ? $solidpg_settings['merchant_entity_id'] : '';
        $item_names_string = function_exists('get_all_cart_item_names') ? get_all_cart_item_names() : ''; // Ensure the function exists
        $return_url = get_permalink(get_option('solidpg_return_page'));
        $total_price = WC()->cart->total;
        $home_url = home_url('/');
        // Localize script data
        wp_register_script(
            'solidpg',
            plugin_dir_url(__FILE__) . '../public/js/solidpg-blocks.js',
            array('wp-element', 'wp-components', 'wc-settings', 'wp-data', 'jquery'), // Add dependencies here if needed
            '1.0.0',
            true
        );

        wp_localize_script('solidpg', 'solidpgData', array(
            'merchantToken' => $merchant_token,
            'merchantEntityId' => $merchant_entity_id,
            'itemNames' => $item_names_string,
            'returnUrl' => $return_url,
            'total_price' => $total_price,
            'home_url' => $home_url
        ));

        // Enqueue the script if the plugin is enabled
        $woocommerce_settings = get_option('woocommerce_solidpg_settings', array());

        if (isset($woocommerce_settings['enabled']) && $woocommerce_settings['enabled'] === 'yes') {
            wp_enqueue_script('solidpg');
        }
    }

    public function validate_custom_payment_fields()
    {
        // Check if your custom payment method is selected
        if (isset($_POST['custom_payment_method']) && $_POST['custom_payment_method'] === 'solidpg-payment-woo') {

            // Validate Name
            if (empty($_POST['custom_name'])) {
                wc_add_notice(__('<strong>Name</strong> field is required.', 'woocommerce'), 'error');
            }

            // Validate Card Number
            if (empty($_POST['custom_cardnumber']) || !preg_match('/^\d{16}$/', $_POST['custom_cardnumber'])) {
                wc_add_notice(__('<strong>Card Number</strong> must be exactly 16 digits.', 'woocommerce'), 'error');
            }

            // Validate Expiration Date
            if (empty($_POST['custom_expirationdate']) || !preg_match('/^\d{2}\/\d{4}$/', $_POST['custom_expirationdate'])) {
                wc_add_notice(__('<strong>Expiration Date</strong> must be in MM/YYYY format.', 'woocommerce'), 'error');
            }

            // Validate Security Code
            if (empty($_POST['custom_securitycode']) || !preg_match('/^\d{3}$/', $_POST['custom_securitycode'])) {
                wc_add_notice(__('<strong>Security Code</strong> (CVV) must be exactly 3 digits.', 'woocommerce'), 'error');
            }
        }
    }


    public function add_custom_thankyou_content($order_id)
    {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);


        if ($order->get_payment_method() === 'solidpg-payment-woo') {
            $solidpg_api_id = get_post_meta($order_id, 'solidpg_api_id', true);

            echo '<h2>' . __('Custom Payment Details', 'woocommerce') . '</h2>';
            echo '<p><strong>' . __('Transaction ID:', 'woocommerce') . '</strong> ' . esc_html($solidpg_api_id) . '</p>';
            echo '<p>' . __('Thank you for your payment using Solid Payment Gateway.', 'woocommerce') . '</p>';
        }
    }

    public function display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status)
    {
        ob_start();
?>
        <div class="order-details" id="solid-css-plugin">
            <h2>Order Details</h2>
            <table class="order-table">
                <tr>
                    <td><strong>Order ID:</strong></td>
                    <td><?php echo $order_id; ?></td>
                </tr>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($order_data['date_created'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><?php echo wc_get_order_status_name($order_status); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong></td>
                    <td><?php echo $payment_method; ?></td>
                </tr>
            </table>

            <h2>Billing Address</h2>
            <address>
                <?php echo $billing_address['first_name'] . ' ' . $billing_address['last_name']; ?><br>
                <?php echo $billing_address['address_1']; ?><br>
                <?php echo $billing_address['address_2']; ?><br>
                <?php echo $billing_address['city'] . ', ' . $billing_address['state'] . ' ' . $billing_address['postcode']; ?><br>
                <?php echo $billing_address['country']; ?><br>
            </address>

            <h2>Order Items</h2>
            <table class="order-items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item_id => $item): ?>
                        <?php
                        $product = $item->get_product();
                        $product_name = $product ? $product->get_name() : $item->get_name();
                        $product_price = wc_price($item->get_total());
                        $product_quantity = $item->get_quantity();
                        ?>
                        <tr>
                            <td><?php echo $product_name; ?></td>
                            <td><?php echo $product_quantity; ?></td>
                            <td><?php echo $product_price; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Total</h2>
            <p><?php echo wc_price($order_total); ?></p>
        </div>

        <?php
        // Return the buffered content
        return ob_get_clean();
    }


    //code to place order for wocommerce on thankyou page after successful payment
    public function place_order()
    {
        global $woocommerce;
        global $wpdb;
        $returned_data = $_REQUEST;
        $customer = WC()->session->get('customer');
        $order_note = isset($_GET['order_note']) ? $_GET['order_note'] : '';
        $order_id_solid = isset($_GET['order_id_solid']) ? $_GET['order_id_solid'] : '';

        if ($order_id_solid) {
            // Find the order IDs with the solidpg_api_id metadata value
            $existing_orders = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'solidpg_api_id' AND meta_value = %s",
                    $order_id_solid
                )
            );

            if (!empty($existing_orders)) {
                if (function_exists('WC')) {
                    $order_id = $existing_orders[0];
                    $order = wc_get_order($order_id);
                    if (!$order) {
                        echo 'Invalid Order ID.';
                        return;
                    }
                    $order_data = $order->get_data();
                    $billing_address = $order->get_address('billing');
                    $shipping_address = $order->get_address('shipping');
                    $payment_method = $order->get_payment_method();
                    $order_total = $order->get_total();
                    $order_status = $order->get_status();
                    if (!empty($order_note)) {
                        $order->add_order_note($order_note);
                    }
                    $thank_you_url = wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order->get_order_key();
        ?>
                    <script type="text/javascript">
                        window.location.href = "<?php echo esc_url($thank_you_url); ?>";
                    </script>
                <?php
                    return;
                    // return $this->display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status);
                } else {
                    $order_id = $existing_order_ids[0]['post_id'];
                    $order = wc_get_order($order_id);
                    if (!$order) {
                        echo 'Invalid Order ID.';
                        return;
                    }
                    $order_data = $order->get_data();
                    $billing_address = $order->get_address('billing');
                    $shipping_address = $order->get_address('shipping');
                    $payment_method = $order->get_payment_method();
                    $order_total = $order->get_total();
                    $order_status = $order->get_status();
                    if (!empty($order_note)) {
                        $order->add_order_note($order_note);
                    }
                    $thank_you_url = wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order->get_order_key();
                ?>
                    <script type="text/javascript">
                        window.location.href = "<?php echo esc_url($thank_you_url); ?>";
                    </script>
                    <?php
                    return;
                    // return $this->display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status);
                }
            } else {
                if (function_exists('WC')) {

                    $table_name = $wpdb->prefix . 'postmeta';

                    $query = $wpdb->prepare("
                            SELECT post_id
                            FROM $table_name
                            WHERE meta_key IN ('trans_id', 'solidpg_order_id')
                            AND meta_value = %s
                            ", $_REQUEST['trans_id']);

                    $existing_order_ids = $wpdb->get_results($query, ARRAY_A);

                    if (count($existing_order_ids) == 0) {

                        //code to place order
                        $cart = WC()->cart;
                        $order = wc_create_order();
                        $order->set_customer_id(get_current_user_id());
                        $billing_address = array(
                            'first_name' => $customer['first_name'],
                            'last_name' => $customer['last_name'],
                            'email' => $customer['email'],
                            'phone' => $customer['phone'],
                            'address_1' => $customer['address_1'],
                            'address_2' => $customer['address_2'],
                            'city' => $customer['city'],
                            'state' => $customer['state'],
                            'postcode' => $customer['postcode'],
                            'country' => $customer['country'],

                        );

                        $order->set_address($billing_address, 'billing');
                        $order->set_address($billing_address, 'shipping');
                        error_log("order details: " . $order);

                        $order->set_payment_method('solidpg-payment-woo');

                        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                            $product_id = $cart_item['product_id'];
                            $quantity = $cart_item['quantity'];
                            $variation_id = $cart_item['variation_id'];
                            $product = wc_get_product($product_id);

                            if ($product) {
                                $order->add_product($product, $quantity);
                            }
                        }

                        $order_total = $cart->total;
                        $currency_code = get_woocommerce_currency();
                        $order->set_total($order_total);
                        $order->save();
                        $order->update_status('completed'); // Set the order status to processing
                        $cart->empty_cart();

                        // save transaction array in postmeta
                        update_post_meta($order->get_id(), 'transaction_details', $_REQUEST);
                        update_post_meta($order->get_id(), 'trans_id', $_REQUEST['trans_id']);
                        update_post_meta($order->get_id(), 'solidpg_order_id', $order->get_id());
                        update_post_meta($order->get_id(), 'solidpg_api_id', $_GET['order_id_solid']);

                        // code for displaying order details
                        $order_id = $order->get_id();
                        $order = wc_get_order($order_id);
                        if (!$order) {
                            echo 'Invalid Order ID.';
                            return;
                        }
                        $order_data = $order->get_data();
                        $billing_address = $order->get_address('billing');
                        $shipping_address = $order->get_address('shipping');
                        $payment_method = $order->get_payment_method();
                        $order_total = $order->get_total();
                        $order_status = $order->get_status();
                        if (!empty($order_note)) {
                            $order->add_order_note($order_note);
                        }
                        $thank_you_url = wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order->get_order_key();
                    ?>
                        <script type="text/javascript">
                            window.location.href = "<?php echo esc_url($thank_you_url); ?>";
                        </script>
                    <?php
                        return;
                        // return $this->display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status);
                    } else {
                        $order_id = $existing_order_ids[0]['post_id'];
                        $order = wc_get_order($order_id);
                        if (!$order) {
                            echo 'Invalid Order ID.';
                            return;
                        }
                        $order_data = $order->get_data();
                        $billing_address = $order->get_address('billing');
                        $shipping_address = $order->get_address('shipping');
                        $payment_method = $order->get_payment_method();
                        $order_total = $order->get_total();
                        $order_status = $order->get_status();
                        if (!empty($order_note)) {
                            $order->add_order_note($order_note);
                        }
                        $thank_you_url = wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order->get_order_key();
                    ?>
                        <script type="text/javascript">
                            window.location.href = "<?php echo esc_url($thank_you_url); ?>";
                        </script>
        <?php
                        return;
                        // return $this->display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status);
                    }
                }
            }
        }
    }

    public function get_all_cart_item_names()
    {
        $cart = WC()->cart;
        $cart_items = $cart->get_cart();
        $item_names = array();
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_name = $cart_item['data']->get_name();
            $item_names[] = $product_name;
        }
        $comma_separated_names = implode(', ', $item_names);
        return $comma_separated_names;
    }



    // code for payment button and its implementation
    public function add_custom_button()
    {
        global $woocommerce;
        $cart = $woocommerce->cart;
        $total_quantity = $cart->get_cart_contents_count();
        $total_price = WC()->cart->total;


        $solidpg_settings = get_option('woocommerce_solidpg_settings', array());
        // echo"<pre>"; print_r($solidpg_settings); exit;
        if ($solidpg_settings['sandbox_enabled'] == 'yes') {
            $solidpg_url = SOLIDPG_SANDBOX_URL;
        } else {
            $solidpg_url = SOLIDPG_LIVE_URL;
        }

        // $merchant_email = $solidpg_settings['merchant_email'];
        $merchant_token = $solidpg_settings['merchant_token'];
        $merchant_entity_id = $solidpg_settings['merchant_entity_id'];

        $item_names_string = $this->get_all_cart_item_names();
        $return_url = get_permalink(get_option('solidpg_return_page'));

        ?>
        <!-- <div class="payment-title">
            <h1>Payment Information</h1>
        </div> -->
        <style>
            .form-container {
                display: grid;
                grid-column-gap: 10px;
                /* grid-template-columns: auto auto; */
                grid-template-rows: 90px 90px 90px 90px;
                color: #707070;

                input,
                textarea {
                    margin-top: 3px;
                    padding: 12px 0;
                    font-size: 16px;
                    width: 100%;
                    border-radius: 3px;
                    border: 1px solid #dcdcdc;
                    border-radius: 10px;
                }
            }

            label {
                padding-bottom: 10px;
                font-size: 13px;
                margin-bottom: 0px !important;
                line-height: 0 !important;
                border-radius: 10px;
            }

            .loader {
                width: 30px;
                height: 20px;
                border: 5px solid #FFF;
                border-bottom-color: #FF3D00;
                border-radius: 50%;
                display: inline-block;
                box-sizing: border-box;
                animation: rotation 1s linear infinite;
            }

            @keyframes rotation {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
        <div class="form-container">
            <div class="field-container">
                <label for="name">Name</label>
                <input id="name" name="custom_name" type="text">
                <input id="paymentbrand" maxlength="20" hidden type="text" value="VISA">
                <input type="hidden" name="custom_payment_method" id="payment_method">
            </div>

            <div class="field-container">
                <label for="cardnumber">Card Number</label>
                <input id="cardnumber" name="custom_cardnumber" maxlength="19" minlength="19" type="text" pattern="[0-9]*" inputmode="numeric">
            </div>

            <div class="field-container">
                <label for="expirationdate">Expiration (MM/YYYY)</label>
                <input id="expirationdate" name="custom_expirationdate" type="text" pattern="[0-9]*" inputmode="numeric">
            </div>

            <div class="field-container">
                <label for="securitycode">Security Code</label>
                <input id="securitycode" name="custom_securitycode" type="text" pattern="[0-9]*" inputmode="numeric">
            </div>

            <div class="field-container " style="margin-bottom: 10px;">
                <label for="note">Note (Optional)</label>
                <!-- <input id="note" type="text" name="note"> -->
                <textarea id="note"></textarea>
            </div>
            <div id="error-container" style="color:red ;font-size: 16px; font-weight:600; padding: 10px 0; line-height: 1.6;" class="text-danger mt-3" style="display: none;"></div>
            <!-- <button id="custom-place-order-btn" style="padding: 12px 0;
                    margin-top: 17px;
                    background-color: #7a7ff4;
                    border: none;
                    color: white;
                    border-radius: 10px;
                    font-size: 18px;" class="button btn-primary alt" type="button">Place Order</button> -->
            <!-- <button id="custom-place-order-btn" type="button" class="btn btn-primary btn-lg" style="padding: 12px 0;
                    margin-top: 17px;
                    background-color: #7a7ff4;
                    border: none;
                    color: white;
                    border-radius: 10px;
                    font-size: 18px;">
                <div id="spinner" class="spinner" role="status" style="display: none;">
                    <span class="loader"></span>
                </div>
                <span id="place-order">Place Order</span>
            </button> -->
        </div>

        <script>
            jQuery(document).ready(function($) {
                const $name = $('#name');
                const $note = $('#note');
                const $cardnumber = $('#cardnumber');
                const $expirationdate = $('#expirationdate');
                const $securitycode = $('#securitycode');
                const $termsCheckbox = $('#terms');
                const $refundPolicyCheckbox = $('#refund-policy-checkbox');
                const $errorContainer = $('#error-container');
                const $placeOrderBtn = $('#custom-place-order-btn');
                const $buttonText = $('#place-order');
                const $spinner = $('#spinner');

                $cardnumber.on('input', function() {
                    const value = $(this).val().replace(/\D/g, '');
                    if (value.length > 16) {
                        $(this).val(value.slice(0, 15));
                    } else {
                        $(this).val(value.replace(/(\d{4})(?=\d)/g, '$1 '));
                    }
                });

                $('#expirationdate').on('input', function() {
                    const value = $(this).val().replace(/\D/g, '');
                    if (value.length > 6) {
                        $(this).val(value.slice(0, 6));
                    } else {
                        const formatted = value
                            .replace(/^(\d{2})(\d{1,4})?$/, '$1/$2')
                            .replace(/\/$/, '');
                        $(this).val(formatted);
                    }
                });


                $securitycode.on('input', function() {
                    const value = $(this).val().replace(/\D/g, '');
                    $(this).val(value.slice(0, 3));
                });

                // Validation on form submission


                jQuery("input[name='payment_method']").on("change", function() {
                    if (jQuery("#payment_method_solidpg").is(":checked")) {
                        // $('#place_order').hide();
                        $('.form-container').show();
                        $('#payment_method').val('solidpg-payment-woo');
                        $('#place_order').on('click', function(e) {
                            $errorContainer.hide().text(''); // Clear any previous errors

                            let isValid = true;
                            let errors = [];

                            const name = $name.val().trim();
                            if (!name) {

                                isValid = false;
                            }

                            const cardNumber = $cardnumber.val().replace(/\s/g, '');
                            if (!/^\d{16}$/.test(cardNumber)) {

                                isValid = false;
                            }

                            const expirationDate = $expirationdate.val();
                            if (!/^\d{2}\/\d{4}$/.test(expirationDate)) {

                                isValid = false;
                            }

                            const securityCode = $securitycode.val();
                            if (!/^\d{3}$/.test(securityCode)) {

                                isValid = false;
                            }

                            // if ($refundPolicyCheckbox.prop("checked") == false) {
                            //     errors.push('Please read and agree to the Refund Policy.');
                            //     isValid = false;
                            // }

                            // if ($termsCheckbox.prop("checked") == false) {
                            //     errors.push('Please read and agree to the Terms and Conditions.');
                            //     isValid = false;
                            // }

                            if (!isValid) {
                                $errorContainer.html(errors.join('<br>')).fadeIn();
                                return;
                            }

                            // Show loader
                            $spinner.show();
                            $buttonText.hide();
                            $placeOrderBtn.prop('disabled', true);

                            const $nameval = $('#name').val();
                            const $noteVal = $('#note').val();
                            const $cardnumberval = $('#cardnumber').val().replace(/\s/g, '');
                            const $expirationdateval = $('#expirationdate').val();
                            const $securitycodeval = $('#securitycode').val();
                            const $paymentbrand = $('#paymentbrand').val();

                            const token = "<?php echo esc_js($merchant_token); ?>"; // Token added

                            e.preventDefault();
                            $('form.checkout').addClass('processing');
                            $.ajax({
                                url: '<?php echo esc_url(rest_url('solidpg/v1/payment')); ?>',
                                type: "POST",
                                headers: {
                                    "Authorization": `Bearer <?php echo esc_attr($merchant_token); ?>`,
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                data: {
                                    "entityId": "<?php echo esc_attr($merchant_entity_id); ?>",
                                    "amount": "<?php echo $total_price; ?>",
                                    "currency": "EUR",
                                    "paymentBrand": $paymentbrand,
                                    "paymentType": "DB",
                                    "card_number": $cardnumberval,
                                    "card_holder": $nameval,
                                    "card_expiryMonth": $expirationdateval.split('/')[0],
                                    "card_expiryYear": $expirationdateval.split('/')[1],
                                    "card_cvv": $securitycodeval,
                                    "shopperResultUrl": "<?php echo $return_url; ?>"
                                },
                                success: function(response) {
                                    if (response.resultDetails?.ExtendedDescription == 'Approved') {
                                        $('form.checkout').removeClass('processing');
                                        // const orderId = `response.id`;
                                        // const order_id = `<?php echo get_post_meta($order_id, 'solidpg_api_id', true); ?>`;
                                        // const thankYouUrl = `<?php echo esc_url(wc_get_endpoint_url('order-received', '', wc_get_checkout_url())); ?>?key=${orderId}&&order_note=${$noteVal}`;
                                        // window.location.href = thankYouUrl;
                                        window.location.href = `<?php echo esc_url(home_url('/')); ?>/solidpg-thankyou-page?order_id_solid=${response.id}&&order_note=${$noteVal}`;
                                    } else {
                                        $('form.checkout').removeClass('processing');
                                        $errorContainer.text(response.result?.description).fadeIn();

                                        $spinner.hide();
                                        $buttonText.show();
                                        $placeOrderBtn.prop('disabled', false);
                                    }
                                },
                                error: function(xhr) {
                                    console.error("Payment Error:", xhr);
                                    $spinner.hide();
                                    $buttonText.show();
                                    $placeOrderBtn.prop('disabled', false);
                                }
                            });
                        });
                    } else {
                        // $('#place_order').show();
                        $('.form-container').hide();
                        $('#payment_method').val('another');
                    }
                })

                if (jQuery("#payment_method_solidpg").is(":checked")) {
                    // $('#place_order').hide();
                    $('.form-container').show();
                    $('#payment_method').val('solidpg-payment-woo');
                    $('#place_order').on('click', function(e) {
                        $errorContainer.hide().text(''); // Clear any previous errors

                        let isValid = true;
                        let errors = [];

                        const name = $name.val().trim();
                        if (!name) {

                            isValid = false;
                        }

                        const cardNumber = $cardnumber.val().replace(/\s/g, '');
                        if (!/^\d{16}$/.test(cardNumber)) {

                            isValid = false;
                        }

                        const expirationDate = $expirationdate.val();
                        if (!/^\d{2}\/\d{4}$/.test(expirationDate)) {

                            isValid = false;
                        }

                        const securityCode = $securitycode.val();
                        if (!/^\d{3}$/.test(securityCode)) {

                            isValid = false;
                        }

                        // if ($refundPolicyCheckbox.prop("checked") == false) {
                        //     errors.push('Please read and agree to the Refund Policy.');
                        //     isValid = false;
                        // }

                        // if ($termsCheckbox.prop("checked") == false) {
                        //     errors.push('Please read and agree to the Terms and Conditions.');
                        //     isValid = false;
                        // }

                        if (!isValid) {
                            $errorContainer.html(errors.join('<br>')).fadeIn();
                            return;
                        }

                        // Show loader
                        $spinner.show();
                        $buttonText.hide();
                        $placeOrderBtn.prop('disabled', true);

                        const $nameval = $('#name').val();
                        const $noteVal = $('#note').val();
                        const $cardnumberval = $('#cardnumber').val().replace(/\s/g, '');
                        const $expirationdateval = $('#expirationdate').val();
                        const $securitycodeval = $('#securitycode').val();
                        const $paymentbrand = $('#paymentbrand').val();

                        const token = "<?php echo esc_js($merchant_token); ?>"; // Token added

                        e.preventDefault();
                        const checkoutForm = $('form.checkout');
                        checkoutForm.addClass('processing');
                        checkoutForm.block({
                            message: null,
                            overlayCSS: {
                                background: '#fff',
                                opacity: 0.6
                            }
                        });
                        $.ajax({
                            url: '<?php echo esc_url(rest_url('solidpg/v1/payment')); ?>',
                            type: "POST",
                            headers: {
                                "Authorization": `Bearer <?php echo esc_attr($merchant_token); ?>`,
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            data: {
                                "entityId": "<?php echo esc_attr($merchant_entity_id); ?>",
                                "amount": "<?php echo $total_price; ?>",
                                "currency": "EUR",
                                "paymentBrand": $paymentbrand,
                                "paymentType": "DB",
                                "card_number": $cardnumberval,
                                "card_holder": $nameval,
                                "card_expiryMonth": $expirationdateval.split('/')[0],
                                "card_expiryYear": $expirationdateval.split('/')[1],
                                "card_cvv": $securitycodeval,
                                "shopperResultUrl": "<?php echo $return_url; ?>"
                            },
                            success: function(response) {
                                if (response.resultDetails?.ExtendedDescription == 'Approved') {
                                    checkoutForm.removeClass('processing');
                                    checkoutForm.unblock();
                                    // const orderId = `response.id`;
                                    // const order_id = `<?php echo get_post_meta($order_id, 'solidpg_api_id', true); ?>`;
                                    // const thankYouUrl = `<?php echo esc_url(wc_get_endpoint_url('order-received', '', wc_get_checkout_url())); ?>?key=${orderId}&&order_note=${$noteVal}`;
                                    // window.location.href = thankYouUrl;
                                    window.location.href = `<?php echo esc_url(home_url('/')); ?>/solidpg-thankyou-page?order_id_solid=${response.id}&&order_note=${$noteVal}`;
                                } else {
                                    checkoutForm.removeClass('processing');
                                    checkoutForm.unblock();
                                    $errorContainer.text(response.result?.description).fadeIn();

                                    $spinner.hide();
                                    $buttonText.show();
                                    $placeOrderBtn.prop('disabled', false);
                                }
                            },
                            error: function(xhr) {
                                console.error("Payment Error:", xhr);
                                $spinner.hide();
                                $buttonText.show();
                                $placeOrderBtn.prop('disabled', false);
                            }
                        });
                    });

                } else {
                    // $('#place_order').show();
                    $('.form-container').hide();
                    $('#payment_method').val('another');
                }

                // $('#custom-place-order-btn').on('click', function(e) {

                // });
            });
        </script>
<?php
    }
}

$frontend_solidpg = new SolidPG_Payment_Gateway_Frontend();
