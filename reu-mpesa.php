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

define('ACFSURL', WP_PLUGIN_URL . "/" . dirname(plugin_basename(__FILE__)));
define('MPESA_DIR', plugin_dir_path(__FILE__));
define('MPESA_INC_DIR', MPESA_DIR . 'includes/');


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

//Calls the create_mpesa_transactions_table function during plugin activation which creates table that records mpesa transactions.
register_activation_hook(__FILE__, 'create_mpesa_transactions_table');

//add_action('wp_enqueue_scripts', "enqueue_scripts_func");
function reu_enqueue_scripts_func()
{
    wp_register_script('jqueryMask',
        plugins_url('/vendor/npm-asset/jquery-mask-plugin/dist/jquery.mask.min.js', __FILE__),
        array('jquery'),
        '1.14.16',
        true
    );

    wp_register_style('bootstrap',
        plugins_url('/vendor/npm-asset/bootstrap/dist/css/bootstrap.min.css', __FILE__),
        array(),
        '4.5.0',
        'all'
    );

    wp_register_style('fontawesome',
        '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css',
        array(),
        '4.1.0',
        'all'
    );

    wp_register_style('googlefont',
        '//fonts.googleapis.com/css?family=Open+Sans:400,700',
        array(),
        null,
        'all'
    );

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
            $this->icon = plugin_dir_url(__FILE__) . 'mpesa-logo.png';
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

            $this->mpesa_callback_url = 'https://webhook.site/ae877091-9700-40da-8016-b02114ab3d01';

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
                'passkey' => [
                    'title' => 'API pass key',
                    'description' => 'Pass key for authentication',
                    'default' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
                    'type' => 'textarea',
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
                'transaction_type' => [
                    'title' => 'Transaction type',
                    'options' => [
                        'CustomerPayBillOnline' => 'CustomerPayBillOnline',
                        'CustomerBuyGoodsOnline' => 'CustomerBuyGoodsOnline'
                    ],
                    'required' => true,
                    'type' => 'select',
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
            ];
        }


        /*
          * Fields validation, more in Step 5
         */
        public function validate_fields()
        {

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

            $phone = str_replace([' ', '<', '>', '&', '{', '}', '*', "+", '!', '@', '#', "$", '%', '^', '&', '-'], "", $phone);
            $phone = preg_replace('/^0/', '254', $phone);
            $timestamp = $this->getTimeStamp();
            $password = base64_encode($this->store_no . $this->passkey . $timestamp);
            $accountRef = "{$timestamp}{$order_id}";


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
                file_put_contents('wc_response.log', "\n", FILE_APPEND);
                file_put_contents('wc_response.log', $body, FILE_APPEND);
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
                $table_name = $wpdb->prefix . 'mpesa_transactions';
                $tableData = [
                    'order_id' => $order_id,
                    'phone_number' => $phone,
                    'transaction_time' => $timestamp,
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
                    $order->update_status('processing', 'Awaiting mpesa confirmation');
                    $order->add_order_note($customerMessage);
                    $woocommerce->cart->empty_cart();
                    return [
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
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
                return;
            }

            $callbackData = $jsonData->Body;

            $resultCode = $callbackData->stkCallback->ResultCode;
            $resultDesc = $callbackData->stkCallback->ResultDesc;
            $merchantRequestID = $callbackData->stkCallback->MerchantRequestID;
            $checkoutRequestID = $callbackData->stkCallback->CheckoutRequestID;


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

                        file_put_contents('wc_webhook_response.log', "we have db jsonData\n", FILE_APPEND);
                        file_put_contents('wc_webhook_response.log', $result, FILE_APPEND);
                    } else {
                        $errorMsg = "M-PESA Error {$resultCode}: {$resultDesc}";
                        $order->update_status('failed');
                        $order->add_order_note($errorMsg);
                        $tableData = [
                            'mpesa_ref' => null,
                            'result_code' => $resultCode,
                            'result_desc' => $resultDesc,
                            'processing_status' => $order->get_status(),
                        ];
                    }
                    $this->updateTransactionTable($tableData, $table_name, $merchantRequestID);
                }
            }
        }

        /**
         * @param array $tableData
         * @param $tableName
         * @param $merchantRequestID
         */
        function updateTransactionTable(array $tableData, $tableName, $merchantRequestID)
        {
            global $wpdb;

            file_put_contents('wc_webhook_response.log', "we have db data\n", FILE_APPEND);
            file_put_contents('wc_webhook_response.log', $merchantRequestID, FILE_APPEND);

            $wpdb->update(
                $tableName,
                $tableData,
                [
                    'merchant_request_id' => $merchantRequestID,
                ]
            );
        }
    }
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
		order_id varchar(150) DEFAULT '' NULL,
		phone_number varchar(35) DEFAULT '' NULL,
		transaction_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		merchant_request_id varchar(150) DEFAULT '' NULL,
		checkout_request_id varchar(150) DEFAULT '' NULL,
		result_code varchar(150) DEFAULT '' NULL,
		result_desc varchar(200) DEFAULT '' NULL,
		amount decimal(10,2) DEFAULT NULL,
		mpesa_ref varchar(120) DEFAULT '' NULL,
		currency varchar(4) DEFAULT 'KES' NULL,
		processing_status varchar(20) DEFAULT '0' NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id) using BTREE
		)$charset_collate
SQL;


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);

    add_option('transaction_db_version', $transaction_db_version);

}

//Process the payments
function reu_request_payment()
{
    global $wpdb;

    if (isset($_SESSION['ReqID'])) {


        $table_name = $wpdb->prefix . 'mpesa_transactions';

        $trx_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE merchant_request_id = '" . $_SESSION['ReqID'] . "' and processing_status = 0");

        //If it exists do not allow the transaction to proceed to the next step

        if ($trx_count > 0) {

            echo json_encode(array("rescode" => "99", "resmsg" => "A similar transaction is in progress, to check its status click on Confirm Order button"));

            exit();

        }

    }

    $total = $_SESSION['total'];

    $url = $_SESSION['credentials_endpoint'];


    $YOUR_APP_CONSUMER_KEY = $_SESSION['ck'];

    $YOUR_APP_CONSUMER_SECRET = $_SESSION['cs'];

    $credentials = base64_encode($YOUR_APP_CONSUMER_KEY . ':' . $YOUR_APP_CONSUMER_SECRET);


    //Request for access token


    $token_response = wp_remote_get($url, array('headers' => array('Authorization' => 'Basic ' . $credentials)));


    $token_array = json_decode('{"token_results":[' . $token_response['body'] . ']}');


    if (array_key_exists("access_token", $token_array->token_results[0])) {

        $access_token = $token_array->token_results[0]->access_token;

    } else {

        echo json_encode(array("rescode" => "1", "resmsg" => "Error, unable to send payment request"));

        exit();

    }


    ///If the access token is available, start lipa na mpesa process

    if (array_key_exists("access_token", $token_array->token_results[0])) {


        ////Starting lipa na mpesa process

        $url = $_SESSION['payments_endpoint'];


        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

        $domainName = $_SERVER['HTTP_HOST'] . '/';

        $callback_url = $protocol . $domainName;


        //Generate the password//

        $shortcd = $_SESSION['shortcode'];

        $timestamp = date("YmdHis");

        $b64 = $shortcd . $_SESSION['passkey'] . $timestamp;

        $pwd = base64_encode($b64);


        ///End in pwd generation//


        $curl_post_data = array(

            //Fill in the request parameters with valid values

            'BusinessShortCode' => $shortcd,

            'Password' => $pwd,

            'Timestamp' => $timestamp,

            'TransactionType' => 'CustomerPayBillOnline',

            'Amount' => $total,

            'PartyA' => $_SESSION['tel'],

            'PartyB' => $shortcd,

            'PhoneNumber' => $_SESSION['tel'],

            'CallBackURL' => $callback_url . '/index.php?callback_action=1',

            'AccountReference' => time(),

            'TransactionDesc' => 'Sending a lipa na mpesa request'

        );


        $data_string = json_encode($curl_post_data);


        $response = wp_remote_post($url, array('headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),

            'body' => $data_string));


        $response_array = json_decode('{"callback_results":[' . $response['body'] . ']}');


        if (array_key_exists("ResponseCode", $response_array->callback_results[0]) && $response_array->callback_results[0]->ResponseCode == 0) {

            $_SESSION['ReqID'] = $response_array->callback_results[0]->MerchantRequestID;
            echo json_encode(array("rescode" => "0", "resmsg" => "Request accepted for processing, check your phone to enter M-PESA pin"));


        } else {

            echo json_encode(array("rescode" => "1", "resmsg" => "Payment request failed, please try again"));


        }

        exit();


    }


}