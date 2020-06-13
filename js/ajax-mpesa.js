"use strict";

const getHost = window.location.protocol + "//" + window.location.host;
//
// function pay() {
//     var xhttp = new XMLHttpRequest();
//
//     console.log(xhttp);
//     xhttp.onreadystatechange = function () {
//         if (this.readyState == 4 && this.status == 200) {
//             var obj2 = JSON.parse(this.responseText);
//             if (obj2.rescode == 0) {
//                 document.getElementById("commonname").className = "waiting_success";
//             } else {
//                 document.getElementById("commonname").className = "error";
//             }
//             document.getElementById("commonname").innerHTML = obj2.resmsg;
//         }
//         ;
//         //xhttp.open("POST", getHost+"/wp-content/plugins/woocommerce_mpesa/callback_scanner.php", true);
//
//         xhttp.open("POST", getHost + "/?payment_action=1", true);
//         xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
//         xhttp.send("type=STK");
//     }
//
// }


jQuery(document).ready(function ($) {


    jQuery('.no-prompt').on('click', function (e) {
        //switch to the manual payment method
        jQuery('#payment_mode').val('manual');
        jQuery('.stk-prompt').removeClass('hidden');
        jQuery('.no-prompt').addClass('hidden');
        jQuery('.stk-instructions').addClass('hidden');
        jQuery('.manual-instructions').removeClass('hidden');
    });

    jQuery('.stk-prompt').on('click', function (e) {
        //switch to the manual payment method
        jQuery('#payment_mode').val('stk');
        jQuery('.no-prompt').removeClass('hidden');
        jQuery('.stk-prompt').addClass('hidden');
        jQuery('.stk-instructions').removeClass('hidden');
        jQuery('.manual-instructions').addClass('hidden');
    });

    jQuery('#pay_btn').on('click', function (e) {
        sendMpesaSTKRequest();
    });


    function sendMpesaSTKRequest() {
        const statusDisplay = jQuery('#commonname');

        const orderId = jQuery('#order_id').val();
        const phoneNumber = jQuery('#phone_number').val();
        const mpesaRef = jQuery('#mpesa_ref').val();
        const amountPaid = jQuery('#amount').val();
        const mode = jQuery('#payment_mode').val();
        const redirectUrl = jQuery('#redirect_url').val();

        const urlParam = {
            order_id: orderId,
            phone_number: phoneNumber,
            mpesa_ref: mpesaRef,
            amount_paid: amountPaid,
            mode: mode,
            redirect_url: redirectUrl,
            action: 'process_mpesa'
        };

        console.log(urlParam);
        jQuery.ajax({
            type: 'POST',
            url: ajaxMpesaCheckout.ajaxurl,
            dataType: "json",
            data: urlParam,
            success: function (data, textStatus, XMLHttpRequest) {
                processResponse(data);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log(XMLHttpRequest);
                console.log(errorThrown);
            }
        });
    }


    function processResponse(data) {
        const statusDisplay = jQuery('#commonname');
        if (data.respCode === 0) {
            statusDisplay.addClass('final_success').removeClass('error')
            window.location = data.checkout;
        } else {
            statusDisplay.addClass('error').removeClass('final_success')
        }
        statusDisplay.html(data.resp);
    }
});