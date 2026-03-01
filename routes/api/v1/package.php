<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Package API Routes
|--------------------------------------------------------------------------
|
| These routes handle package management for users to track their
| logistics packages, view costs, and make payments.
|
| Packages are separate from Shop orders and Goods.
|
*/

Route::namespace('Package')->middleware('auth')->group(function () {

    // User Package Management
    Route::prefix('packages')->group(function () {
        
        // List all packages
        Route::get('/', 'PackageController@index');
        
        // Get package statistics
        Route::get('/statistics', 'PackageController@statistics');
        
        // Get specific package details
        Route::get('/{goodsId}', 'PackageController@show');
        
        // Get tracking timeline
        Route::get('/{goodsId}/tracking', 'PackageController@tracking');
        
        // Get payment summary
        Route::get('/{goodsId}/payment', 'PackageController@paymentSummary');
        
        // Initiate payment
        Route::post('/{goodsId}/payment/initiate', 'PackageController@initiatePayment');
        
        // Confirm payment
        Route::post('/payment/confirm', 'PackageController@confirmPayment');
    });
});
