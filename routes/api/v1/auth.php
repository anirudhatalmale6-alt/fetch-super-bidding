<?php

/*
|--------------------------------------------------------------------------
| Auth API Routes
|--------------------------------------------------------------------------
|
| These routes are prefixed with 'api/v1'.
| These routes use the root namespace 'App\Http\Controllers\Api\V1'.
|
 */

/*
 * These routes use the root namespace 'App\Http\Controllers\Api\V1\Auth'.
 */
Route::namespace('Auth')->group(function () {

    // ── OTP endpoints — max 5 attempts per minute per IP ─────────────────
    Route::middleware('throttle:otp')->group(function () {
        Route::post('user/login/send-otp', 'LoginController@sendUserLoginOTP');
        Route::post('mobile-otp',          'LoginController@mobileOtp');
        Route::post('validate-otp',        'LoginController@validateSmsOtp');
        Route::post('user/register/send-otp', 'Registration\UserRegistrationController@sendOTP');
        Route::post('send-mail-otp',       'Registration\UserRegistrationController@sendMailOTP');
    });

    Route::post('social-auth/{provider}', 'LoginController@socialAuth');

    // ── Login endpoints — max 10 attempts per minute per IP (brute-force) ─
    Route::middleware('throttle:auth')->group(function () {
        Route::post('user/login',          'LoginController@loginUser');
        Route::post('driver/login',        'LoginController@loginDriver');
        Route::post('admin/login',         'LoginController@loginAdmin');
        Route::post('dispatcher/login',    'LoginController@loginDispatcher');
    });

    // Logout the user by revoking the access token.
    Route::post('logout', 'LoginController@logout')->middleware('auth');
    // Reset password
    Route::post('reset-password', 'Password\PasswordResetController@validateUserMobileIsExistForForgetPassword');

    /**
     * Root namespace 'App\Http\Controllers\Api\V1\Auth\Registration'.
     */
    Route::namespace('Registration')->group(function () {

        // Register a normal user.
        Route::post('user/register', 'UserRegistrationController@register');
        Route::post('user/validate-mobile', 'UserRegistrationController@validateUserMobile');
        Route::post('user/validate-mobile-for-login', 'UserRegistrationController@validateUserMobileForLogin');
//Reset Password
        Route::post('user/update-password', 'UserRegistrationController@updatePassword');     
        Route::post('driver/update-password', 'DriverSignupController@updatePassword');   
        // Register Driver
        Route::post('driver/register', 'DriverSignupController@register');
        Route::post('driver/validate-mobile', 'DriverSignupController@validateDriverMobile');
        Route::post('driver/validate-mobile-for-login', 'DriverSignupController@validateDriverMobileForLogin');
        // user/register/send-otp is now handled under throttle:otp group above

        // Owner Register
        Route::post('owner/register','DriverSignupController@ownerRegister');
        // Update Referral code after signup
        Route::post('update/user/referral', 'ReferralController@updateUserReferral')->middleware('auth');
        Route::post('update/driver/referral', 'ReferralController@updateDriverReferral')->middleware('auth');
        // Get Referral code
        Route::get('get/referral', 'ReferralController@index')->middleware('auth');
        // send-mail-otp is now handled under throttle:otp group above
        Route::post('sendmail', 'EmailSendController@ridedetails');
        // Validate the email registration OTP.
        Route::post('validate-email-otp', 'UserRegistrationController@validateEmailOTP');

        // Validate the registration OTP.
        Route::post('user/register/validate-otp', 'UserRegistrationController@validateOTP');
        // Register Admin user
        Route::post('admin/register', 'AdminRegistrationController@register');
    });

    // Confirm user's email.
    Route::post('email/confirm', 'Email\EmailConfirmationController@confirm');

    // Resend user's email address confirmation email.
    Route::post('email/resend-confirmation', 'Email\EmailConfirmationController@resend');

    /**
     * These routes are prefixed with 'api/v1/password'.
     * Root namespace 'App\Http\Controllers\Api\V1\Auth\Password'.
     */
    Route::prefix('password')->namespace('Password')->group(function () {

        // Send the password reset email.
        Route::post('forgot', 'PasswordResetController@forgotPassword');

        // Validate the password reset token.
        Route::post('validate-token', 'PasswordResetController@validateToken');

        // Reset (update) the user's password.
        Route::post('reset', 'PasswordResetController@reset');
    });
});
