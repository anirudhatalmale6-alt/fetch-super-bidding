_<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Company/Fleet Owner Routes (Includes Trucking Companies)
|--------------------------------------------------------------------------
|
| These routes handle the fleet owner dashboard, which includes both
| regular fleet owners (taxi) and trucking companies (interstate logistics).
| Trucking companies have access to the shop for purchasing products.
|
*/

Route::middleware(['auth:web', 'role:trucking_company|fleet_owner|owner'])
    ->prefix('company')
    ->name('company.')
    ->namespace('Company')
    ->group(function () {
        
        // ==========================================
        // DASHBOARD
        // ==========================================
        Route::get('/dashboard', 'DashboardController@index')->name('dashboard');
        Route::post('/logout', function () {
            auth('web')->logout();
            request()->session()->invalidate();
            return redirect('/company-login');
        })->name('logout');
        
        // ==========================================
        // PACKAGE MANAGEMENT (Goods Controller - from accepted bids)
        // ==========================================
        Route::get('/packages', 'PackageController@index')->name('packages.index');
        Route::get('/packages/{id}', 'PackageController@show')->name('packages.show');
        Route::put('/packages/{id}', 'PackageController@update')->name('packages.update');
        
        // ==========================================
        // GOODS MANAGEMENT (Legacy Interstate Logistics)
        // ==========================================
        Route::get('/goods', 'GoodsController@index')->name('goods.index');
        Route::get('/goods/pending', 'GoodsController@pendingPricing')->name('goods.pending');
        Route::get('/goods/{id}/pricing', 'GoodsController@editPricing')->name('goods.pricing');
        Route::post('/goods/{id}/pricing', 'GoodsController@savePricing')->name('goods.save-pricing');
        Route::post('/goods/bulk-pricing', 'GoodsController@bulkPricing')->name('goods.bulk-pricing');
        Route::get('/goods/{id}', 'GoodsController@show')->name('goods.show');
        Route::post('/goods/{id}/status', 'GoodsController@updateStatus')->name('goods.status');
        Route::post('/goods/{id}/status-update', 'GoodsController@addStatusUpdate')->name('goods.status-update');
        Route::post('/goods/{id}/fees', 'GoodsController@saveFees')->name('goods.fees');
        
        // ==========================================
        // SHOP SECTION (For Trucking Companies) - Ecommerce
        // ==========================================
        // Main shop route - uses company shop with video slider
        Route::get('/shop', 'CompanyShopController@index')->name('shop.index');
        Route::post('/shop/cart/add', 'CompanyShopController@addToCart')->name('shop.cart.add');
        Route::get('/shop/cart', 'CompanyShopController@cart')->name('shop.cart');
        Route::get('/shop/checkout', 'CompanyShopController@checkout')->name('shop.checkout');
        Route::post('/shop/checkout', 'CompanyShopController@processCheckout')->name('shop.process-checkout');
        Route::get('/shop/orders', 'CompanyShopController@orders')->name('shop.orders');
        Route::get('/shop/orders/{id}', 'CompanyShopController@orderDetail')->name('shop.orders.detail');
        
        // Cart AJAX endpoints
        Route::post('/shop/cart/add', 'CompanyShopController@addToCart')->name('shop.cart.add');
        
        // ==========================================
        // PROFILE MANAGEMENT
        // ==========================================
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', 'ProfileController@edit')->name('index');
            Route::get('/edit', 'ProfileController@edit')->name('edit');
            Route::post('/', 'ProfileController@update')->name('update');
            Route::get('/change-password', 'ProfileController@changePassword')->name('changePassword');
            Route::post('/change-password', 'ProfileController@updatePassword')->name('updatePassword');
            Route::get('/documents', 'ProfileController@documents')->name('documents');
            Route::post('/documents', 'ProfileController@uploadDocument')->name('uploadDocument');
            Route::get('/settings', 'ProfileController@settings')->name('settings');
            Route::post('/settings', 'ProfileController@updateSettings')->name('updateSettings');
        });
        
        // ==========================================
        // NOTIFICATIONS
        // ==========================================
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', 'NotificationController@index')->name('index');
            Route::get('/unread-count', 'NotificationController@unreadCount')->name('unread-count');
            Route::get('/recent', 'NotificationController@recent')->name('recent');
            Route::post('/mark-all-read', 'NotificationController@markAllAsRead')->name('markAllAsRead');
            Route::post('/{id}/mark-read', 'NotificationController@markAsRead')->name('markAsRead');
            Route::delete('/{id}', 'NotificationController@destroy')->name('destroy');
            Route::get('/preferences', 'NotificationController@preferences')->name('preferences');
            Route::post('/preferences', 'NotificationController@updatePreferences')->name('updatePreferences');
        });
        
        // ==========================================
        // BIDDING MODULE (Interstate Trucking Companies)
        // ==========================================
        Route::prefix('bids')->name('bids.')->group(function () {
            Route::get('/', 'CompanyBidController@index')->name('index');
            Route::get('/active', 'CompanyBidController@index')->name('active');
            Route::get('/available', 'CompanyBidController@available')->name('available');
            Route::get('/create/{id}', 'CompanyBidController@create')->name('create');
            Route::post('/submit', 'CompanyBidController@submit')->name('submit');
            Route::get('/history', 'CompanyBidController@history')->name('history');
            Route::get('/{id}', 'CompanyBidController@show')->name('show');
            Route::get('/{id}/edit', 'CompanyBidController@edit')->name('edit');
            Route::put('/{id}', 'CompanyBidController@update')->name('update');
            Route::post('/{id}/withdraw', 'CompanyBidController@withdraw')->name('withdraw');
        });
        
        // ==========================================
        // DELIVERY LEGS MANAGEMENT (Interstate)
        // ==========================================
        Route::prefix('deliveries')->name('deliveries.')->group(function () {
            Route::get('/', 'CompanyDeliveryController@index')->name('index');
            Route::get('/{id}', 'CompanyDeliveryController@show')->name('show');
            Route::post('/{id}/accept', 'CompanyDeliveryController@accept')->name('accept');
            Route::post('/{id}/status', 'CompanyDeliveryController@updateStatus')->name('update-status');
        });

        // ==========================================
        // BANNERS/SLIDER (Company Dashboard)
        // ==========================================
        Route::prefix('banners')->name('banners.')->group(function () {
            Route::get('/', 'BannerController@index')->name('index');
            Route::get('/display', 'BannerController@display')->name('display');
        });
        
        // ==========================================
        // HUB MANAGEMENT
        // ==========================================
        Route::prefix('hubs')->name('hubs.')->group(function () {
            Route::get('/', 'HubController@index')->name('index');
            Route::get('/create', 'HubController@create')->name('create');
            Route::post('/', 'HubController@store')->name('store');
            Route::get('/{id}/edit', 'HubController@edit')->name('edit');
            Route::put('/{id}', 'HubController@update')->name('update');
            Route::delete('/{id}', 'HubController@destroy')->name('destroy');
        });
        
        // ==========================================
        // ROUTE MANAGEMENT
        // ==========================================
        Route::prefix('routes')->name('routes.')->group(function () {
            Route::get('/', 'RouteController@index')->name('index');
            Route::get('/create', 'RouteController@create')->name('create');
            Route::post('/', 'RouteController@store')->name('store');
            Route::get('/{id}/edit', 'RouteController@edit')->name('edit');
            Route::put('/{id}', 'RouteController@update')->name('update');
            Route::delete('/{id}', 'RouteController@destroy')->name('destroy');
        });
    });
