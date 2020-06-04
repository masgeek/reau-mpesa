<?php

/*
Plugin Name: Reu Mpesa
Plugin URI: https://tsobu.co.ke/mpesa
Description: MPESA Payment plugin for wordpress
Version: 1.0
Author: Sammy Barasa
Author URI: https://tsobu.co.ke
License: GPL2
*/

defined('ABSPATH') or die('No script kiddies please!');

require_once 'vendor/autoload.php';

define('ACFSURL', WP_PLUGIN_URL . "/" . dirname(plugin_basename(__FILE__)));


add_action('wp_enqueue_scripts', "enqueue_scripts_func");

function enqueue_scripts_funcOld()
{
    //wp_deregister_script('jquery-core');
    //wp_register_script('jquery-core', ACFSURL . '/vendor/npm-asset/jquery/dist/jquery.js', array(), '3.5.1');
    //wp_deregister_script('jquery-migrate');
    //wp_register_script('jquery-migrate', ACFSURL . '/vendor/npm-asset/jquery-migrate/dist/jquery-migrate.js', array(), '3.3.0');

//    wp_enqueue_script('ajaxcontact', ACFSURL . '/js/ajaxcontact.js', array('jquery'));
    wp_enqueue_script('ajaxmpesa', ACFSURL . '/vendor/npm-asset/jquery-mask-plugin/dist/jquery.mask.js', array('jquery'), '1.14.16');
    wp_enqueue_script('ajaxmpesa', ACFSURL . '/js/ajax-mpesa.js', array('jquery'), '1.1.0');


//    wp_localize_script('ajaxcontact', 'ajaxcontactajax', array('ajaxurl' => admin_url('admin-ajax.php')));
    wp_localize_script('ajaxmpesa', 'ajaxmpesaajax', array('ajaxurl' => admin_url('admin-ajax.php')));
}

function enqueue_scripts_func()
{
    wp_register_script('jqueryMask',
        plugins_url('/vendor/npm-asset/jquery-mask-plugin/dist/jquery.mask.js', __FILE__),
        array('jquery'),
        '1.14.16',
        true
    );

    wp_register_style('bootstrap',
        plugins_url('/vendor/npm-asset/bootstrap/dist/css/bootstrap.css', __FILE__),
        array(),
        '4.5.0',
        'all'
    );

    wp_register_script(
        'ajaxHandle',
        plugins_url('/js/ajax-mpesa.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );
    wp_enqueue_script('jqueryMask');
    wp_enqueue_script('ajaxHandle');
    wp_enqueue_style('bootstrap');

    wp_localize_script('ajaxHandle', 'ajaxMpesaCheckout', array('ajaxurl' => admin_url('admin-ajax.php')));
}


function contact_form_func()
{
    ?>
    <div class="container">
        <div class="row">
            <div class="col-sm-12">
                <div id="ajax-response"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <!-- form -->
                <form enctype="multipart/form-data" action="" method="post" name="mpesa-form" id="mpesa-form">
                    <div class="card">
                        <div class="card-header bg-success text-white">MOBILE MONEY PAYMENTS</div>
                        <div class="card-body">

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" class="form-control phone" id="phone" name="phone"
                                       aria-describedby="phone"
                                       placeholder="Enter Phone number" required="required">
                                <small id="phoneHelp" class="form-text text-muted">
                                    Enter phone number you'll be paying with
                                </small>
                            </div>


                            <div class="form-group">
                                <label for="amount">Amount</label>
                                <input type="text" class="form-control" id="amount" name="amount"
                                       aria-describedby="amountHelp"
                                       placeholder="Enter Amount" required="required">
                                <small id="amountHelp" class="form-text text-muted">Enter amount you want to pay</small>
                            </div>

                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-sm-4">
                                    <button type="button" class="btn btn-outline-success btn-block" id="stk-button">
                                        Checkout
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <!-- form -->
            </div>
        </div>
    </div>
    <?php
}

function mpesa_form_func($atts)
{

    ob_start();

    contact_form_func();

    $output = ob_get_contents();

    ob_end_clean();

    return $output;

}

add_shortcode("mpesa_form", "mpesa_form_func");

function process_mpesa()
{
    $results = '';
    $error = 0;

    $name = $_POST['names'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $contents = $_POST['acfcontents'];
    $admin_email = get_option('admin_email');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $results = "Email address; {$email} is not valid.";
        $error = 1;
    } elseif (strlen($name) == 0) {
        $results = "Name is invalid.";
        $error = 1;
    }

    if ($error == 0) {
        //process the payment info
    }

    $resp = [
        'resp' => $results
    ];
    echo json_encode($resp);
    exit($error);
}

//Register ajax handlers
add_action('wp_ajax_nopriv_process_mpesa', 'process_mpesa');
add_action('wp_ajax_process_mpesa', 'process_mpesa');