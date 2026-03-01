<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Goods (Shipment Tracking) API Routes
|--------------------------------------------------------------------------
|
| Goods = physical shipment payloads being transported.
| These routes contain ZERO ecommerce/shop logic.
|
| Goods ≠ Shop.
|
*/

Route::namespace('Goods')->group(function () {

    // ── Company Side (authenticated trucking company) ──────────────────────
    Route::middleware('auth')->prefix('goods')->group(function () {

        // List all goods assigned to company
        Route::get('/', 'GoodsController@index');

        // Lookup by goods_id (before accepting handover)
        Route::post('/lookup', 'GoodsController@lookup');

        // Company receives goods (confirms handover)
        Route::post('/receive', 'GoodsController@receive');

        // Get full detail + tracking log for one goods item
        Route::get('/{goods_id}', 'GoodsController@show');

        // Add tracking note
        Route::post('/{goods_id}/note', 'GoodsController@addNote');

        // Add transport/insurance/handling costs
        Route::post('/{goods_id}/costs', 'GoodsController@addCosts');

        // Mark as dispatched (sent on to next leg)
        Route::post('/{goods_id}/dispatch', 'GoodsController@dispatchGoods');

        // Mark as delivered
        Route::post('/{goods_id}/deliver', 'GoodsController@markDelivered');
    });

    // ── User Side (authenticated app user) ────────────────────────────────
    Route::middleware('auth')->prefix('user/goods')->group(function () {

        // List all shipments for the user
        Route::get('/', 'GoodsController@userGoodsList');

        // Track a specific shipment by goods_id
        Route::get('/{goods_id}/tracking', 'GoodsController@userTracking');

        // Payment summary + payment legs
        Route::get('/{goods_id}/payment', 'GoodsController@paymentSummary');
    });
});
