'use strict'

function ajaxformsendmail(name, email, subject, contents) {

    var formData = {
        action: 'ajaxcontact_send_mail',
        acfname: name,
        acfemail: email,
        acfsubject: subject,
        acfcontents: contents

    }
    jQuery.ajax({
        type: 'POST',
        url: ajaxcontactajax.ajaxurl,
        dataType: "json",
        // contentType: "json",
        data: formData,
        success: function (data, textStatus, XMLHttpRequest) {
            var id = '#ajaxcontact-response';
            jQuery(id).html('');
            jQuery(id).append(data.resp);
            console.log(data.resp);
        },
        error: function (MLHttpRequest, textStatus, errorThrown) {
            alert(errorThrown);
        }
    });
}