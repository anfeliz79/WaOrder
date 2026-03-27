<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverAppController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\MenuWebController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PublicOrderStatusController;
use App\Http\Controllers\Api\MobileAppController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

// Public endpoints (rate limited)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/public/order-status/{orderNumber}', [PublicOrderStatusController::class, 'show']);

    // Public web menu API (accessed from WhatsApp CTA links)
    Route::get('/public/menu/{token}', [MenuWebController::class, 'showItem']);
    Route::get('/public/menu/{token}/full', [MenuWebController::class, 'showFullMenu']);
    Route::post('/public/menu/{token}/add', [MenuWebController::class, 'addToCart']);
});

// Driver App - Linking (unauthenticated, rate limited)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/driver-app/link', [DriverAppController::class, 'link']);
});

// Driver App - Authenticated endpoints
Route::middleware(['auth:driver', \App\Http\Middleware\IdentifyTenantFromDriver::class])
    ->prefix('driver-app')
    ->group(function () {
        Route::delete('/unlink', [DriverAppController::class, 'unlink']);
        Route::get('/profile', [DriverAppController::class, 'profile']);
        Route::post('/availability', [DriverAppController::class, 'toggleAvailability']);
        Route::put('/push-token', [DriverAppController::class, 'updatePushToken']);
        Route::get('/orders', [DriverAppController::class, 'orders']);
        Route::get('/orders/history', [DriverAppController::class, 'orderHistory']);
        Route::get('/orders/{order}', [DriverAppController::class, 'orderDetail']);
        Route::post('/orders/{order}/delivered', [DriverAppController::class, 'markDelivered']);
    });

// Authenticated API endpoints
Route::middleware(['auth:web', \App\Http\Middleware\IdentifyTenant::class])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'index']);
    Route::get('/dashboard/orders/live', [DashboardController::class, 'liveOrders']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('/orders/{order}/history', [OrderController::class, 'history']);
    Route::post('/orders/{order}/assign-driver', [OrderController::class, 'assignDriver']);

    // Menu Categories
    Route::get('/menu/categories', [MenuCategoryController::class, 'index']);
    Route::post('/menu/categories', [MenuCategoryController::class, 'store']);
    Route::put('/menu/categories/{category}', [MenuCategoryController::class, 'update']);
    Route::delete('/menu/categories/{category}', [MenuCategoryController::class, 'destroy']);

    // Menu Items
    Route::get('/menu/items', [MenuItemController::class, 'index']);
    Route::post('/menu/items', [MenuItemController::class, 'store']);
    Route::put('/menu/items/{item}', [MenuItemController::class, 'update']);
    Route::patch('/menu/items/{item}/availability', [MenuItemController::class, 'toggleAvailability']);
    Route::delete('/menu/items/{item}', [MenuItemController::class, 'destroy']);

    // Customers
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);

    // Drivers
    Route::get('/drivers', [DriverController::class, 'index']);
    Route::post('/drivers', [DriverController::class, 'store']);
    Route::put('/drivers/{driver}', [DriverController::class, 'update']);
    Route::delete('/drivers/{driver}', [DriverController::class, 'destroy']);
    Route::patch('/drivers/{driver}/availability', [DriverController::class, 'toggleAvailability']);
    Route::post('/drivers/{driver}/qr-token', [DriverController::class, 'generateQrToken']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/test-whatsapp', [SettingsController::class, 'testWhatsApp']);

    // Mobile App Build Management
    Route::get('/mobile-app/builds', [MobileAppController::class, 'builds']);
    Route::post('/mobile-app/builds/trigger', [MobileAppController::class, 'trigger']);
});
