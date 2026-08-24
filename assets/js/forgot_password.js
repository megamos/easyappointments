// NOTE: forgot_password.js and forgot_password.min.js are kept byte-identical on purpose. The
// .min.js twin is the file actually served (DEBUG_MODE = FALSE), and hand-minifying this by hand
// would be pure risk for 1.6 KB of savings. Edit both, or neither.

$(function () {
    'use strict';

    var $form = $('form');

    /**
     * Event: Form "Submit"
     *
     * Ask the server for a new password and report back in place. Never navigate away, and never
     * say anything that would reveal whether the account exists.
     */
    function onFormSubmit(event) {
        event.preventDefault();

        var url = GlobalVariables.baseUrl + '/index.php/user/ajax_forgot_password';

        var data = {
            'csrfToken': GlobalVariables.csrfToken,
            'username': $('#username').val(),
            'email': $('#email').val()
        };

        var $alert = $('.alert');
        var $button = $('#get-new-password');

        $alert.addClass('d-none').removeClass('alert-danger alert-success');
        $button.prop('disabled', true);

        $.post(url, data)
            .done(function (response) {
                if (response === GlobalVariables.AJAX_SUCCESS) {
                    $alert
                        .removeClass('d-none alert-danger')
                        .addClass('alert-success')
                        .text(EALang['new_password_sent_with_email']);
                    $('#username').val('');
                    $('#email').val('');
                } else {
                    $alert
                        .removeClass('d-none alert-success')
                        .addClass('alert-danger')
                        .text(EALang['type_username_and_email_for_new_password']);
                }
            })
            .fail(function () {
                $alert
                    .removeClass('d-none alert-success')
                    .addClass('alert-danger')
                    .text(EALang['service_communication_error']);
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    }

    $form.on('submit', onFormSubmit);
});
