<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Interstate Freight API Routes
|--------------------------------------------------------------------------
|
| These routes handle dimensional freight pricing and interstate
| delivery operations for the Tagxi platform.
|
*/

Route::prefix('interstate')->namespace('Interstate')->group(function () {
    
    // Public/Authenticated routes
    Route::middleware('auth')->group(function () {

        // Company Selection (V2 flow - user picks company, no upfront bidding)
        Route::get('/companies', 'CompanyListController@getAvailableCompanies');

        // Freight Calculation & Quotes
        Route::post('/freight/quote', 'FreightCalculationController@calculateQuote');
        Route::post('/freight/validate', 'FreightCalculationController@validatePackages');
        Route::post('/freight/calculate-volumetric', 'FreightCalculationController@calculateVolumetric');
        Route::get('/freight/routes', 'FreightCalculationController@getAvailableRoutes');
        
        // Interstate Delivery Requests
        Route::post('/delivery/request', 'InterstateDeliveryController@createRequest');
        Route::get('/delivery/requests', 'InterstateDeliveryController@getUserRequests');
        Route::get('/delivery/tracking/{requestNumber}', 'InterstateDeliveryController@getTracking');
        Route::get('/delivery/requests/{requestId}', 'InterstateDeliveryController@getRequestDetails');
        Route::post('/delivery/cancel/{requestId}', 'InterstateDeliveryController@cancelRequest');
        
        // Interstate Bidding Routes (User Side)
        Route::get('/bids/request/{requestId}', 'InterstateBiddingController@getBidsForRequest');
        Route::post('/bids/accept/{bidId}', 'InterstateBiddingController@acceptBid');
        
        // Payment Routes
        Route::get('/payment/status/{requestId}', 'PaymentController@getPaymentStatus');
        Route::get('/payment/summary/{requestId}', 'PaymentController@getPaymentSummary');
        Route::post('/payment/initiate/{requestId}/{legNumber}', 'PaymentController@initiatePayment');
        Route::post('/payment/confirm', 'PaymentController@confirmPayment');
    });
    
    // Trucking Company Routes (requires trucking_company role)
    Route::middleware(['auth', 'role:trucking_company'])->prefix('trucking')->group(function () {
        
        // Bidding Routes (Company Side)
        Route::get('/bids', 'InterstateBiddingController@getCompanyBids');
        Route::post('/bids/submit', 'InterstateBiddingController@submitBid');
        Route::post('/bids/update/{bidId}', 'InterstateBiddingController@updateBid');
        Route::post('/bids/withdraw/{bidId}', 'InterstateBiddingController@withdrawBid');
        
        // Dashboard & Operations
        Route::get('/dashboard', 'TruckingCompanyController@dashboard');
        Route::get('/profile', 'TruckingCompanyController@getProfile');
        Route::post('/profile', 'TruckingCompanyController@updateProfile');
        
        // Hub Management
        Route::get('/hubs', 'TruckingCompanyController@getHubs');
        Route::get('/hubs/{hubId}', 'TruckingCompanyController@getHubDetails');
        Route::get('/hubs/{hubId}/inventory', 'TruckingCompanyController@getHubInventory');
        Route::post('/hubs/{hubId}/check-in', 'TruckingCompanyController@checkInPackage');
        Route::post('/hubs/{hubId}/check-out', 'TruckingCompanyController@checkOutPackage');
        
        // Route Management
        Route::get('/routes', 'TruckingCompanyController@getRoutes');
        Route::get('/routes/{routeId}', 'TruckingCompanyController@getRouteDetails');
        Route::post('/routes/{routeId}/pricing', 'TruckingCompanyController@updateRoutePricing');
        
        // Delivery Legs (Interstate Transport)
        Route::get('/legs/pending', 'TruckingCompanyController@getPendingLegs');
        Route::get('/legs/active', 'TruckingCompanyController@getActiveLegs');
        Route::get('/legs/completed', 'TruckingCompanyController@getCompletedLegs');
        Route::post('/legs/{legId}/accept', 'TruckingCompanyController@acceptLeg');
        Route::post('/legs/{legId}/pickup', 'TruckingCompanyController@markPickedUp');
        Route::post('/legs/{legId}/in-transit', 'TruckingCompanyController@markInTransit');
        Route::post('/legs/{legId}/arrived', 'TruckingCompanyController@markArrived');
        Route::post('/legs/{legId}/complete', 'TruckingCompanyController@markComplete');
        Route::post('/legs/{legId}/update-location', 'TruckingCompanyController@updateLocation');
        
        // Documents & Proof
        Route::post('/legs/{legId}/upload-proof', 'TruckingCompanyController@uploadProof');
        Route::get('/legs/{legId}/documents', 'TruckingCompanyController@getDocuments');
        
        // Analytics & Reports
        Route::get('/analytics/summary', 'TruckingCompanyController@getAnalyticsSummary');
        Route::get('/analytics/shipments', 'TruckingCompanyController@getShipmentReport');
        Route::get('/analytics/revenue', 'TruckingCompanyController@getRevenueReport');
        
        // === INSPECTION & FINAL COST FLOW ===
        // Goods Intake
        Route::post('/goods-intake/search', 'InspectionController@searchForIntake');
        
        // Inspection Process
        Route::post('/inspection/start/{requestId}', 'InspectionController@startInspection');
        Route::post('/inspection/photo', 'InspectionController@uploadInspectionPhoto');
        Route::post('/inspection/measurements', 'InspectionController@submitFinalMeasurements');
        Route::post('/inspection/final-cost', 'InspectionController@submitFinalCost');
        Route::get('/inspection/{requestId}', 'InspectionController@getInspectionDetails');
        
        // Tracking Updates
        Route::post('/tracking/update', 'TrackingController@addTrackingUpdate');
    });
    
    // Final Cost Approval Routes (User Side)
    Route::middleware('auth')->group(function () {
        Route::get('/final-cost/{requestId}', 'FinalCostController@getFinalCostDetails');
        Route::post('/final-cost/accept/{requestId}', 'FinalCostController@acceptFinalCost');
        Route::post('/final-cost/reject-reroute/{requestId}', 'FinalCostController@rejectAndReroute');
        Route::post('/final-cost/cancel/{requestId}', 'FinalCostController@cancelShipment');
        
        // Tracking Routes (User Side)
        Route::get('/tracking/{requestId}', 'TrackingController@getTrackingUpdates');
        Route::get('/timeline/{requestId}', 'TrackingController@getTimeline');
    });
    
    // Driver Routes (for local pickup/delivery legs)
    Route::middleware(['auth', 'role:driver'])->prefix('driver')->group(function () {
        
        // Interstate Legs assigned to driver
        Route::get('/interstate-legs', 'DriverInterstateController@getAssignedLegs');
        Route::get('/interstate-legs/{legId}', 'DriverInterstateController@getLegDetails');
        
        // Leg Actions
        Route::post('/interstate-legs/{legId}/accept', 'DriverInterstateController@acceptLeg');
        Route::post('/interstate-legs/{legId}/arrived', 'DriverInterstateController@markArrived');
        Route::post('/interstate-legs/{legId}/picked-up', 'DriverInterstateController@markPickedUp');
        Route::post('/interstate-legs/{legId}/complete', 'DriverInterstateController@markComplete');
        
        // Hub Handoff
        Route::post('/interstate-legs/{legId}/hub-handoff', 'DriverInterstateController@processHubHandoff');
        Route::post('/interstate-legs/{legId}/hub-pickup', 'DriverInterstateController@processHubPickup');
        
        // Documents
        Route::post('/interstate-legs/{legId}/upload-proof', 'DriverInterstateController@uploadProof');
    });
    
});
