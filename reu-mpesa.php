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

class MyMpesa
{

}

function enqueue_scripts_func()
{

//    wp_enqueue_script('ajaxcontact', ACFSURL . '/js/ajaxcontact.js', array('jquery'));
    wp_enqueue_script('ajaxmpesa', ACFSURL . '/js/ajax-mpesa.js', array('jquery'));
//    wp_localize_script('ajaxcontact', 'ajaxcontactajax', array('ajaxurl' => admin_url('admin-ajax.php')));
    wp_localize_script('ajaxmpesa', 'ajaxmpesatajax', array('ajaxurl' => admin_url('admin-ajax.php')));
}

add_action('wp_enqueue_scripts', "enqueue_scripts_func");

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

            <input type="text" id="ajaxcontactemail" name="ajaxcontactemail"/><br/>

            <br/>

            <strong>Subject </strong> <br/>

            <input type="text" id="ajaxcontactsubject" name="ajaxcontactsubject"/><br/>

            <br/>

            <strong>Contents </strong> <br/>

            <textarea id="ajaxcontactcontents" name="ajaxcontactcontents" rows="10" cols="20"></textarea><br/>

            <a onclick="ajaxformsendmail(ajaxcontactname.value,ajaxcontactemail.value,ajaxcontactsubject.value,ajaxcontactcontents.value);"
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