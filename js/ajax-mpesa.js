"use strict";


jQuery(document).ready(function () {

    jQuery(function (e) {
        jQuery(".phone").mask("254999999999", {placeholder: ""});
        jQuery(".amount").mask("9?999999999", {placeholder: ""});
        //jQuery("#email").mask("9?99999999", {placeholder: ""});

        jQuery('.alpha-no-spaces').mask("A", {
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
        const formData = jQuery('#mpesa-form').serialize();


        jQuery.ajax({
            type: 'POST',
            url: mpesaAjax.ajaxUrl,
            dataType: "json",
            data: formData,
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
        jQuery(id).append(data);
        console.log(data);
    }
});