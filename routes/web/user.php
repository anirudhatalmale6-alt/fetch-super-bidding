<?php

/*
|--------------------------------------------------------------------------
| User Routes
|--------------------------------------------------------------------------
|
| These Routes are for user web booking and tracking
|
 */

Route::prefix('web-user')->namespace ('User')->group(function () {

    // Route::get('login', 'WebUserControllerController@viewLogin');

});

/*
|--------------------------------------------------------------------------
| Interstate Delivery Tracking Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('tracking')->name('tracking.')->group(function () {
    
    // Leg tracking for interstate delivery
    Route::get('/request/{requestNumber}/legs', 'Booking\TrackingController@trackLegs')
        ->name('request.legs');
    
    Route::get('/leg/{legId}/pay', 'Booking\TrackingController@payForLeg')
        ->name('leg.pay');
    
    Route::get('/request/{requestNumber}/current-leg', 'Booking\TrackingController@getCurrentLeg')
        ->name('request.current-leg');
    
});

