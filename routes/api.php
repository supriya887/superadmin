<?php
use App\Http\Controllers\Api\Auth\LoginController as ApiLoginController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\InvoicePaymentController as ApiInvoicePaymentController;
use App\Http\Controllers\Api\PartnerAdminController as ApiPartnerAdminController;
use App\Http\Controllers\Api\DeviceTokenController as ApiDeviceTokenController;
use App\Http\Controllers\Api\RefundController as ApiRefundController;
use App\Http\Controllers\Api\PartnerAuthController as ApiPartnerAuthController;
use App\Http\Controllers\Api\PartnerDomainTransferController as ApiPartnerDomainTransferController;
use App\Http\Controllers\Api\OrderGenerationController;
use App\Http\Controllers\Api\PartnerOrderController as ApiPartnerOrderController;
use App\Http\Controllers\Api\SubscriptionRenewalController as ApiSubscriptionRenewalController;
use App\Http\Controllers\Api\SubscriptionUpgradeController as ApiSubscriptionUpgradeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Home\CommonController;
use App\Http\Controllers\Home\DomainController;
use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Home\LocationController;
use App\Http\Controllers\Home\OrderController;
use App\Http\Controllers\Home\PlanController;
use App\Http\Controllers\Home\RegisterValidationController;
use App\Http\Controllers\Partner\CustomerApi;
use App\Http\Controllers\Partner\PartnerController;
use App\Http\Controllers\SuperAdmin\BillingController as SuperAdminBillingController;
use App\Http\Controllers\Api\WhatsAppDemoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Report;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\SupportInboundMailController;
use App\Http\Controllers\Api\AccountDeletionController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\GlobalSearchController;

Route::prefix('v1')->group(function () {

    // WhatsApp (JMD) — check templates
    Route::match(['get', 'post'], '/whatsapp/templates', [WhatsAppDemoController::class, 'listTemplates'])
        ->name('api.v1.whatsapp.templates');
           // User Auth APIs
    Route::post('/register', [AuthController::class, 'register_validation']);
    Route::post('/login', [AuthController::class, 'loginPost']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/search-gstin', [AuthController::class, 'searchGstinforapi']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logoutPost']);

    });

    //user api
    Route::post('/register-validation', [RegisterValidationController::class, 'register_validation']);
    Route::post('/user-login', [RegisterValidationController::class, 'login']);
    Route::post('/user-login-verify-otp', [RegisterValidationController::class, 'verifyLoginOtp']);

    Route::prefix('partner')->group(function () {
        // --- PUBLIC PARTNER APIs ---
        Route::post('/register', [ApiPartnerAuthController::class, 'register'])->name('api.partner.register');
        Route::post('/resend-registration-otp', [ApiPartnerAuthController::class, 'resendOtp'])->name('api.partner.resend-registration-otp');
        Route::post('/forgot-password', [ApiPartnerAuthController::class, 'forgotPassword'])->name('api.partner.forgot-password');
        Route::post('/send-otp', [ApiPartnerAuthController::class, 'sendOtp'])->name('api.partner.send-otp');
        Route::post('/partner-resend-otp', [ApiPartnerAuthController::class, 'sendOtp'])->name('api.partner.send-otp');
        Route::post('/verify-otp', [ApiPartnerAuthController::class, 'verifyOtp'])->name('api.partner.verify-otp');
        Route::post('/verify-registration-otp', [ApiPartnerAuthController::class, 'verifyRegistrationEmailOtp'])->name('api.partner.verify-registration-otp');
        Route::get('/test/send-otp', [ApiPartnerAuthController::class, 'sendOtp']);
        Route::post('/support/inbound-email', SupportInboundMailController::class);
        Route::post('/partner-approval-status', [ApiPartnerAuthController::class, 'partnerApproval'])->middleware('throttle:10,1');

        // --- PROTECTED PARTNER APIs ---
        Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsurePartnerSingleSession::class])->group(function () {
            Route::get('/me', [ApiLoginController::class, 'me'])->name('api.partner.me');
            Route::get('/global-search', GlobalSearchController::class)->name('api.partner.global-search');
            Route::get('/dashboard', [ApiPartnerAuthController::class, 'dashboard'])->name('api.partner.dashboard');
            Route::post('/logout',[ApiPartnerAuthController::class, 'logout'])->name('api.partner.logout');
            
            Route::post('/account-details', [ApiPartnerAuthController::class, 'account_details']);
            Route::post('/update-profile', [ApiPartnerAuthController::class, 'updateProfile']);
            Route::post('/account-deletion-request', [AccountDeletionController::class, 'requestDeletion']);
            Route::post('/account-deletion-status', [AccountDeletionController::class, 'status']);
            Route::post('/user-management', [ApiPartnerAuthController::class, 'getPartnerUserManagement']);
            Route::post('/user-management-update', [ApiPartnerAuthController::class, 'updatePartnerUserManagement']);
            Route::post('/partner-users', [ApiPartnerAuthController::class, 'partnerUserList']);
            Route::post('/partner-user-detail', [ApiPartnerAuthController::class, 'partnerUserDetail']);
            Route::post('/partner-user-deactivate-device', [ApiPartnerAuthController::class, 'partnerUserDeactivateDevice']);
            Route::post('/partner-user-add', [ApiPartnerAuthController::class, 'partnerUserAdd']);
            Route::post('/partner-user-update', [ApiPartnerAuthController::class, 'partnerUserUpdate']);
            Route::post('/partner-user-inactive', [ApiPartnerAuthController::class, 'partnerUserStatus']);//partner_id, partner_user_id, status=active|inactive
            Route::post('/get-user-permissions', [ApiPartnerAuthController::class, 'getUserPermissions']);
            
            Route::post('/format-ticket-preview', [ApiPartnerAuthController::class, 'formatTicketPreview']);
            Route::post('/add-ticket', [ApiPartnerAuthController::class, 'addTicket']);
            Route::post('/get-tickets', [ApiPartnerAuthController::class, 'getTickets']);
            Route::post('/get-ticket-detail', [ApiPartnerAuthController::class, 'getTicketDetail']);
            // Route::post('/partner-approval-status', [ApiPartnerAuthController::class, 'partnerApproval']);
            Route::post('/generate-ticket-preview', [SupportController::class, 'generateTicketPreview']);
            Route::post('/get-ticket-conversation', [SupportController::class, 'getTicketConversation']);
            Route::post('/reply-ticket', [SupportController::class, 'reply_message']);
            Route::post('/reopen-ticket', [SupportController::class, 'reopenTicket']);
            Route::post('/close-ticket', [SupportController::class, 'closeTicket']);

            // Live Chat APIs
            Route::post('/get-conversation', [ChatController::class, 'getConversation']);
            Route::post('/send-chat-message', [ChatController::class, 'sendMessage']);

            Route::get('/providers', [ApiPartnerAuthController::class, 'index']);
            Route::post('/providers-variants', [ApiPartnerAuthController::class, 'variants']);
            Route::post('/providers-plans', [ApiPartnerAuthController::class, 'plans']);
            
            Route::get('/dashboard-detail', [ApiPartnerAuthController::class, 'creditHistoryget']);
            Route::post('/dashboard-details', [ApiPartnerAuthController::class, 'creditHistory']);
            Route::match(['get', 'post'], '/sales-report', [ApiPartnerAuthController::class, 'salesReport']);
            Route::post('/revenue-history', [ApiPartnerAuthController::class, 'revenueHistory']);
            Route::post('/transaction-history',[ApiPartnerAuthController::class, 'orderhistory']);  
            Route::post('/transaction-details', [ApiPartnerAuthController::class, 'transactionDetails']); 
            Route::post('pending-history',[ApiPartnerAuthController::class, 'pandinghistory']);
            Route::post('/domain-history', [ApiPartnerAuthController::class, 'domainHistory']);
            
            Route::post('/renewals', [ApiPartnerAuthController::class, 'renewalsindex']); 
            Route::post('/renewal-check', [Report::class, 'renewalCheck']); 
            Route::post('/renewalsCustomerdetails', [ApiPartnerAuthController::class, 'renewalsCustomer']);
            Route::post('/renewal-subscription', [ApiSubscriptionRenewalController::class, 'renew'])
                ->name('api.partner.renewal-subscription');

            Route::post('/partner-dashboard-details', [ApiPartnerAuthController::class, 'detailsById'])->name('api.partner.details');

            Route::get('/customers', [CustomerApi::class, 'customersgetIndex']);
            Route::post('/customers', [CustomerApi::class, 'customersIndex']);
            Route::post('/add-customer', [CustomerApi::class, 'customersStore']);
            Route::get('/getCustomerDetail', [CustomerApi::class, 'customersEditGet']);
            Route::get('/all-customers-details', [CustomerApi::class, 'allCustomersDetails']);
            Route::post('/getCustomerDetails', [CustomerApi::class, 'customersEditPost']); 
            Route::post('/update-customer', [CustomerApi::class, 'customersUpdate']);
            Route::post('/customers-orderdetails', [CustomerApi::class, 'customerOrder']);

            Route::post('/details-for-support', [SupportController::class, 'detailsForSupport']);

            Route::post('/notification-list', [ApiPartnerAuthController::class, 'notificationList']);
            Route::post('/notification-delete', [ApiPartnerAuthController::class, 'notificationDelete']);
            Route::post('/notification-read', [ApiPartnerAuthController::class, 'notificationRead']);
            Route::post('/order-list', [ApiPartnerAuthController::class, 'orderindex']);
            Route::post('/credit-notes', [ApiPartnerAuthController::class, 'creditNotesList']);
            Route::post('/license-decrease-request', [ApiPartnerAuthController::class, 'licenseDecreaseRequest']);
            Route::post('/license-decrease-requests', [ApiPartnerAuthController::class, 'licenseDecreaseRequestsList']);
            Route::match(['get', 'post'], '/get-orders-by-partner', [ApiPartnerAuthController::class, 'getOrdersByPartner']);
            Route::post('/orderno-tickets', [ApiPartnerAuthController::class, 'orderListByPartner']);
            Route::get('/planViewByPartner', [ApiPartnerAuthController::class, 'planViewByPartner']);
            Route::post('/create-order', [ApiPartnerOrderController::class, 'addOrder'])->name('api.partner.add-order');
            Route::post('/generate-new-order', [OrderGenerationController::class, 'generateNewOrder'])->name('api.partner.generate-new-order');
            Route::post('/generate-renew-order', [OrderGenerationController::class, 'generateRenewOrder'])->name('api.partner.generate-renew-order');
            Route::post('/renew-cart', [ApiPartnerAuthController::class, 'renewcart'])->name('api.partner.renew-cart');
            Route::post('/generate-upgrade-order', [OrderGenerationController::class, 'generateUpgradeOrder'])->name('api.partner.generate-upgrade-order');
            Route::match(['get', 'post'], '/order-details', [OrderGenerationController::class, 'orderDetails'])->name('api.partner.order-details');
            Route::match(['get', 'post'], '/order-place-without-aadhaar', [OrderGenerationController::class, 'placeOrderWithoutAadhaar'])
                ->name('api.partner.order-place-without-aadhaar');
            Route::match(['get', 'post'], '/order-aadhaar-init', [OrderGenerationController::class, 'orderAadhaarInit']);
            Route::post('/order-aadhaar-resend-otp', [OrderGenerationController::class, 'orderAadhaarInit']);
            Route::match(['get', 'post'], '/order-aadhaar-verify', [OrderGenerationController::class, 'orderAadhaarVerify']);
            
            Route::post('/po-details-store', [ApiPartnerAuthController::class, 'poDetailsStore']);
            Route::post('/upload-order-po', [OrderGenerationController::class, 'uploadOrderPo'])->name('api.partner.upload-order-po');
            Route::post('/order-view', [ApiPartnerAuthController::class, 'orderView']);
            Route::post('/order-edit', [ApiPartnerAuthController::class, 'orderEdit']);
            Route::post('/order-update', [ApiPartnerAuthController::class, 'orderUpdate']);
            Route::post('/order-cancel', [ApiPartnerAuthController::class, 'orderCancel']);
            Route::post('/invoice', [ApiPartnerAuthController::class, 'b2bBilling']);
            Route::get('/orders-check-domain', [ApiPartnerAuthController::class, 'checkDomainAvailability']);
            Route::post('/enquire-now', [EnquiryController::class, 'store'])->name('enquire-now');

            Route::post('/transfer-code', [ApiPartnerAuthController::class, 'postTransferCode']);

            Route::get('/promo-code-list', [ApiPartnerAuthController::class, 'getPromotions']);
            Route::post('/promo-code', [ApiPartnerAuthController::class, 'getPromotion']);

            Route::post('/add-to-cart', [ApiPartnerAuthController::class, 'addToCart']);
            Route::post('/update-cart', [ApiPartnerAuthController::class, 'updateCart']);
            Route::post('/delete-cart', [ApiPartnerAuthController::class, 'deleteCart']);
            Route::post('/getCartDetails', [ApiPartnerAuthController::class, 'CartDetails']);
            Route::post('/addon', [ApiPartnerAuthController::class, 'addon']);
            Route::get('/getcarts-and-CreditDetails/{partner_id}', [ApiPartnerAuthController::class, 'getcarts_and_CreditDetails']);
            Route::post('/get-summary', [ApiPartnerAuthController::class, 'getsummary']);
            Route::get('updated', [ApiPartnerAuthController::class, 'fetchupdated']);

            Route::post('/upgrade-add-to-cart', [ApiPartnerAuthController::class, 'upgradeAddToCart']);
            Route::post('/partial-upgrade-add-to-cart', [ApiPartnerAuthController::class, 'partialUpgradeAddToCart']);
            Route::post('/license-add-to-cart', [ApiPartnerAuthController::class, 'licenseAddToCart']);
            Route::post('/add-license', [ApiPartnerAuthController::class, 'licenseAddToCart']);
            Route::post('/upgrade-cart-details', [ApiPartnerAuthController::class, 'upgradeCartDetails']);

            Route::get('/upgrade', [EnquiryController::class, 'upgrade']);
            Route::post('/subscription-renew', [ApiSubscriptionRenewalController::class, 'renew'])->name('api.partner.subscription-renew.alias');
            Route::post('/subscription-details', [ApiPartnerAuthController::class, 'subscriptionDetails']);
            Route::post('/plan-details', [ApiPartnerAuthController::class, 'planDetail']);
            Route::get('/downgrade', [ApiPartnerAuthController::class, 'downgrade']);
            Route::post('/downgrade-cart', [ApiPartnerAuthController::class, 'downgradeCart']);
            Route::post('/save-notification-preferences',[ApiPartnerAuthController::class, 'saveNotificationPreferences']);
            Route::post('/get-notification-preferences',[ApiPartnerAuthController::class, 'getNotificationPreferences']);

            Route::post('/device-token', [ApiDeviceTokenController::class, 'store'])->name('api.partner.device_token.store');
            Route::post('/device-token/remove', [ApiDeviceTokenController::class, 'destroy'])->name('api.partner.device_token.remove');
            Route::post('/device-token/test-push', [ApiDeviceTokenController::class, 'testPush'])->name('api.partner.device_token.test_push');

            Route::post('/credit-request', [ApiPartnerAuthController::class, 'creditRequest']);

            Route::middleware('throttle:payment')->withoutMiddleware('throttle:api')->group(function () {
                Route::post('/invoices-payment-details', [ApiInvoicePaymentController::class, 'getPaymentDetails']);
                Route::post('/payment-verify', [ApiInvoicePaymentController::class, 'verifyPayment']);
                Route::post('/invoice-payment-verify', [ApiInvoicePaymentController::class, 'verifyPayment']);
            });

            Route::post('/refund-policy-check', [ApiRefundController::class, 'checkPolicy']);
            Route::post('/refunds-list', [ApiRefundController::class, 'index']);
            Route::post('/report-list', [Report::class, 'reportList']);
            // download-report-list 
            Route::post('/download-report-list', [Report::class, 'downloadReportList']);
            Route::post('/report', [Report::class, 'reportBySlug']);
            Route::post('/send-purchase-request', [Report::class, 'sendPurchaseRequest']);
            Route::post('/upgrade-request', [Report::class, 'sendPurchaseRequest']);
        });
    });

    // SuperAdmin / internal refund & approval processing
    Route::prefix('superadmin')->group(function () {
        Route::post('/refund-policy-check', [ApiRefundController::class, 'checkPolicy']);
        Route::post('/refunds', [ApiRefundController::class, 'store']);
        Route::post('/refunds-list', [ApiRefundController::class, 'index']);
        Route::post('/approve-purchase-request', [Report::class, 'approvePurchaseRequest']);
    });

    // Partner Admin APIs
    Route::middleware('auth:sanctum')->prefix('partner-admin')->group(function () {
        Route::get('/services', [ApiPartnerAdminController::class, 'servicesIndex'])->name('api.partner-admin.services.index');
        Route::get('/customers', [ApiPartnerAdminController::class, 'customersIndex'])->name('api.partner-admin.customers.index');
        Route::post('/customers/store', [ApiPartnerAdminController::class, 'customersStore'])->name('api.partner-admin.customers.store');
        Route::post('/search-pincode', [ApiPartnerAdminController::class, 'searchPincode'])->name('api.partner-admin.search-pincode');
    });

    Route::middleware(['auth:sanctum', 'role:user,admin,super_admin'])->prefix('auth')->group(function () {
        Route::get('/me', [ApiLoginController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/logout', [ApiLoginController::class, 'logout'])->name('api.v1.auth.logout');
    });

    // PO / Aadhaar verification APIs (app flow visibility in admin)
    Route::post('/orders/aadhaar/init', [SuperAdminBillingController::class, 'aadharVerifyInit'])->name('api.v1.orders.aadhaar.init');
    Route::post('/orders/aadhaar/otp-verify', [SuperAdminBillingController::class, 'aadharVerifyOtp'])->name('api.v1.orders.aadhaar.otp_verify');
    Route::post('/orders/generate-upgrade', [SuperAdminBillingController::class, 'generateUpgradeOrder'])->name('api.v1.orders.generate_upgrade');
});

Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('/plans/{id}', [PlanController::class, 'show'])->name('plans.show');
Route::post('/calculate-order', [PlanController::class, 'calculateOrder'])->name('calculate-order');
Route::post('/save-order', [OrderController::class, 'saveOrder'])->name('save-order');
Route::post('/update-order', [OrderController::class, 'updateOrder'])->name('update-order');
Route::get('/order/{id}', [OrderController::class, 'getOrder'])->name('get-order');
Route::post('/order/{id}/assign-domain', [OrderController::class, 'assignDomain'])->name('assign-domain');
Route::get('/check-domain', [DomainController::class, 'checkDomain']);

// Location Routes
Route::get('/countries', [LocationController::class, 'getCountries']);
Route::get('/states/{countryId}', [LocationController::class, 'getStates']);
Route::get('/cities/{stateId}', [LocationController::class, 'getCities']);
Route::get('/get-states', [CommonController::class, 'getStates']);
Route::post('/get-cities', [CommonController::class, 'getCities']);
Route::post('/apply-promo', [CommonController::class, 'applyPromo']);
Route::get('/payment/create-order-test', function () {
    return "API working";
});
 
Route::post('/payment-options', [HomeController::class, 'paymentOptions'])->middleware('throttle:payment')->withoutMiddleware('throttle:api');
Route::post('/payment-create-order', [HomeController::class, 'createOrder'])->middleware('throttle:payment')->withoutMiddleware('throttle:api');
Route::post('/payment-verify', [HomeController::class, 'verifyPayment'])->middleware('throttle:payment')->withoutMiddleware('throttle:api');
Route::post('/payment-failure', [HomeController::class, 'recordPaymentFailure'])->middleware('throttle:payment')->withoutMiddleware('throttle:api');
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json([
        'success' => true,
        'error' => false,
        'message' => 'User fetched successfully',
        'data' => $request->user(),
    ], 200);
});

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'error' => true,
        'message' => 'API endpoint not found.',
        'data' => null,
    ], 400);
});
