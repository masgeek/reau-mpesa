<?php /** @noinspection PhpComposerExtensionStubsInspection */


/** @noinspection SqlResolve */
/** @noinspection DuplicatedCode */

/*
Plugin Name: Tsobu Mpesa gateway
Plugin URI: https://tsobu.co.ke/mpesa
Description: M-PESA Payment plugin for woocommerce
Version: 1.0.0
Author: Tsobu Enterprise <dev@tsobu.co.ke>
Author URI: https://tsobu.co.ke
License: GPL2

* WC requires at least: 2.2
* WC tested up to: 4.2.0
*/

defined('ABSPATH') or die('No script kiddies please!');

//define('ACFSURL', WP_PLUGIN_URL . "/" . dirname(plugin_basename(__FILE__)));
//define('MPESA_DIR', plugin_dir_path(__FILE__));
//define('MPESA_INC_DIR', MPESA_DIR . 'includes/');


if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit('Please install WooCommerce for this extension to work');
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'reu_add_gateway_class');
function reu_add_gateway_class($gateways)
{
    $gateways[] = 'WcMpesaGateway';
    return $gateways;
}

add_action('init', function () {
    /** Add a custom path and set a custom query argument. */
    add_rewrite_rule('^/payment/?([^/]*)/?', 'index.php?payment_action=1', 'top');
});

add_filter('query_vars', function ($query_vars) {
    /* add additional parameters to request string to help wordpress call the action */
    $query_vars [] = 'payment_action';
    return $query_vars;
});

add_action('wp', function () {
    if (get_query_var('payment_action')) {
        reu_request_payment();
    }
});

//Register ajax handlers

add_action('wp_ajax_nopriv_process_mpesa', 'reu_request_payment');
add_action('wp_ajax_process_mpesa', 'reu_request_payment');

//Calls the create_mpesa_transactions_table function during plugin activation which creates table that records mpesa transactions.
register_activation_hook(__FILE__, 'create_mpesa_transactions_table');

add_action('wp_enqueue_scripts', "reu_enqueue_scripts_func");
function reu_enqueue_scripts_func()
{
    wp_register_style('cartStyle',
        plugins_url('/css/cart.css', __FILE__),
        array(),
        '1.0.0',
        'all'
    );

    wp_register_script(
        'ajaxHandle',
        plugins_url('/js/ajax-mpesa.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_register_script(
        'cartHandle',
        plugins_url('/js/cart.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_enqueue_script('jqueryMask');
    wp_enqueue_script('ajaxHandle');
    wp_enqueue_script('cartHandle');
    wp_enqueue_style('bootstrap');
    wp_enqueue_style('fontawesome');
    //wp_enqueue_style('googlefont');
    wp_enqueue_style('cartStyle');

    wp_localize_script('ajaxHandle', 'ajaxMpesaCheckout', array('ajaxurl' => admin_url('admin-ajax.php')));
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'reu_init_gateway_class');
function reu_init_gateway_class()
{
    class WcMpesaGateway extends WC_Payment_Gateway
    {
        private $mpesa_codes = [];
        public $merchant_name;
        public $shortcode;
        public $store_no;
        public $consumer_key;
        public $consumer_secret;
        public $transaction_type;
        public $passkey;
        public $api_url;
        public $credentials_endpoint;
        public $payments_endpoint;
        /* callbacks */
        public $mpesa_callback_url;
        public $mpesa_timeout_url;
        public $mpesa_result_url;
        public $mpesa_confirmation_url;
        public $mpesa_validation_url;


        /**
         * @var bool
         */
        public function __construct()
        {

            $this->id = 'tsobu_mpesa';
            $this->icon = plugin_dir_url(__FILE__) . 'mpesa.png';
            $this->has_fields = false;
            $this->method_title = 'M-Pesa Payment';
            $this->method_description = __('Enable customers to make payments via mpesa');

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
//            $this->supports = array(
//                'products'
//            );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');

            $this->testmode = $this->get_option('testmode');
            $this->transaction_type = $this->get_option('transaction_type');
            $this->merchant_name = $this->get_option('merchant_name');
            $this->shortcode = $this->get_option('shortcode');
            $this->store_no = $this->get_option('store_no');
            $this->consumer_key = $this->get_option('consumer_key');
            $this->consumer_secret = $this->get_option('consumer_secret');
            $this->passkey = $this->get_option('passkey');
            $this->api_url = $this->get_option('api_url');
            $this->credentials_endpoint = $this->get_option('credentials_endpoint');
            $this->payments_endpoint = $this->get_option('payments_endpoint');

            //https://wordpress.test/?wc-api=callback if you haven't enabled url prettifying
            $baseUrl = rtrim(home_url(), '/');
            $this->mpesa_callback_url = "{$baseUrl}/wc-api/callback";
            $this->mpesa_timeout_url = "{$baseUrl}/wc-api/timeout";
            $this->mpesa_result_url = "{$baseUrl}/wc-api/reconcile";
            $this->mpesa_confirmation_url = "{$baseUrl}/wc-api/confirm";
            $this->mpesa_validation_url = "{$baseUrl}/wc-api/validate";

            //$this->mpesa_callback_url = 'https://webhook.site/ae877091-9700-40da-8016-b02114ab3d01';

            $this->mpesa_codes = [
                0 => 'Success',
                1 => 'Insufficient Funds',
                2 => 'Less Than Minimum Transaction Value',
                3 => 'More Than Maximum Transaction Value',
                4 => 'Would Exceed Daily Transfer Limit',
                5 => 'Would Exceed Minimum Balance',
                6 => 'Unresolved Primary Party',
                7 => 'Unresolved Receiver Party',
                8 => 'Would Exceed Maximum Balance',
                11 => 'Debit Account Invalid',
                12 => 'Credit Account Invalid',
                13 => 'Unresolved Debit Account',
                14 => 'Unresolved Credit Account',
                15 => 'Duplicate Detected',
                17 => 'Internal Failure',
                20 => 'Unresolved Initiator',
                26 => 'Traffic blocking condition in place'
            ];
            //This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('woocommerce_api_callback', array($this, 'mpesa_callback'));
            add_action('woocommerce_api_timeout', array($this, 'mpesa_callback'));
            add_action('woocommerce_api_reconcile', array($this, 'mpesa_callback'));
            add_action('woocommerce_api_confirm', array($this, 'mpesa_confirm'));
            add_action('woocommerce_api_validate', array($this, 'mpesa_validate'));

            add_action('woocommerce_receipt_tsobu_mpesa', array($this, 'receipt_page'));
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => 'Enable/Disable',
                    'label' => 'Enable M-Pesa Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ],
                'title' => [
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Reu Att Mpesa checkout',
                    'desc_tip' => false,
                ],
                'description' => [
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay using M-Pesa',
                ],
                'merchant_name' => [
                    'title' => 'Merchant Name',
                    'description' => 'Merchant name',
                    'default' => 'LITTLE REUBY STUDIOS',
                    'type' => 'text',
                ],
                'transaction_type' => [
                    'title' => 'Transaction type',
                    'options' => [
                        'CustomerPayBillOnline' => 'CustomerPayBillOnline',
                        'CustomerBuyGoodsOnline' => 'CustomerBuyGoodsOnline'
                    ],
                    'required' => true,
                    'type' => 'select',
                ],
                'store_no' => [
                    'title' => 'Head Office Number',
                    'description' => 'HO/Store Number (for Till) or Paybill Number. Use "Online Shortcode" in Sandbox',
                    'default' => 174379,
                    'type' => 'number',
                    'desc_tip' => false
                ],
                'shortcode' => [
                    'title' => 'Short Code',
                    'description' => 'Your MPesa Business Till/Paybill Number. Use "Online Shortcode" in Sandbox',
                    'default' => 174379,
                    'type' => 'number',
                    'desc_tip' => false
                ],
                'consumer_key' => [
                    'title' => 'Consumer key',
                    'description' => 'Consumer key',
                    'default' => '9YgLOYoIPIlGk1dBZAz9QhxlcXJ0lvis',
                    'type' => 'password',
                ],
                'consumer_secret' => [
                    'title' => 'Consumer secret',
                    'description' => 'Consumer secret',
                    'default' => 'YnWiMgbNiyE1Or1Y',
                    'type' => 'password',
                ],
                'passkey' => [
                    'title' => 'API pass key',
                    'description' => 'Pass key for authentication',
                    'default' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
                    'type' => 'textarea',
                ],
                'api_url' => [
                    'title' => 'API Endpoint',
                    'options' => [
                        'https://sandbox.safaricom.co.ke/' => 'Testing URL (https://sandbox.safaricom.co.ke)',
                        'https://api.safaricom.co.ke' => 'Production URL (https://api.safaricom.co.ke)'
                    ],
                    'required' => true,
                    'type' => 'select',
                ],
                'credentials_endpoint' => [
                    'readonly' => true,
                    'title' => __('Credentials Endpoint', 'woocommerce'),
                    'default' => 'oauth/v1/generate?grant_type=client_credentials',
                    'description' => 'Default is: oauth/v1/generate?grant_type=client_credentials',
                    'type' => 'text',
                    'desc_tip' => true
                ],
                'payments_endpoint' => [
                    'title' => 'Payments Endpoint',
                    'default' => '/mpesa/stkpush/v1/processrequest',
                    'description' => 'Default is: mpesa/stkpush/v1/processrequest',
                    'required' => true,
                    'type' => 'text',
                    'desc_tip' => true
                ],
                'enable_c2b' => array(
                    'title' => 'Manual Payments',
                    'label' => 'Enable C2B API(Offline Payments)',
                    'type' => 'checkbox',
                    'description' => '<small>' . (($this->get_option('idtype') == 4) ? 'This requires C2B Validation, which is an optional feature that needs to be activated on M-Pesa. <br>Request for activation by sending an email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on the <a href="https://developer.safaricom.co.ke/">developer portal.</a><br>' : '') . '<a class="button button-secondary" href="' . home_url('lipwa/register/') . '">Once enabled, click here to register confirmation & validation URLs</a><p>Kindly note that if this is disabled, the user can still resend an STK push if the first one fails.</p></small>',
                    'default' => 'no',
                ),
            ];
        }


        public function validate_fields()
        {
            //validate  phone
            $mpesa_number = filter_input(INPUT_POST, 'billing_phone', FILTER_VALIDATE_INT);

            if (!isset($mpesa_number)) {
                wc_add_notice(__('Phone number is required!', 'wc-mpesa-payment-gateway'), 'error');
                return false;
            }
            return true;
        }

        /**
         * Receipt Page
         **/

        public function receipt_page($order_id)
        {
            echo $this->woompesa_generate_iframe($order_id);
        }

        public function woompesa_generate_iframe($order_id)
        {
            global $woocommerce;
            $order = new WC_Order ($order_id);

            $amnt = $order->get_total();

            $tel = $order->get_billing_phone();


            $manualInstructions = "<div class='manual-instructions hidden'>";
            $manualInstructions .= <<<MANUAL
<ol>
<li>Go to the M-PESA menu on your phone</li>
<li>Choose Lipa na M-PESA</li>
<li>Select Buy Goods and Services Option : Enter Till Number: <strong>$this->shortcode</strong></li>
<li>Enter the EXACT amount (KSh. <strong>$amnt</strong> )</li>
<li>Enter your PIN and then send the money</li>
<li>Complete your transaction on your phone</li>
<li>You will receive a transaction confirmation SMS from MPESA</li>
<li>Enter the M-PESA Confirmation reference</li>
<li>Then click on the <strong>Confirm Payment</strong> button below</li>
</ol>
MANUAL;
            $manualInstructions .= "</div>";

            /**
             * Make the payment here by clicking on pay button and confirm by clicking on complete order button
             */

            if ($_GET['transactionType'] == 'checkout') {

                $redirect = $_GET['rdr'];
                $instructions = "<h4>Payment Instructions:</h4>";

                $instructions .= "<div class='stk-instructions'>";
                $instructions .= <<<INSTR
<ol>
        <li>Click on the <strong>Pay</strong> button in order to initiate the M-PESA payment.</li>
        <li>Check your mobile phone for a prompt asking to enter M-PESA pin.</li>
        <li>Enter your <strong>M-PESA PIN</strong> and the amount specified <strong>$amnt</strong> on the 
        notification will be deducted from your M-PESA account when you press send.</li>
        <li>When you enter the pin and click on send, you will receive an M-PESA payment confirmation message on your mobile phone.</li>    	
        <li>After receiving the M-PESA payment confirmation message please click on the <strong>Confirm Payment</strong> button below to complete the order and confirm the payment made.</li>
</ol>
INSTR;
                $instructions .= "</div>";
                $help = "<h5 class='no-prompt'>I did not get a prompt on my phone. Take me to the previous MPESA payment method</h5>";
                $help .= "<h5 class='stk-prompt hidden'>Use LIPA Online</h5>";

                ?>

                <div id="commonname"></div>
                <?= $instructions ?>
                <?= $manualInstructions ?>
                <?= $help ?>
                <input type="hidden" value="<?= $redirect ?>" id="redirect_url"/>
                <input type="hidden" value="<?= $order_id ?>" id="order_id"/>
                <input type="hidden" value="stk" id="payment_mode" readonly/>
                <input type="hidden" value="<?= $amnt ?>" id="amount" readonly/>

                <div class="manual-instructions hidden">
                    <label for="phone_number">Payment number</label>
                    <br/>
                    <input type="text" value="<?= $tel ?>" id="phone_number" readonly
                           class="form-input"/>
                    <br/>
                    <br/>
                    <label for="mpesa_ref">M-PESA reference</label>
                    <br/>
                    <input type="text" value="" id="mpesa_ref" placeholder="Mpesa Reference"
                           class="form-input"/>
                </div>
                <button id="pay_btn" style="width: 100%;margin-top: 15px;">Confirm Payment</button>

                <?php
            }


        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment($order_id)
        {
            global $wpdb;
            global $woocommerce;
            $table_name = $wpdb->prefix . 'mpesa_transactions';


            $order = new WC_Order($order_id);


            $endpoint = "{$this->api_url}{$this->payments_endpoint}";

            $total = $order->get_total();
            $phone = $order->get_billing_phone();
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $timestamp = $this->getTimeStamp();
            $password = base64_encode($this->store_no . $this->passkey . $timestamp);
            $accountRef = "{$timestamp}{$order_id}";


            if (!$phone || !preg_match('/^254[0-9]{9}$/', $phone)) {
                wc_add_notice('Phone number is incorrect! It should start with 2547xxxxxxxx', 'error');
                return [
                    'result' => 'fail',
                    'redirect' => ''
                ];
            }
            $postData = [
                'BusinessShortCode' => $this->store_no,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => $this->transaction_type,
                'Amount' => round($total),
                'PartyA' => $phone,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $this->mpesa_callback_url,
                'AccountReference' => $accountRef,
                'TransactionDesc' => 'WooCommerce Payment For ' . $order_id,
                'Remark' => 'WooCommerce Payment via MPesa'
            ];
            $token = $this->authenticate();

            $dataString = json_encode($postData);

            $payload = [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => "application/json",
                ],
                'body' => $dataString,
            ];


            $request = wp_remote_post($endpoint, $payload);
            $body = wp_remote_retrieve_body($request);


            if (is_wp_error($request)) {
                $error_message = 'There is issue connecting to the Mpesa payment gateway. Sorry for the inconvenience. ' . $this->api_url;
                $order->update_status('failed', 'Could not connect to MPesa to process payment.');
                wc_add_notice('Failed! ' . $error_message, 'error');

                return [
                    'result' => 'fail',
                    'redirect' => ''
                ];
            }

            if (empty($body)) {
                $error_message = 'Could not connect to MPesa to process payment. Please try again';
                $order->update_status('failed', 'Could not connect to MPesa to process payment.');
                wc_add_notice('Failed! ' . $error_message, 'error');
                return [
                    'result' => 'fail',
                    'redirect' => ''
                ];
            }


            $result = json_decode($body);

            $responseCode = $result->ResponseCode;

            file_put_contents('wc_response.log', $phone, FILE_APPEND);
            if ($responseCode == null) {
                $error_message = 'Could not process your MPesa transaction, please try again';
                $order->update_status('failed', 'Could not process your MPesa transaction, please try again.');
                wc_add_notice('Failed! ' . $error_message, 'error');
                return [
                    'result' => 'fail',
                    'redirect' => ''
                ];
            } else if ($responseCode != 0) {
                $msg = $result->errorMessage;
                $errorCode = $result->errorCode;
                if ($msg == null) {
                    $msg = $result->fault->faultstring;
                    //$errorCode = $result->fault->detail->errorcode;
                }
                $error_message = 'MPesa Error ' . $errorCode . ': ' . $msg;
                $order->update_status('failed', $error_message);
                $order->add_order_note($error_message);
                wc_add_notice('Failed! ', $error_message, 'error');
            } else if ($responseCode == 0) {
                $merchantRequestID = $result->MerchantRequestID;
                $checkoutRequestID = $result->CheckoutRequestID;
                $responseCode = $result->ResponseCode;
                $responseDesc = $result->ResponseDescription;
                $customerMessage = $result->CustomerMessage;

                //insert to transactions table
                $tableData = [
                    'order_id' => $order_id,
                    'first_name' => strtoupper($first_name),
                    'last_name' => strtoupper($last_name),
                    'phone_number' => $phone,
                    'transaction_time' => $timestamp,
                    'mpesa_ref' => $timestamp,
                    'merchant_request_id' => $merchantRequestID,
                    'checkout_request_id' => $checkoutRequestID,
                    'result_code' => $responseCode,
                    'result_desc' => $responseDesc,
                    'amount' => $total,
                    'processing_status' => $order->get_status(),
                ];
                $wpdb->insert(
                    $table_name,
                    $tableData
                );
                $record_id = $wpdb->insert_id;

                if ($record_id > 0) {
                    $order->update_status('pending', 'Awaiting mpesa confirmation');
                    $order->add_order_note($customerMessage);

                    $returnUrl = $this->get_return_url($order); //url to redericet after successfull checkout
                    $checkout_url = $order->get_checkout_payment_url(true);
                    $checkout_edited_url = $checkout_url . "&transactionType=checkout&rdr={$returnUrl}";


                    //redirect to order summary do not full fill the order yet
//                    return [
//                        'result' => 'success',
//                        'redirect' => $this->get_return_url($order),
//                    ];
                    return [
                        'result' => 'success',
                        'redirect' => add_query_arg('order', $order_id,
                            add_query_arg('key', $order->order_key, $checkout_edited_url)
                        )
                    ];
                }
            }
            return [
                'result' => 'fail',
                'redirect' => '',
            ];
        }

        /**
         * Generate authentication Token
         * @return bool|null
         */
        public function authenticate()
        {
            $endpoint = "{$this->api_url}{$this->credentials_endpoint}";

            $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);

            $payload = ['headers' => ['Authorization' => 'Basic ' . $credentials]];

            $request = wp_remote_get($endpoint, $payload);
            if (is_wp_error($request)) {
                return null;
            }
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body);
            if (empty($data)) {
                return null;
            } else {
                return $data->access_token;
            }
        }

        /**
         * @param bool $asDate
         * @return int|string
         */
        public function getTimeStamp()
        {
            return current_time('YmdHis');
        }


        /**
         * @return false|string
         */
        public function mpesa_callback()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'mpesa_transactions';

            $response = file_get_contents('php://input');
            $jsonData = json_decode($response);

            if (!isset($jsonData->Body)) {
                file_put_contents('wc_webhook_response.log', "No body jsonData \n", FILE_APPEND);
                file_put_contents('wc_webhook_response.log', $response, FILE_APPEND);
                wp_send_json($jsonData);
            }

            $callbackData = $jsonData->Body;

            $resultCode = $callbackData->stkCallback->ResultCode;
            $resultDesc = $callbackData->stkCallback->ResultDesc;
            $merchantRequestID = $callbackData->stkCallback->MerchantRequestID;
            $checkoutRequestID = $callbackData->stkCallback->CheckoutRequestID;

            $resp = [
                'ResponseCode' => $resultCode,
                'ResponseDesc' => 'Failed to process transaction results',
            ];

            $query = <<<SQL
SELECT
	order_id,
	phone_number,
	transaction_time,
	merchant_request_id,
	checkout_request_id,
	result_code,
	result_desc,
	amount,
	processing_status,
	created_at,
	updated_at 
FROM
	$table_name 
WHERE
	merchant_request_id ='$merchantRequestID'
SQL;

            $result = $wpdb->get_row($query);
            if ($result != null) {
                //convert to object for easy reading
                $mpesaTrans = (object)$result;
                $order_id = $mpesaTrans->order_id;

                if (wc_get_order($order_id)) {
                    $order = new WC_Order($order_id);
                    $first_name = $order->get_billing_first_name();
                    $last_name = $order->get_billing_last_name();
                    $customer = "{$first_name} {$last_name}";

                    $amount_due = $order->get_total();


                    if ($resultCode == 0) {
                        $amount_paid = $callbackData->stkCallback->CallbackMetadata->Item[0]->Value;
                        $mpesaReceiptNumber = $callbackData->stkCallback->CallbackMetadata->Item[1]->Value;
                        $balance = $callbackData->stkCallback->CallbackMetadata->Item[2]->Value;
                        $transactionDate = $callbackData->stkCallback->CallbackMetadata->Item[3]->Value;
                        $phone = $callbackData->stkCallback->CallbackMetadata->Item[4]->Value;

                        $ipn_balance = $amount_paid - $amount_due;
                        if ($ipn_balance == 0) {
                            $order->payment_complete();
                            $order->update_status('completed');
                            $order->add_order_note("Full M-PESA Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}");
                        } elseif ($ipn_balance > 0) {
                            $currency = get_woocommerce_currency();
                            $order->payment_complete();
                            $order->update_status('completed');
                            $order->add_order_note("{$customer} {$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}");
                        } else {
                            $order->update_status('on-hold');
                            $order->add_order_note("M-PESA Payment from {$phone} is Incomplete");
                        }

                        $tableData = [
                            'mpesa_ref' => $mpesaReceiptNumber,
                            'result_code' => $resultCode,
                            'result_desc' => $resultDesc,
                            'processing_status' => $order->get_status(),
                        ];
                        $conditionData = [
                            'merchant_request_id' => $merchantRequestID,
                        ];

                        $resp = [
                            'ResponseCode' => $resultCode,
                            'ResponseDesc' => "Payment processed successfully",
                        ];
                    } else {
                        $errorMsg = "M-PESA Error {$resultCode}: {$resultDesc}";
                        $order->update_status('failed');
                        $order->add_order_note($errorMsg);
                        $tableData = [
                            'mpesa_ref' => $order_id,
                            'result_code' => $resultCode,
                            'result_desc' => $resultDesc,
                            'processing_status' => $order->get_status(),
                        ];

                        $conditionData = [
                            'merchant_request_id' => $merchantRequestID,
                        ];

                        $resp = [
                            'ResponseCode' => $resultCode,
                            'ResponseDesc' => $errorMsg,
                        ];
                    }
                    $this->updateTransactionTable($tableData, $conditionData);
                }
            }

            wp_send_json($resp);
        }

        /**
         * Allow transaction to proceed
         * @todo Get WC transaction ID
         */
        public function mpesa_proceed($transID = 0)
        {
            return [
                'ResponseCode' => 0,
                'ResponseDesc' => 'Success',
                'ThirdPartyTransID' => $transID
            ];
        }

        /**
         * @param int $transID
         * @deprecated
         *
         */
        public function mpesa_confirm_old($transID = 0)
        {
            $response = file_get_contents('php://input');
            $callbackData = json_decode($response);

            if (!isset($callbackData->TransID)) {
                $resp = [
                    "ResultCode" => 1,
                    "ResultDesc" => "Failed",
                    "ThirdPartyTransID" => $transID
                ];
            } else {

                $amount_paid = $callbackData->TransAmount;
                $mpesaReceiptNumber = $callbackData->TransID;
                $balance = $callbackData->OrgAccountBalance;
                $transactionDate = $callbackData->TransTime;
                $phone = $callbackData->MSISDN;
                $firstName = $callbackData->FirstName;
                $middleName = $callbackData->MiddleName;
                $lastName = $callbackData->LastName;

                $tableData = [
                    'mpesa_ref' => $mpesaReceiptNumber,
                    'result_code' => $transID,
                    'phone_number' => $phone,
                    'transaction_type' => 'C2B',
                    'result_desc' => 'Mpesa C2B',
                    'processing_status' => 0,
                ];

                $args = [
                    //'limit' => -1,
                    //'return' => 'ids',
                    //'date_completed' => '2018-10-01...2020-10-10',
                    'status' => 'cancelled'
                ];
                $orders = wc_get_orders($args);
                if (!empty($orders)) {
                    foreach ($orders as $order) {
                        foreach ($order->get_items() as $item_id => $item_values) {
                            $order_id = $item_values['order_id'];
                            $order = new WC_Order($order_id);
                            $orderTotal = (float)$order->get_total();
                            $billingPhone = $order->get_billing_phone();
                            //check if billing phone matches the mpesa payment phone
                            if ($billingPhone == $phone && $amount_paid == $orderTotal) {
                                //stop loop and proceed with the processing
                                $tableData['order_id'] = $order_id;
                                $tableData['amount'] = $amount_paid;
                                //break;
                            }

                            $tableData['processing_status'] = $order->get_status();
                        }
                    }
                    wp_send_json($tableData);
                }

                wp_send_json($orders);
                $resp = [
                    "ResultCode" => 0,
                    "ResultDesc" => "Completed",
                    "ThirdPartyTransID" => $transID
                ];
            }

            wp_send_json($resp);
        }

        public function mpesa_confirm($transID = 0)
        {
            $response = file_get_contents('php://input');
            $callbackData = json_decode($response);

            if (!isset($callbackData->TransID)) {
                $resp = [
                    "ResultCode" => 1,
                    "ResultDesc" => "Failed",
                    "ThirdPartyTransID" => $transID
                ];
            } else {

                $amount_paid = $callbackData->TransAmount;
                $mpesaReceiptNumber = $callbackData->TransID;
                $balance = $callbackData->OrgAccountBalance;
                $transactionDate = $callbackData->TransTime;
                $phone = $callbackData->MSISDN;
                $firstName = $callbackData->FirstName;
                $middleName = $callbackData->MiddleName;
                $lastName = $callbackData->LastName;

                $tableData = [
                    'mpesa_ref' => $mpesaReceiptNumber,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'transaction_time' => $transactionDate,
                    'last_name' => $lastName,
                    'amount' => $amount_paid,
                    'result_code' => 0,
                    'phone_number' => $phone,
                    'transaction_type' => 'C2B',
                    'result_desc' => 'Mpesa C2B',
                    'processing_status' => 0,
                ];

                //insert to table then the user wil confirm later
                $result = $this->insertToTransactionTable($tableData);
                $resp = [
                    "ResultCode" => 0,
                    "ResultDesc" => "Completed",
                    "ThirdPartyTransID" => $transID,
                    'result' => $result
                ];
            }

            wp_send_json($resp);
        }

        /**
         * @param array $tableData
         * @param $tableName
         * @param $conditionData
         * @return bool|false|int
         */
        public function updateTransactionTable(array $tableData, array $conditionData)
        {
            global $wpdb;
            $tableName = $wpdb->prefix . 'mpesa_transactions';
            return $wpdb->update(
                $tableName,
                $tableData,
                $conditionData
            );
        }

        /**
         * @param array $tableData
         * @return bool|false|int
         */
        public function insertToTransactionTable(array $tableData)
        {
            global $wpdb;

            $tableName = $wpdb->prefix . 'mpesa_transactions';
            return $wpdb->insert(
                $tableName,
                $tableData
            );
        }

    }
}

//Process the payments
function reu_request_payment()
{
    global $wpdb;
    global $woocommerce;

    $tableName = $wpdb->prefix . 'mpesa_transactions';

    $resp = $_POST;
    $orderId = $resp['order_id'];
    $mpesaRef = $resp['mpesa_ref'];
    $phoneNumber = $resp['phone_number'];
    $amountPaid = $resp['amount_paid'];
    $redirectUrl = $resp['redirect_url'];
    $mode = $resp['mode'];

    if ($mode == "stk") {
        $query = <<<SQL
SELECT
	order_id,
	phone_number,
	transaction_time,
	merchant_request_id,
	checkout_request_id,
    mpesa_ref,
	result_code,
	result_desc,
	amount,
	processing_status,
	created_at,
	updated_at 
FROM
	$tableName 
WHERE
	order_id ='$orderId'
AND
    processing_status =  'completed'
AND
    amount = $amountPaid
SQL;
    } else {
        $query = <<<SQL
SELECT
	order_id,
	phone_number,
	transaction_time,
	merchant_request_id,
	checkout_request_id,
	result_code,
	result_desc,
	amount,
	processing_status,
	created_at,
	updated_at 
FROM
	$tableName 
WHERE
    phone_number = '$phoneNumber'
AND
    mpesa_ref ='$mpesaRef'
AND
    amount = '$amountPaid'
AND
    processing_status =  '0'
SQL;
    }

    //query the mpesa table for that particular order
    $result = $wpdb->get_row($query);

    $resp = [
        'respCode' => 1,
        'resp' => 'Payment confirmation failed, please try again'
    ];
    if ($result != null) {

        $merchantRequestID = $result->merchant_request_id;


        if (wc_get_order($orderId)) {
            $order = new WC_Order($orderId);
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $customer = "{$first_name} {$last_name}";

            $tableData = [
                'result_code' => 0
            ];

            if ($mode == "stk") {
                $mpesaRef = $result->mpesa_ref;
                $tableData['processing_status'] = 'reconciled';
                $conditionData = [
                    'merchant_request_id' => $merchantRequestID,
                ];
            } else {
                $tableData['order_id'] = $order->get_order_key();
                $tableData['merchant_request_id'] = $order->get_order_key();
                $tableData['checkout_request_id'] = $order->get_order_key();
                $tableData['result_code'] = 0;
                $tableData['processing_status'] = 'completed';


                $conditionData = [
                    'phone_number' => $phoneNumber,
                    'mpesa_ref' => $mpesaRef,
                    'amount' => $amountPaid,
                ];
            }


            $updateResult = $wpdb->update(
                $tableName,
                $tableData,
                $conditionData
            );

            if ($updateResult == 1) {

                $currency = get_woocommerce_currency();
                $order->payment_complete();
                $order->update_status('completed');
                $order->add_order_note("{$customer} {$phoneNumber} has fully paid {$currency} {$amountPaid} and confirmed. Receipt Number {$mpesaRef}");

                $woocommerce->cart->empty_cart();
                $resp = [
                    'respCode' => 0,
                    'checkout' => $redirectUrl,
                    'resp' => 'Payment completed successfully'
                ];

            } else {
                $resp = [
                    'respCode' => 1,
                    'resp' => 'Unable to process payment confirmation, please try again'
                ];
                $order->update_status('failed');
                $order->add_order_note("M-PESA Payment from {$phoneNumber} has failed");
            }
        }
    }

    wp_send_json($resp);
}

//Create Table for M-PESA Transactions
function create_mpesa_transactions_table()
{

    global $wpdb;
    global $transaction_db_version;
    $transaction_db_version = '1.0';

    $table_name = $wpdb->prefix . 'mpesa_transactions';

    $charset_collate = $wpdb->get_charset_collate();


    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS $table_name (
	    id bigint NOT NULL AUTO_INCREMENT,
		order_id varchar(150) DEFAULT NULL,
		phone_number varchar(35) DEFAULT NULL,
        first_name varchar(150) NOT NULL,
        middle_name varchar(150) NOT NULL,
        last_name varchar(150) NULL,
		transaction_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		merchant_request_id varchar(150) DEFAULT NULL,
		checkout_request_id varchar(150) DEFAULT NULL,
		result_code varchar(150) DEFAULT 0,
		result_desc varchar(200) DEFAULT NULL,
		amount decimal(10,2) DEFAULT NULL,
		mpesa_ref varchar(120) NOT NULL,
		currency varchar(4) DEFAULT 'KES' NULL,
		processing_status varchar(20) DEFAULT '0' NULL,
		transaction_type varchar(20) DEFAULT 'STK' NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY mpesa_ref (mpesa_ref),
		PRIMARY KEY  (id) using BTREE
		)ENGINE=InnoDB $charset_collate
SQL;


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);

    add_option('transaction_db_version', $transaction_db_version);

}