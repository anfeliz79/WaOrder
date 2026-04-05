<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\MenuPageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SetupController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\SuperAdmin\BankAccountController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\PlanController;
use App\Http\Controllers\SuperAdmin\SettingsController as SuperAdminSettingsController;
use App\Http\Controllers\SuperAdmin\SubscriptionController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\SuperAdmin\TransferVerificationController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\RegistrationBankTransferController;
use App\Http\Controllers\Auth\RegistrationPaymentController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OrderConsoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing page (public)
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Payment pages (public, no auth)
Route::get('/pay/{uuid}', [\App\Http\Controllers\Payment\CardnetPaymentController::class, 'show']);
Route::get('/pay/{uuid}/success', [\App\Http\Controllers\Payment\CardnetPaymentController::class, 'success']);
Route::get('/pay/{uuid}/cancel', [\App\Http\Controllers\Payment\CardnetPaymentController::class, 'cancel']);

// Public menu pages (no auth required - accessed from WhatsApp)
Route::get('/m/{token}', fn (string $token) => view('menu-item', ['token' => $token]));
Route::get('/menu/{token}', fn (string $token) => view('menu-browse', ['token' => $token]));

// Auth routes
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::get('/register', [RegisterController::class, 'showForm'])->name('register')->middleware('guest');
Route::post('/register', [RegisterController::class, 'register'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::post('/impersonation/leave', [ImpersonationController::class, 'leave'])->middleware('auth')->name('impersonation.leave');

// Registration payment step (auth required, no setup check)
Route::middleware(['auth', \App\Http\Middleware\IdentifyTenant::class])->group(function () {
    Route::get('/register/payment', [RegistrationPaymentController::class, 'show']);
    Route::post('/register/payment/tokenize', [RegistrationPaymentController::class, 'tokenize']);

    // Bank transfer payment path
    Route::post('/register/bank-transfer', [RegistrationBankTransferController::class, 'submit']);
    Route::get('/register/bank-transfer/pending', [RegistrationBankTransferController::class, 'pending']);
});

// Branch selection (after login, before full admin access)
Route::middleware(['auth', \App\Http\Middleware\IdentifyTenant::class])->group(function () {
    Route::get('/select-branch', [AuthController::class, 'showSelectBranch']);
    Route::post('/select-branch', [AuthController::class, 'selectBranch']);
    Route::post('/switch-branch', [AuthController::class, 'switchBranch']);
});

// Order Console (accessible by admin, gestor, order_taker)
Route::middleware(['auth', \App\Http\Middleware\IdentifyTenant::class, \App\Http\Middleware\EnsureSetupComplete::class])->group(function () {
    Route::get('/console', [OrderConsoleController::class, 'index']);

    // Order actions (shared between admin panel and console)
    Route::get('/orders/latest-id', [OrderController::class, 'latestId']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/assign-driver', [OrderController::class, 'assignDriver']);
    Route::patch('/orders/{order}/delivery-address', [OrderController::class, 'updateDeliveryAddress']);
    Route::post('/orders/{order}/send-to-driver', [OrderController::class, 'sendToDriver']);
});

// Panel (requires auth + tenant) — accessible by admin + gestor (NOT order_taker)
Route::middleware(['auth', \App\Http\Middleware\IdentifyTenant::class, \App\Http\Middleware\EnsureSetupComplete::class, 'not_order_taker'])->group(function () {
    // Root redirect for authenticated users (landing handles guest → blade view)
    Route::get('/home', fn () => redirect('/dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Orders (admin + gestor)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // Customers (admin + gestor)
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);

    // Billing (admin only, before general admin group)
    Route::middleware('admin')->group(function () {
        Route::get('/billing', [\App\Http\Controllers\Api\BillingController::class, 'index']);
        Route::post('/billing/change-plan', [\App\Http\Controllers\Api\BillingController::class, 'changePlan']);
        Route::post('/billing/cancel', [\App\Http\Controllers\Api\BillingController::class, 'cancel']);
        Route::post('/billing/reactivate', [\App\Http\Controllers\Api\BillingController::class, 'reactivate']);
    });

    // Admin-only routes
    Route::middleware('admin')->group(function () {
        // Setup wizard
        Route::get('/setup', [SetupController::class, 'index'])->name('setup');
        Route::post('/setup/restaurant', [SetupController::class, 'saveRestaurant']);
        Route::post('/setup/whatsapp', [SetupController::class, 'saveWhatsApp']);
        Route::post('/setup/test-whatsapp', [SetupController::class, 'testWhatsApp']);
        Route::post('/setup/menu', [SetupController::class, 'saveMenu']);
        Route::post('/setup/test-menu-api', [SetupController::class, 'testMenuApi']);
        Route::post('/setup/admin', [SetupController::class, 'saveAdmin']);
        Route::post('/setup/complete', [SetupController::class, 'complete']);

        // Menu
        Route::get('/menu', [MenuPageController::class, 'index']);
        Route::post('/menu/categories', [\App\Http\Controllers\Api\MenuCategoryController::class, 'store']);
        Route::put('/menu/categories/{category}', [\App\Http\Controllers\Api\MenuCategoryController::class, 'update']);
        Route::delete('/menu/categories/{category}', [\App\Http\Controllers\Api\MenuCategoryController::class, 'destroy']);
        Route::post('/menu/items', [\App\Http\Controllers\Api\MenuItemController::class, 'store']);
        Route::put('/menu/items/{item}', [\App\Http\Controllers\Api\MenuItemController::class, 'update']);
        Route::patch('/menu/items/{item}/availability', [\App\Http\Controllers\Api\MenuItemController::class, 'toggleAvailability']);
        Route::delete('/menu/items/{item}', [\App\Http\Controllers\Api\MenuItemController::class, 'destroy']);

        // Drivers
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::post('/drivers', [DriverController::class, 'store']);
        Route::put('/drivers/{driver}', [DriverController::class, 'update']);
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy']);
        Route::patch('/drivers/{driver}/availability', [DriverController::class, 'toggleAvailability']);
        Route::post('/drivers/{driver}/qr-token', [DriverController::class, 'generateQrToken']);

        // Branches
        Route::get('/branches', [BranchController::class, 'index']);
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{branch}', [BranchController::class, 'update']);
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Reviews
        Route::get('/reviews', [ReviewController::class, 'index']);

        // Settings
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/test-whatsapp', [SettingsController::class, 'testWhatsApp']);
        Route::post('/settings/test-ai', [SettingsController::class, 'testAi']);
        Route::post('/settings/notification-sound', [SettingsController::class, 'uploadNotificationSound']);
        Route::delete('/settings/notification-sound', [SettingsController::class, 'deleteNotificationSound']);
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo']);
        Route::delete('/settings/logo', [SettingsController::class, 'deleteLogo']);

    });
});

// ── Super Admin Routes ──────────────────────────────────────────────────────
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{id}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/tenants/{id}', [TenantController::class, 'update'])->name('tenants.update');
    Route::post('/tenants/{id}/toggle-active', [TenantController::class, 'toggleActive'])->name('tenants.toggle-active');
    Route::delete('/tenants/{id}', [TenantController::class, 'destroy'])->name('tenants.destroy');
    Route::post('/tenants/{id}/impersonate', [ImpersonationController::class, 'impersonate'])->name('tenants.impersonate');

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('/subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend'])->name('subscriptions.extend');
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('/subscriptions/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])->name('subscriptions.reactivate');

    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{id}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{id}', [PlanController::class, 'update'])->name('plans.update');
    Route::post('/plans/{id}/toggle-active', [PlanController::class, 'toggleActive'])->name('plans.toggle-active');
    Route::delete('/plans/{id}', [PlanController::class, 'destroy'])->name('plans.destroy');

    // Bank accounts (for bank transfer payments)
    Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::put('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
    Route::delete('/bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

    // Transfer verifications
    Route::get('/transfer-verifications', [TransferVerificationController::class, 'index'])->name('transfer-verifications.index');
    Route::get('/transfer-verifications/{transferVerification}', [TransferVerificationController::class, 'show'])->name('transfer-verifications.show');
    Route::post('/transfer-verifications/{transferVerification}/approve', [TransferVerificationController::class, 'approve'])->name('transfer-verifications.approve');
    Route::post('/transfer-verifications/{transferVerification}/reject', [TransferVerificationController::class, 'reject'])->name('transfer-verifications.reject');

    // Settings
    Route::get('/settings', [SuperAdminSettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SuperAdminSettingsController::class, 'update'])->name('settings.update');

    // System management
    Route::get('/system', [SystemController::class, 'index'])->name('system');
    Route::get('/system/status', [SystemController::class, 'status'])->name('system.status');
    Route::get('/system/logs', [SystemController::class, 'logs'])->name('system.logs');
    Route::post('/system/migrate', [SystemController::class, 'migrate'])->name('system.migrate');
    Route::post('/system/cache/clear', [SystemController::class, 'clearCache'])->name('system.cache.clear');
    Route::post('/system/cache/rebuild', [SystemController::class, 'rebuildCache'])->name('system.cache.rebuild');
    Route::post('/system/queue/restart', [SystemController::class, 'restartWorkers'])->name('system.queue.restart');
    Route::post('/system/queue/flush', [SystemController::class, 'clearFailedJobs'])->name('system.queue.flush');
    Route::post('/system/storage/link', [SystemController::class, 'storageLink'])->name('system.storage.link');
});
