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

    wp_register_script(
        'ajaxHandle',
        plugins_url('/js/ajax-mpesa.js', __FILE__),
        array('jquery'),
        false,
        true
    );
    wp_enqueue_script('jqueryMask');
    wp_enqueue_script('ajaxHandle');

    wp_localize_script(
        'ajaxHandle',
        'mpesaAjax',
        array('ajaxurl' => admin_url('admin-ajax.php'))
    );
}


function contact_form_func()
{
    ?>
    <form id="ajaxcontactform" action="" method="post" enctype="multipart/form-data">

        <div id="ajaxcontact-text">

            <div id="ajaxcontact-response" style="background-color:#E6E6FA ;color:blue;"></div>

            <strong>Name </strong> <br/>

            <input type="text" id="ajaxcontactname" name="ajaxcontactname"/><br/>

            <br/>

            <strong>Email </strong> <br/>

            <input type="text" id="email" name="email" class="alpha-no-spaces"/><br/>

            <input type="text" class="form-control phone" id="phone" name="phone"
                   placeholder="Enter Phone number" required="required">
            <br/>

            <strong>Subject </strong> <br/>

            <input type="text" id="ajaxcontactsubject" name="ajaxcontactsubject"/><br/>

            <br/>

            <strong>Contents </strong> <br/>

            <textarea id="ajaxcontactcontents" name="ajaxcontactcontents" rows="10" cols="20"></textarea><br/>

            <button type="button" class="button wp-generate-pw hide-if-no-js">Generate Password</button>

            <button type="button" class="button wpforms-form" id="stk-button">Checkout</button>

            <a onclick="sendMpesaSTKRequest();"
               style="cursor: pointer"><b>Send Mail</b></a>

        </div>
    </form>
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

function ajaxcontact_send_mail()
{
    $results = '';
    $error = 0;

    $name = $_POST['acfname'];
    $email = $_POST['acfemail'];
    $subject = $_POST['acfsubject'];
    $contents = $_POST['acfcontents'];
    $admin_email = get_option('admin_email');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $results = $email . " :email address is not valid.";
        $error = 1;
    } elseif (strlen($name) == 0) {
        $results = "Name is invalid.";
        $error = 1;
    }

    if ($error == 0) {
        $headers = 'From:' . $email . "rn";
        if (wp_mail($admin_email, $subject, $contents, $headers)) {
            $results = "*Thanks for you mail.";
        } else {
            $results = "*The mail could not be sent.";
        }
    }

    $resp = [
        'names' => 'sammy',
        'resp' => $results
    ];
    echo json_encode($resp);
    die();
}

//Register ajax handlers
add_action('wp_ajax_nopriv_ajaxcontact_send_mail', 'ajaxcontact_send_mail');
add_action('wp_ajax_ajaxcontact_send_mail', 'ajaxcontact_send_mail');