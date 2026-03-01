<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop API Routes
|--------------------------------------------------------------------------
|
| These routes handle the e-commerce shop functionality for companies
| to browse and purchase products from Super Admin.
|
*/

// Public shop routes (no authentication required)
Route::prefix('shop')->group(function () {
    
    // Categories
    Route::get('/categories', 'Shop\CategoryController@index');
    Route::get('/categories/{id}', 'Shop\CategoryController@show');
    
    // Products
    Route::get('/products', 'Shop\ProductController@index');
    Route::get('/products/featured', 'Shop\ProductController@featured');
    Route::get('/products/{id}', 'Shop\ProductController@show');
    
});

// Sliders (public)
Route::get('/sliders', 'SliderController@index');
Route::get('/sliders/homepage', 'SliderController@homepage');
Route::get('/sliders/shop', 'SliderController@shop');
Route::get('/sliders/company-dashboard', 'SliderController@companyDashboard');

// Protected shop routes (company authentication required)
Route::middleware(['auth'])->prefix('shop')->group(function () {
    
    // Cart
    Route::get('/cart', 'Shop\CartController@index');
    Route::post('/cart', 'Shop\CartController@store');
    Route::put('/cart/{id}', 'Shop\CartController@update');
    Route::delete('/cart/{id}', 'Shop\CartController@destroy');
    Route::delete('/cart', 'Shop\CartController@clear');
    
    // Delivery Options
    Route::get('/delivery-options', 'Shop\OrderController@getDeliveryOptions');
    
    // Orders
    Route::get('/orders', 'Shop\OrderController@index');
    Route::get('/orders/{id}', 'Shop\OrderController@show');
    Route::post('/orders', 'Shop\OrderController@store');
    Route::post('/orders/{id}/bank-transfer-proof', 'Shop\OrderController@submitBankTransfer');
    
});
