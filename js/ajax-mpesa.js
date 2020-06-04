"use strict";


jQuery(document).ready(function ($) {

    jQuery(function (e) {
        jQuery(".phone").mask("254999999999", {placeholder: ""});
        jQuery(".amount").mask("9?999999999", {placeholder: ""});
        //jQuery("#email").mask("9?99999999", {placeholder: ""});

        jQuery('.no-space').mask("A", {
            translation: {
                "A": {pattern: /[\w@\-.+]/, recursive: true}
            }
        });
    });

    jQuery('form').on('submit', function (e) {
        e.preventDefault();
    });

    jQuery('#stk-button').on('click', function (e) {
        sendMpesaSTKRequest();
    });


    function sendMpesaSTKRequest() {
        const formData = jQuery('#mpesa-form').serializeArray();
        // var formData = {
        //     action: 'ajaxcontact_send_mail',
        //     email: "sammy@tsobu.co.ke"
        // };

        formData.push({
            name: 'action', value: 'process_mpesa'
        });

        const urlParam = jQuery.param(formData);

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
        const id = '#ajax-response';

        jQuery(id).html('');
        jQuery(id).append(data.resp);
        console.log(data);
    }
});