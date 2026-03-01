$(document).ready(function () {

    // Form  validation
    $.validate({
        modules: 'file,sanitize',
        validateOnBlur: false,
        form: '.login_form',
        inputParentClassOnError: 'has-danger',
        errorMessageClass: 'alert-danger',
        onError: function ($form) {
            return false;
        },
        onSuccess: function ($form) {
            $('.submit_button').attr('disabled', 'disabled');
            login();

            return false;
        }
    });

    // submit form
    function login() {

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // NO AJAX - just redirect based on URL immediately
        var path = window.location.pathname.toLowerCase();
        var urlStr = window.location.href.toLowerCase();

        if (path.indexOf('company-login') > -1 || urlStr.indexOf('company-login') > -1) {
            // This is company login page - submit via AJAX but we'll redirect anyway
            var values = $('.login_form').serializeArray();
            $.ajax({
                url: "api/spa/login",
                type: "post",
                data: values,
                success: function (response) {
                    // Small delay to ensure session is written before redirect
                    setTimeout(function () {
                        // Check if the response contains redirect info from server
                        if (response.redirect) {
                            // Add cache-busting parameter
                            var redirectUrl = response.redirect + (response.redirect.indexOf('?') > -1 ? '&' : '?') + '_ts=' + Date.now();
                            window.location.href = redirectUrl;
                        } else {
                            // Default to company dashboard for company login
                            window.location.href = 'company/dashboard?_ts=' + Date.now();
                        }
                    }, 100);
                },
                error: function (response) {
                    // Handle undefined errors gracefully
                    var errors = null;
                    try {
                        errors = response.responseJSON ? response.responseJSON.errors : null;
                    } catch (e) {
                        errors = null;
                    }

                    if (errors) {
                        printErrorMsg(errors);
                    } else {
                        // Handle non-validation errors (like server errors without validation messages)
                        var errorMessage = 'An error occurred. Please try again.';
                        try {
                            if (response.responseJSON && response.responseJSON.message) {
                                errorMessage = response.responseJSON.message;
                            } else if (response.status === 0) {
                                errorMessage = 'Network error. Please check your connection.';
                            } else if (response.status === 500) {
                                errorMessage = 'Server error. Please try again later.';
                            }
                        } catch (e) { }
                        printErrorMsg({ 0: errorMessage });
                    }
                }
            });
        } else {
            // Regular admin login
            var values = $('.login_form').serializeArray();
            $.ajax({
                url: "api/spa/login",
                type: "post",
                data: values,
                success: function (response) {
                    // Small delay to ensure session is written before redirect
                    setTimeout(function () {
                        // Add cache-busting parameter to prevent stale dashboard
                        window.location.href = 'dashboard?_ts=' + Date.now();
                    }, 100);
                },
                error: function (response) {
                    // Handle undefined errors gracefully
                    var errors = null;
                    try {
                        errors = response.responseJSON ? response.responseJSON.errors : null;
                    } catch (e) {
                        errors = null;
                    }

                    if (errors) {
                        printErrorMsg(errors);
                    } else {
                        // Handle non-validation errors (like server errors without validation messages)
                        var errorMessage = 'An error occurred. Please try again.';
                        try {
                            if (response.responseJSON && response.responseJSON.message) {
                                errorMessage = response.responseJSON.message;
                            } else if (response.status === 0) {
                                errorMessage = 'Network error. Please check your connection.';
                            } else if (response.status === 500) {
                                errorMessage = 'Server error. Please try again later.';
                            }
                        } catch (e) { }
                        printErrorMsg({ 0: errorMessage });
                    }
                }
            });
        }
    }

    function printErrorMsg(msg) {
        $(".print-error-msg").find("ul").html('');
        $(".print-error-msg").css('display', 'block');
        $.each(msg, function (key, value) {
            $(".print-error-msg").find("ul").append('<li>' + value + '</li>');
            $('.submit_button').removeAttr('disabled');
        });
    }


});
