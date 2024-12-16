<?php

class SolidPG_Payment_Gateway_Frontend
{

    public function __construct()
    {
        add_shortcode('place_order', array($this, 'place_order'));
        add_action('woocommerce_review_order_before_submit', array($this, 'add_custom_button'));
        add_action('wp_enqueue_scripts', array($this, 'solidpg_enqueue_frontend_style'));
    }

    public function solidpg_enqueue_frontend_style()
    {
        // Enqueue the style from your plugin's public/css folder
        wp_enqueue_style('solidpg-payment-style', plugins_url('/css/style.css', __FILE__));
        wp_register_script(
			'solidpg',
			plugin_dir_url(__FILE__) . '../public/js/solidpg-blocks.js',
			[],
			'1.0.0',
			true
		);
        wp_enqueue_script('solidpg');
    }

    public function display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status)
    {
?>

        <div class="order-details">
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

            <?php if ($shipping_address['first_name'] || $shipping_address['last_name'] || $shipping_address['address_1'] || $shipping_address['address_2'] || $shipping_address['city'] || $shipping_address['state'] || $shipping_address['postcode'] || $shipping_address['country']): ?>
                <h2>Shipping Address</h2>
                <address>
                    <?php echo $shipping_address['first_name'] . ' ' . $shipping_address['last_name']; ?><br>
                    <?php echo $shipping_address['address_1']; ?><br>
                    <?php echo $shipping_address['address_2']; ?><br>
                    <?php echo $shipping_address['city'] . ', ' . $shipping_address['state'] . ' ' . $shipping_address['postcode']; ?><br>
                    <?php echo $shipping_address['country']; ?><br>
                </address>
            <?php endif; ?>

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
    }

    //code to place order for wocommerce on thankyou page after successful payment
    public function place_order()
    {
        global $woocommerce;
        global $wpdb;
        $returned_data = $_REQUEST;
        $customer = WC()->session->get('customer');

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
                // $order->update_status('completed');
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

                $this->display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status);
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

                $this->display_order_details($order, $order_id, $order_data, $billing_address, $shipping_address, $payment_method, $order_total, $order_status);
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

        $solidpg_url = SOLIDPG_SANDBOX_URL;
        $solidpg_settings = get_option('woocommerce_solidpg_settings', array());
        // echo"<pre>"; print_r($solidpg_settings); exit;
        if ($solidpg_settings['sandbox_enabled'] == 'yes') {
            $solidpg_url = SOLIDPG_SANDBOX_URL;
        }

        // $merchant_email = $solidpg_settings['merchant_email'];
        $merchant_token = $solidpg_settings['merchant_token'];
        $merchant_entity_id = $solidpg_settings['merchant_entity_id'];

        $item_names_string = $this->get_all_cart_item_names();
        $return_url = get_permalink(get_option('solidpg_return_page'));

    ?>
        <div class="payment-title">
            <h1>Payment Information</h1>
        </div>

        <div class="form-container">
            <div class="field-container">
                <label for="name">Name</label>
                <input id="name" maxlength="20" type="text">
                <input id="paymentbrand" maxlength="20" hidden type="text" value="VISA">
            </div>
            <div class="field-container">
                <label for="cardnumber">Card Number</label>
                <input id="cardnumber" type="text" pattern="[0-9]*" inputmode="numeric">
                <svg id="ccicon" class="ccicon" width="750" height="471" viewBox="0 0 750 471" version="1.1" xmlns="http://www.w3.org/2000/svg"
                    xmlns:xlink="http://www.w3.org/1999/xlink">

                </svg>
            </div>
            <div class="field-container">
                <label for="expirationdate">Expiration (mm/yy)</label>
                <input id="expirationdate" type="text" pattern="[0-9]*" inputmode="numeric">
            </div>
            <div class="field-container">
                <label for="securitycode">Security Code</label>
                <input id="securitycode" type="text" pattern="[0-9]*" inputmode="numeric">
            </div>
            <button id="custom-place-order-btn" class="button alt" type="button">Pay with SolidPG</button>
        </div>


        <script src="https://cdnjs.cloudflare.com/ajax/libs/imask/3.4.0/imask.min.js"></script>
        <script>
            jQuery(document).ready(function($) {
                const $name = $('#name');
                const $cardnumber = $('#cardnumber');
                const $expirationdate = $('#expirationdate');
                const $securitycode = $('#securitycode');
                const $ccicon = $('#ccicon');


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
                $('#custom-place-order-btn').on('click', function() {
                    let isValid = true;

                    const cardNumber = $('#cardnumber').val().replace(/\s/g, ''); // Remove spaces
                    if (!/^\d{16}$/.test(cardNumber)) {
                        alert('Card number must be exactly 16 digits.');
                        isValid = false;
                    }

                    const expirationDate = $('#expirationdate').val();
                    if (!/^\d{2}\/\d{4}$/.test(expirationDate)) {
                        alert('Expiration date must be in MM/YYYY format.');
                        isValid = false;
                    }

                    const securityCode = $('#securitycode').val();
                    if (!/^\d{3}$/.test(securityCode)) {
                        alert('Security code (CVV) must be exactly 3 digits.');
                        isValid = false;
                    }

                    if (isValid) {
                        console.log('Payment details are valid! Proceeding...');
                        // Submit the form or trigger the payment process here
                    }
                });

                jQuery("input[name='payment_method']").on("change", function() {
                    if (jQuery("#payment_method_solidpg").is(":checked")) {
                        $('#place_order').hide();
                        $('.form-container').show();
                    } else {
                        $('#place_order').show();
                        $('.form-container').hide();
                    }
                })

                if (jQuery("#payment_method_solidpg").is(":checked")) {
                    $('#place_order').hide();
                    $('.form-container').show();

                } else {
                    $('#place_order').show();
                    $('.form-container').hide();
                }

                $('#custom-place-order-btn').on('click', function(e) {
                    const $nameval = $('#name').val();
                    const $cardnumberval = $('#cardnumber').val().replace(/\s/g, '');
                    const $expirationdateval = $('#expirationdate').val();
                    const $securitycodeval = $('#securitycode').val();
                    const $paymentbrand = $('#paymentbrand').val();

                    const token = "<?php echo esc_js($merchant_token); ?>"; // Token added

                    e.preventDefault();

                    $.ajax({
                        url:  '<?php echo esc_url(rest_url('solidpg/v1/payment')); ?>',
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
                            console.log("Payment Success:", response);
                            if(response.resultDetails.ExtendedDescription == 'Approved'){
                                alert('success');
                                window.location.href = `<?php echo esc_url(home_url('/')); ?>/solidpg-thankyou-page?order_id_solid=${response.id}`;
                            }
                        },
                        error: function(xhr) {
                            console.error("Payment Error:", xhr);
                            alert("Payment failed. Please try again.");
                        }
                    });
                });
            });
        </script>
<?php
    }
}

$frontend_solidpg = new SolidPG_Payment_Gateway_Frontend();
