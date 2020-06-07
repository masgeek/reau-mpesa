<?php

/*
Plugin Name: Reu Att Mpesa gateway
Plugin URI: https://tsobu.co.ke/mpesa
Description: M-PESA Payment plugin for woocommerce
Version: 2.0.0
Author: Sammy Barasa
Author URI: https://tsobu.co.ke
License: GPL2

* WC requires at least: 2.2
* WC tested up to: 4.9.7
*/

defined('ABSPATH') or die('No script kiddies please!');

require_once 'vendor/autoload.php';

define('ACFSURL', WP_PLUGIN_URL . "/" . dirname(plugin_basename(__FILE__)));

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'reu_add_gateway_class');
function reu_add_gateway_class($gateways)
{
    $gateways[] = 'WcMpesaGateway'; // your class name is here
    return $gateways;
}

add_filter('query_vars', function ($query_vars) {
    /* add additional parameters to request string to help wordpress call the actoin*/
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
        public $testmode;
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


        /**
         * @var bool
         */
        public function __construct()
        {

            // Basic settings

            $this->id = 'reuatt-mpesa';
            $this->icon = plugin_dir_url(__FILE__) . 'mpesa-logo.png';
            $this->has_fields = false;
            $this->method_title = 'M-Pesa Payment';
            $this->method_description = __('Enable customers to make payments via mpesa');

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
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

            //Turn these settings into variables we can use
//            foreach ($this->settings as $setting_key => $value) {
//                $this->$setting_key = $value;
//            }


            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
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
                'shortcode' => [
                    'title' => 'Short Code',
                    'description' => 'Short code, this is the number customers pay to',
                    'default' => 174379,
                    'type' => 'number',
                    'desc_tip' => false
                ],
                'store_no' => [
                    'title' => 'Store Number',
                    'description' => 'Store number, this is used during till number payments',
                    'default' => 600000,
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
                    'type' => 'password',
                ],
                'consumer_secret' => [
                    'title' => 'Consumer secret',
                    'description' => 'Consumer secret',
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
                'credentials_endpoint' => array(
                    'readonly' => true,
                    'title' => __('Credentials Endpoint', 'woocommerce'),
                    'default' => 'oauth/v1/generate?grant_type=client_credentials',
                    'description' => 'Default is: oauth/v1/generate?grant_type=client_credentials',
                    'type' => 'text',
                    'desc_tip' => true
                ),

                'payments_endpoint' => array(
                    'title' => 'Payments Endpoint',
                    'description' => '/mpesa/stkpush/v1/processrequest',
                    'default' => 'Default is: mpesa/stkpush/v1/processrequest',
                    'required' => true,
                    'type' => 'text',
                    'desc_tip' => true
                ),
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


        }

        /*
         * In case you need a webhook, like PayPal IPN etc
         */
        public function webhook()
        {

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
		transaction_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		merchant_request_id varchar(150) DEFAULT '' NULL,
		checkout_request_id varchar(150) DEFAULT '' NULL,
		result_code varchar(150) DEFAULT '' NULL,
		result_desc varchar(200) DEFAULT '' NULL,
		amount decimal(10,2) DEFAULT NULL,
		processing_status varchar(20) DEFAULT '0' NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
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

