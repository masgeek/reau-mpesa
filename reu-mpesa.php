<?php

/*
Plugin Name: Reu Mpesa
Plugin URI: https://tsobu.co.ke/mpesa
Description: MPESA Payment plugin for wordpress
Version: 2.0.0
Author: Sammy Barasa
Author URI: https://tsobu.co.ke
License: GPL2
*/

use Balambasik\Input;

defined('ABSPATH') or die('No script kiddies please!');

require_once 'vendor/autoload.php';

require_once 'inc/MpesaFactory.php';

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


function mpesa_checkout_form()
{
    ?>

    <div class="form-group">
        <label for="phone">Full Names</label>
        <input type="text" class="form-control" id="name" name="userInfo[name]"
               aria-describedby="name"
               placeholder="Enter full names" required="required">
        <small id="nameHelp" class="form-text text-muted">
            Enter your full names
        </small>
    </div>

    <div class="row">
        <div class="col-md">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" class="form-control phone" id="phone" name="userInfo[phone]"
                       aria-describedby="phone"
                       placeholder="Enter Phone number" required="required">
                <small id="phoneHelp" class="form-text text-muted">
                    Enter phone number you'll be paying with
                </small>
            </div>
        </div>
        <div class="col-md">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="text" class="form-control no-space" id="email" name="userInfo[email]"
                       aria-describedby="email"
                       placeholder="Enter email address" required="required">
                <small id="emailHelp" class="form-text text-muted">
                    Enter your email address so that we can send you the book
                </small>
            </div>
        </div>
    </div>


    <?php
}

function cart_form_old()
{
    $taxRate = 14;

    $description = <<<DESC
 Let’s go on an adventure in Furaha Valley, and Join Otis the Gingercat and his wonderful friends, 
 Lulu and Cliff on an adventure. Otis and Cliff woke up today with big smiles on their faces, excited to  Jump rope with Lulu, 
 but they can’t find Lulu anywhere. Have you seen Lulu?
DESC;

    $productData = [
        [

            'itemName' => 'Otis the Gingercat : Have you seen Lulu',
            'itemDescription' => $description,
            'itemPrice' => 150,
            'itemImage' => 'https://picsum.photos/id/237/150',
        ],
        [
            'itemName' => 'Book B',
            'itemPrice' => 200,
            'itemImage' => 'https://picsum.photos/id/236/150',
        ]
    ];

    ?>
    <!-- products section -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md"></div>
                <div class="col-md">Product</div>
                <div class="col-md">Item Price</div>
                <div class="col-md">Quantity</div>
                <div class="col-md text-right">Total</div>
                <div class="col-md">&nbsp;</div>
            </div>
        </div>
    </div>
    <?php foreach ($productData as $key => $productArr):
    $product = (object)$productArr;
    ?>
    <div class="card">
        <div class="card-body">
            <div class="row product">

                <input class="form-control hidden" readonly value="<?= $product->itemPrice ?>"
                       name="checkout[<?= $key ?>][itemPrice]"/>

                <div class="col-md">
                    <img src="<?= $product->itemImage ?>" class="img img-thumbnail"
                         alt="<?= $product->itemName ?>"/>
                </div>
                <div class="col-md"><?= $product->itemName ?></div>

                <div class="col-md product-price">
                    <?= $product->itemPrice ?>
                </div>
                <div class="col-md-4 product-quantity">
                    <input class="form-control" type="number" min="0" value="0"
                           name="checkout[<?= $key ?>][quantity]"/>
                    <small class="form-text text-muted">Quantity</small>
                </div>
                <div class="col-md text-right product-line-price">0.00</div>

                <div class="col-md text-right product-removal">
                    <button class="btn btn-sm btn-danger btn-block disabled remove-product">
                        <i class="fa fa-trash-o"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
    <!-- end products section -->
    <hr/>
    <div class="totals">
        <div class="row totals-item">
            <div class="col-md text-right">Sub-Total</div>
            <div class="col-md-2 text-right" id="cart-subtotal">0.00</div>
        </div>
        <div class="row totals-item">
            <div class="col-md text-right">Tax(<?= $taxRate ?>)%</div>
            <div class="col-md-2 text-right totals-value" id="cart-tax">0.00</div>
        </div>

        <div class="row totals-item totals-item-total">
            <div class="col-md text-right"><strong>Grand Total</strong></div>
            <div class="col-md-2 text-right totals-value">
                <strong id="cart-total">0.00</strong>
            </div>
        </div>
    </div>
    <?php
}

function cart_form()
{
    $taxRate = 14;

    $description = <<<DESC
 Let’s go on an adventure in Furaha Valley, and Join Otis the Gingercat and his wonderful friends, 
 Lulu and Cliff on an adventure. Otis and Cliff woke up today with big smiles on their faces, excited to  Jump rope with Lulu, 
 but they can’t find Lulu anywhere. Have you seen Lulu?
DESC;

    $productData = [
        [

            'itemName' => 'Otis the Gingercat : Have you seen Lulu',
            'itemDescription' => $description,
            'itemPrice' => 15000,
            'currency' => 'KSH',
            'itemImage' => 'http://placekitten.com/200/150',
            'metaData' => [
                'Size : 32 pages',
                'Genre: Children’s Picture book',
                'Age: 4-7 yrs',
                'Format : PDF',
                'Download Only'
            ]
        ],
    ];
    ?>
    <!-- products section -->
    <?php
    $index = 1;
    foreach ($productData as $key => $productArr):
        $product = (object)$productArr;
        product_detail($index, $product);
        $index++;
    endforeach; ?>
    <!-- end products section -->
    <hr/>
    <div class="totals">
        <div class="row totals-item">
            <div class="col-md text-right"><h5 class="sub-total">Sub-Total</h5></div>
            <div class="col-md-2 text-right" id="cart-subtotal"><h5 class="sub-total">0.00</h5></div>
        </div>

        <div class="row totals-item totals-item-total">
            <div class="col-md text-right"><h4 class="total-price">Grand Total</h4></div>
            <div class="col-md-2 text-right totals-value">
                <h4 class="total-price" id="cart-total">0.00</h4>
            </div>
        </div>
    </div>
    <?php
}

/**
 * @param $key
 * @param $product
 */
function product_detail($key, $product)
{
    $metaData = $product->metaData;
    ?>

    <div class="product">
        <div class="row">
            <input class="form-control hiddens product-price" readonly value="<?= $product->itemPrice ?>"
                   name="checkout[<?= $key ?>][itemPrice]" id="checkout-<?= $key ?>-itemPrice"/>

            <div class="preview col-md">
                <img class="card-img" src="<?= $product->itemImage ?>"/>
            </div>
            <div class="details col-md">
                <h4 class="product-title"><?= $product->itemName ?></h4>
                <p class="product-description"><?= $product->itemDescription ?></p>
                <small>
                    <ul>
                        <?php foreach ($metaData as $key2 => $value): ?>
                            <li><?= $value ?></li>
                        <?php endforeach; ?>
                    </ul>
                </small>

                <h4 class="price float-right">
                    <?= $product->currency ?>
                    <span><?= number_format($product->itemPrice, 2) ?></span>
                </h4>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 product-quantity">
                <input class="form-control" type="number" min="0" value="0"
                       name="checkout[<?= $key ?>][quantity]" id="checkout-<?= $key ?>-quantity"/>
                <small class="form-text text-muted">Quantity</small>
            </div>
            <div class="col-md text-right product-line-price">0.00</div>
        </div>
    </div>
    <?php
}


function combine_forms()
{
    ?>
    <div class="row">
        <div class="col-sm-12">
            <div id="ajax-response"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-success text-white">Buy book</div>
        <div class="card-body">
            <form enctype="multipart/form-data" action="" method="post" name="mpesa-form" id="mpesa-form">
                <?php
                cart_form();
                mpesa_checkout_form();
                ?>
            </form>
        </div>
        <div class="card-footer">
            <button type="button" class="btn btn-success btn-block text-uppercase" id="stk-button">
                Pay
            </button>
        </div>
    </div>
    <?php
}

function mpesa_form_func($atts)
{

    ob_start();

    combine_forms();
    $output = ob_get_contents();

    ob_end_clean();

    return $output;

}


add_shortcode("mpesa_form", "mpesa_form_func");

function process_mpesa()
{
    $results = '';
    $error = 0;

    $post = Input::post();
    $checkoutPost = $_POST['checkout'];;

    $itemPriceArr = $_POST['checkout'];
    $quantityArr = $_POST['quantity'];

    $resp = [
        $post
    ];

    echo json_encode($resp);
    exit($error);
    $name = $_POST['name'];
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
        $mpesa = new \mpesa\MpesaFactory();
        $timestamp = $mpesa->GetTimeStamp(true);

        $results = $timestamp;
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