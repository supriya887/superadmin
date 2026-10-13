<?php

use App\Http\Controllers\Admin\Home;
use App\Http\Controllers\Admin\Login;
use App\Http\Controllers\Api\PartnerAuthController as ApiPartnerAuthController;
use App\Http\Controllers\Api\SmsDemoController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Home\OrderController;
use App\Http\Controllers\Partner\PartnerController;
use App\Http\Controllers\PartnerAdminController;
use App\Http\Controllers\SuperAdmin\AdminSettingController as SuperAdminSettingController;
use App\Http\Controllers\SuperAdmin\AppController as SuperAppController;
use App\Http\Controllers\SuperAdmin\Auth\LoginController as SuperAdminLoginController;
use App\Http\Controllers\SuperAdmin\B2B\B2bNewPlanController;
use App\Http\Controllers\SuperAdmin\SuperAdminOrderController;
use App\Http\Controllers\SuperAdmin\B2B\B2bPartnerController;
use App\Http\Controllers\SuperAdmin\B2B\DomainTransferController;
use App\Http\Controllers\SuperAdmin\B2B\PartnerCustomerController;
use App\Http\Controllers\SuperAdmin\B2B\PartnerRequestController;
use App\Http\Controllers\SuperAdmin\B2c\CustomerController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\CategoryController as SuperCategoryController;
use App\Http\Controllers\SuperAdmin\CreditRequestController;
use App\Http\Controllers\SuperAdmin\LicenseDecreaseRequestController;
use App\Http\Controllers\SuperAdmin\PurchaseRequest;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\InvoiceManagementController;
use App\Http\Controllers\SuperAdmin\RefundController;
use App\Http\Controllers\SuperAdmin\PartnerLoginLogController;
use App\Http\Controllers\SuperAdmin\PlanController as SuperPlanController;
use App\Http\Controllers\SuperAdmin\PromotionsCouponsController;
use App\Http\Controllers\SuperAdmin\ProviderController as SuperProviderController;
use App\Http\Controllers\SuperAdmin\ProviderController;
use App\Http\Controllers\SuperAdmin\StubController;
use App\Http\Controllers\SuperAdmin\TicketController as SuperAdminTicketController;
use App\Http\Controllers\SuperAdmin\SupportAgentController;
use App\Http\Controllers\SuperAdmin\SupportChatController;
use App\Http\Controllers\SuperAdmin\UserController as SuperUserController;
use App\Http\Controllers\SuperAdmin\VariantController as SuperVariantController;
use App\Http\Controllers\Web\Auth\LoginController as WebLoginController;
// use App\Http\Controllers\Home\PaymentController;
use App\Services\SandboxAadhaarService;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;  // ADD THIS LINE
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve public-disk files when symlink/mirror is missing.
 * Also accepts legacy URLs: /storage/app/public/apps/icons/x.png → apps/icons/x.png
 */
Route::get('/storage/{path}', function (string $path) {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^(app/public/)+#', '', $path) ?? $path;
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, '..')) {
        abort(404);
    }

    $storageFile = storage_path('app/public/' . $path);
    $publicFile = public_path('storage/' . $path);

    if ((!is_file($publicFile) || filesize($publicFile) <= 0) && is_file($storageFile) && filesize($storageFile) > 0) {
        $dir = dirname($publicFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @copy($storageFile, $publicFile);
    }

    $serve = null;
    if (is_file($publicFile) && filesize($publicFile) > 0) {
        $serve = $publicFile;
    } elseif (is_file($storageFile) && filesize($storageFile) > 0) {
        $serve = $storageFile;
    }

    if ($serve === null) {
        abort(404);
    }

    return new BinaryFileResponse($serve);
})->where('path', '.*')->name('public.storage.fallback');

// Route::post('/api/v1/orders/aadhaar/init', function(Request $request) {
//     try {
//         \Log::emergency('🚨🚨🚨 DIRECT ROUTE HIT 🚨🚨🚨');
//         \Log::emergency('Order ID: ' . $request->order_id);
//         \Log::emergency('Aadhaar: ' . $request->aadhaar_number);
        
//         $service = new SandboxAadhaarService();
//         $result = $service->generateOtp($request->aadhaar_number, $request->order_id);
        
//         \Log::emergency('Result: ' . json_encode($result));
        
//         return response()->json($result);
//     } catch (\Exception $e) {
//         \Log::emergency('ERROR: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => $e->getMessage()
//         ]);
//     }
// })->name('api.v1.orders.aadhaar.init');

// Route::post('/api/v1/orders/aadhaar/otp-verify', function(Request $request) {
//     try {
//         \Log::emergency('🚨🚨🚨 VERIFY ROUTE HIT 🚨🚨🚨');
//         \Log::emergency('Order ID: ' . $request->order_id);
//         \Log::emergency('OTP: ' . $request->otp);
        
//         $service = new SandboxAadhaarService();
//         $result = $service->verifyOtp($request->order_id, $request->otp);
        
//         \Log::emergency('Verify Result: ' . json_encode($result));
        
//         return response()->json($result);
//     } catch (\Exception $e) {
//         \Log::emergency('VERIFY ERROR: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => $e->getMessage()
//         ]);
//     }
// })->name('api.v1.orders.aadhaar.otp_verify');
// Route::get('/test-resellerclub-api', function() {
//     $domain = 'facebook.com';
//     $parts = explode('.', $domain, 2);
//     $sld = $parts[0];
//     $tld = $parts[1];
    
//     $authUserid = config('services.domaincheck.auth_userid');
//     $apiKey = config('services.domaincheck.api_key');
//     $baseUrl = config('services.domaincheck.base_url');
    
//     $url = $baseUrl . '/api/domains/available.json';
//     $query = http_build_query([
//         'auth-userid' => $authUserid,
//         'api-key' => $apiKey,
//         'domain-name' => $sld,
//         'tlds' => $tld
//     ]);
    
//     $ch = curl_init($url . '?' . $query);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//     curl_setopt($ch, CURLOPT_TIMEOUT, 30);
//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     $curlError = curl_error($ch);
//     curl_close($ch);
    
//     return response()->json([
//         'url' => $url . '?' . $query,
//         'http_code' => $httpCode,
//         'curl_error' => $curlError,
//         'response' => json_decode($response, true),
//         'raw_response' => $response
//     ]);
// });
// Route::get('/test-domain-config', function() {
//     return [
//         'base_url' => config('services.domaincheck.base_url'),
//         'auth_userid' => config('services.domaincheck.auth_userid'),
//         'api_key' => config('services.domaincheck.api_key'),
//     ];
// });
// Route::get('/', [HomeController::class, 'index']);

// Google Play Data Safety — account deletion request info page (request only; no hard delete)
Route::get('/account-deletion', function () {
    return view('public.account_deletion');
})->name('account.deletion');
// Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
// Route::get('/partner', [HomeController::class, 'partner'])->name('partner');
// // about
// Route::get('about', [HomeController::class, 'about'])->name('about');
// Route::get('/tizzy-mail', [HomeController::class, 'tizzy_mail'])->name('tizzy_mail');
// Route::get('/microsoft-365', [HomeController::class, 'microsoft_365'])->name('microsoft_365');
// // migration
// Route::get('migration', [HomeController::class, 'migration'])->name('migration');
// // cloud-microsoft-365
// Route::get('cloud-microsoft-365', [HomeController::class, 'cloud_microsoft_365'])->name('cloud_microsoft_365');
// Route::get('google-workspace', [HomeController::class, 'google_workspace'])->name('google_workspace');
// // verify-email
// Route::get('verify-email', [AuthController::class, 'verify_email'])->name('verify-email');
// Route::middleware(['userAuth'])->group(function () {
// Route::get('dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
// Route::post('user-logout', [AuthController::class, 'logout'])->name('user_logout');
// });
// Route::post('/generate-qr', [HomeController::class, 'generateQr'])->name('generate.qr');
// Route::get('user-login', [HomeController::class, 'user_login'])->name('user_login');
// Route::post('user-login-validation', [AuthController::class, 'loginPost'])->name('user-login-validation');
// Route::get('user-register', [HomeController::class, 'user_register'])->name('user_register');
// Route::post('register-validation', [AuthController::class, 'register_validation'])->name('register-validation');
// Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp');
// Route::post('resend-otp', [AuthController::class, 'resendOtp'])->name('resend-otp');
// Route::get('verification-success', function () {return view('home.verification_success');})->name('verification-success');
// Route::get('payment', [HomeController::class, 'payment'])->name('payment');
// Route::get('payment/{id}', [HomeController::class, 'payment'])->name('payment');
// Route::get('checkout/{order_id}', [HomeController::class, 'checkout'])->name('checkout');
// Route::get('purchase-order/{order}', [OrderController::class, 'purchaseOrder'])->name('purchase-order.show');
// Route::get('purchase-order/{order}/pdf', [OrderController::class, 'generatePurchaseOrderPdf'])->name('purchase-order.pdf');
// Route::get('select-plan/{id}', [HomeController::class, 'select_plan'])->name('select-plan');
// Route::post('verify-payment', [OrderController::class, 'verifyPayment'])->name('verify-payment');
// Route::get('payment-success/{id}', [OrderController::class, 'paymentSuccess'])->name('payment-success');
// // Route::get('/login', [Login::class, 'login'])->name('userlogin')->middleware('adminLogin');
// Route::get('logout', [AuthController::class, 'logout'])->name('user.logout');
// Route::get('plan', [HomeController::class, 'plan'])->name('plan');
// Route::get('kyc-registration', [HomeController::class, 'kycRegistration'])->name('registration');
Route::match(['get', 'post'], '/sms-demo', [SmsDemoController::class, 'sendDemoSms'])->name('sms-demo');
// Route::get('/test/send-otp', [PartnerController::class, 'sendOtponmobile']);
// // patners home
// Route::prefix('partner')->group(function () {
//     Route::get('/register', [PartnerController::class, 'showRegister']);
//     Route::post('/register', [PartnerController::class, 'register']);
//     Route::post('/resend-registration-otp', [PartnerController::class, 'sendOtp']);
    
//     Route::get('/login', [PartnerController::class, 'showLogin']);
//     Route::post('/login', [PartnerController::class, 'login']);

//     // logout
//     Route::get('/logout', [PartnerController::class, 'logout']);

//     Route::get('/forgot-password', [PartnerController::class, 'showForgotPassword']);
//     Route::post('/forgot-password', [PartnerController::class, 'forgotPassword']);
//     Route::get('/verify-reset-otp', [PartnerController::class, 'showVerifyResetOtp']);
//     Route::post('/verify-reset-otp', [PartnerController::class, 'verifyResetOtp']);
//     Route::get('/reset-password', [PartnerController::class, 'showResetPassword']);
//     Route::post('/reset-password', [PartnerController::class, 'resetPassword']);

//     Route::get('/verify-successfully', function () {
//         return view('partner.verify-successfully');
//     });

//     // Route::get('/verify-otp', [PartnerController::class, 'showVerifyOtp']);
//     // Route::post('/verify-otp', [PartnerController::class, 'verifyOtp']);

//     Route::get('/partner-dashboard', [PartnerController::class, 'dashboard']);
//     Route::post('/send-otp', [PartnerController::class, 'sendOtp']);

//     // customers
//     Route::get('/customers', [PartnerAdminController::class, 'customersIndex'])->name('partner.partneradmin.customers.index');
//     Route::get('/customers-create', [PartnerAdminController::class, 'customersCreate'])->name('partner.partneradmin.customers.create');
//     Route::post('/customers-store', [PartnerAdminController::class, 'customersStore'])->name('partner.partneradmin.customers.store');
//     Route::get('/customers-edit/{id}', [PartnerAdminController::class, 'customersEdit'])->name('partner.partneradmin.customers.edit');
//     Route::post('/customers-update', [PartnerAdminController::class, 'customersUpdate'])->name('partner.partneradmin.customers.update');
//     Route::delete('/customers-delete/{id}', [PartnerAdminController::class, 'customersDelete'])->name('partner.partneradmin.customers.delete');
// });




// Route::get('send-email', [Home::class, 'sendmail']);

// Route::post('/admin-login-validation', [Login::class, 'login_validation'])->name('admin.login');

// Route::group(['middleware' => ['adminLoggedIn']], function () {
//     Route::get('add-user-creation', [Home::class, 'add_usercreation']);
//     Route::get('add-user-creation/{id}', [Home::class, 'add_usercreation']);
//     Route::get('user-creation-list', [Home::class, 'usercreation_list']);
//     Route::post('user-validation', [Home::class, 'user_validation']);
//     Route::post('delete-usercreation', [Home::class, 'delete_usercreation']);

//     Route::get('authority-table', [Home::class, 'authority_table']);
//     Route::get('add-authority', [Home::class, 'add_authority']);
//     Route::get('add-authority/{id}', [Home::class, 'add_authority']);
//     Route::post('authority-validation', [Home::class, 'authority_validation']);
//     Route::post('delete-authority', [Home::class, 'delete_authority']);

//     Route::get('rolepermission-table/{id}', [Home::class, 'rolepermission_table']);
//     Route::get('add-rolepermission', [Home::class, 'add_rolepermission']);
//     Route::get('add-rolepermission/{mid}', [Home::class, 'add_rolepermission']);
//     Route::get('add-rolepermission/{mid}/{id}', [Home::class, 'add_rolepermission']);
//     Route::post('rolepermission-validation', [Home::class, 'rolepermission_validation']);
//     Route::post('delete-rolepermission', [Home::class, 'delete_rolepermission']);

//     Route::get('module-mst-table', [Home::class, 'module_mst_table']);
//     Route::get('add-module-mst', [Home::class, 'add_module_mst']);
//     Route::get('add-module-mst/{id}', [Home::class, 'add_module_mst']);
//     Route::post('module-mst-validation', [Home::class, 'module_mst_validation']);
//     Route::post('delete-module-mst', [Home::class, 'delete_module_mst']);


//     Route::get('/dashboard', [PartnerAdminController::class, 'dashboard'])->name('dashboard');
//     Route::get('/services', [PartnerAdminController::class, 'servicesIndex'])->name('partner.services');
//     Route::get('/customers', [PartnerAdminController::class, 'customersIndex'])->name('customers.index');
//     Route::get('/customers/create', [PartnerAdminController::class, 'customersCreate'])->name('customers.create');
//     Route::post('/customers/store', [PartnerAdminController::class, 'customersStore'])->name('customers.store');
//     Route::get('/customers/edit/{id}', [PartnerAdminController::class, 'customersEdit'])->name('customers.edit');
//     Route::post('/customers/update', [PartnerAdminController::class, 'customersUpdate'])->name('customers.update');
//     Route::delete('/customers/delete/{id}', [PartnerAdminController::class, 'customersDelete'])->name('customers.delete');
    
// });

// Route::prefix('web-auth')->name('web.auth.')->group(function () {
//     Route::get('/login', [WebLoginController::class, 'show'])->name('login');
//     Route::post('/login', [WebLoginController::class, 'login'])->name('login.submit');
// });

// Route::middleware(['auth', 'role:user,admin,super_admin'])->prefix('web-auth')->name('web.auth.')->group(function () {
//     Route::get('/dashboard', [WebLoginController::class, 'dashboard'])->name('dashboard');
//     Route::post('/logout', [WebLoginController::class, 'logout'])->name('logout');
// });

// -----------------------------------------------------------------------
// Super Admin Routes
// -----------------------------------------------------------------------
// Route::get('/test-real-aadhaar', function() {
//     $service = new \App\Services\SandboxAadhaarService();
//     $result = $service->generateOtp('789456123010', 999);
    
//     return response()->json([
//         'service_result' => $result,
//         'service_class' => get_class($service),
//         'env_vars' => [
//             'SANDBOX_MODE' => env('SANDBOX_MODE'),
//             'SANDBOX_API_KEY_TEST' => env('SANDBOX_API_KEY_TEST'),
//             'SANDBOX_BASE_URL_TEST' => env('SANDBOX_BASE_URL_TEST'),
//         ]
//     ]);
// });



Route::post('/search-gstin', [AuthController::class, 'searchGstin'])->name('search-gstin');
Route::prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/login',  [SuperAdminLoginController::class, 'show'])->name('login');
    Route::post('/login', [SuperAdminLoginController::class, 'login'])->name('login.post');
    Route::post('/partner-invoices-webhook', [InvoiceManagementController::class, 'paymentWebhook'])->name('partner_invoices.webhook');

    Route::middleware(['super_admin'])->group(function () {
    Route::get('/logout', [SuperAdminLoginController::class, 'logout'])->name('logout');

    Route::get('add-user-creation', [Home::class, 'add_usercreation']);
    Route::get('add-user-creation/{id}', [Home::class, 'add_usercreation']);
    Route::get('user-creation-list', [Home::class, 'usercreation_list']);
    Route::post('user-validation', [Home::class, 'user_validation']);
    Route::post('delete-usercreation', [Home::class, 'delete_usercreation']);

    Route::get('authority-table', [Home::class, 'authority_table']);
    Route::get('add-authority', [Home::class, 'add_authority']);
    Route::get('add-authority/{id}', [Home::class, 'add_authority']);
    Route::post('authority-validation', [Home::class, 'authority_validation']);
    Route::post('delete-authority', [Home::class, 'delete_authority']);

    Route::get('rolepermission-table/{id}', [Home::class, 'rolepermission_table']);
    Route::get('add-rolepermission', [Home::class, 'add_rolepermission']);
    Route::get('add-rolepermission/{mid}', [Home::class, 'add_rolepermission']);
    Route::get('add-rolepermission/{mid}/{id}', [Home::class, 'add_rolepermission']);
    Route::post('rolepermission-validation', [Home::class, 'rolepermission_validation']);
    Route::post('delete-rolepermission', [Home::class, 'delete_rolepermission']);

    Route::get('module-mst-table', [Home::class, 'module_mst_table']);
    Route::get('add-module-mst', [Home::class, 'add_module_mst']);
    Route::get('add-module-mst/{id}', [Home::class, 'add_module_mst']);
    Route::post('module-mst-validation', [Home::class, 'module_mst_validation']);
    Route::post('delete-module-mst', [Home::class, 'delete_module_mst']);

    Route::get('/dashboard',        [SuperAdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/tables',           [SuperAdminDashboardController::class, 'tables'])->name('tables');
    Route::get('/form-validation',  [SuperAdminDashboardController::class, 'formValidation'])->name('form-validation');

    Route::get('/provider-logo/{filename}', function (string $filename) {
        $filename = basename(str_replace(['\\', '/'], '', $filename));
        abort_unless($filename !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $filename), 404);

        $fullPath = storage_path('app/public/providers/logos/' . $filename);
        abort_unless(is_file($fullPath), 404);

        return response()->file($fullPath, [
            'Content-Type' => mime_content_type($fullPath) ?: 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    })->where('filename', '[^/]+')->name('providers.logo');

    Route::get('/providers',             [SuperProviderController::class, 'index'])->name('providers.index');
    Route::get('/providers/add',        [SuperProviderController::class, 'form'])->name('providers.form');
    Route::get('/providers/{id}',   [SuperProviderController::class, 'form'])->name('providers.form.edit');
    Route::post('/providers/save',       [SuperProviderController::class, 'save'])->name('providers.save');
    Route::post('/providers/delete',     [SuperProviderController::class, 'delete'])->name('providers.delete');
    Route::get('/providers-variants/{id}', [SuperProviderController::class, 'variants'])->name('providers.variants');

    // Variant Routes
    Route::post('variants-validation', [SuperVariantController::class, 'variants_validation'])->name('providers.variants.save');
    Route::get('/providers/variants/{id}/edit', [SuperVariantController::class, 'edit'])->name('providers.variants.edit');
    Route::post('/superadmin/providers/variants/update-order',[SuperVariantController::class, 'updateOrder'])->name('providers.variants.updateOrder');

    // Plan Routes
    Route::get('/plans/add-plan', [SuperPlanController::class, 'providerSelector'])->name('plans.add_selector');
    Route::get('/plans/{provider_id}/{variant_id}', [SuperPlanController::class, 'index']);
    Route::get('/plans/add-plan/{provider_id}/{variant_id}', [SuperPlanController::class, 'create']);
    Route::post('/plans/save-plan/{provider_id}/{variant_id}', [SuperPlanController::class, 'save']);
    Route::get('/plans/edit-plan/{provider_id}/{variant_id}/{plan_id}', [SuperPlanController::class, 'edit']);
    Route::post('/plans/delete-plan/{provider_id}/{variant_id}', [SuperPlanController::class, 'delete']);
    Route::post('/plans/update-order',[SuperPlanController::class, 'updateOrder'])->name('superadmin.plans.updateOrder');

    // Popular Apps & Services Master Routes
    Route::get('/apps', [SuperAppController::class, 'index'])->name('apps.index');
    Route::get('/apps/form/{id?}', [SuperAppController::class, 'form'])->name('apps.form');
    Route::post('/apps/save', [SuperAppController::class, 'save'])->name('apps.save');
    Route::post('/apps/delete', [SuperAppController::class, 'delete'])->name('apps.delete');

    // Plan Categories Master Routes
    Route::get('/categories', [SuperCategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/form/{id?}', [SuperCategoryController::class, 'form'])->name('categories.form');
    Route::post('/categories/save', [SuperCategoryController::class, 'save'])->name('categories.save');
    Route::post('/categories/delete', [SuperCategoryController::class, 'delete'])->name('categories.delete');

    Route::get('/products',    [StubController::class, 'products'])->name('products.index');
    Route::get('/plans',       [StubController::class, 'plans'])->name('plans.index');
    Route::get('/variants',    [StubController::class, 'variants'])->name('variants.index');
    Route::get('/options',     [StubController::class, 'options'])->name('options.index');
    // ── Commerce ─────────────────────────────────────────────────
    Route::get('/orders', [BillingController::class, 'index'])->name('orders.index');
    // GET avoids 419 CSRF mismatches on DataTables reload (read-only list).
    Route::match(['GET', 'POST'], '/orders-list-table', [BillingController::class, 'ordersListTable'])->name('orders.list_table');
    Route::get('/legacy-manual-order', [BillingController::class, 'legacyManualOrderForm'])->name('manual-create-order');
    Route::post('/legacy-manual-order', [BillingController::class, 'storeLegacyManualOrder'])->name('manual-create-order.store');
    Route::get('/legacy-manual-order/plans', [BillingController::class, 'legacyManualPlanOptions'])->name('manual-create-order.plans');
    Route::get('/orders-add', [SuperAdminOrderController::class, 'create'])->name('orders.create');
    Route::get('/orders-add/{step}/{id?}', [SuperAdminOrderController::class, 'form'])->name('orders.create.step')->where(['step' => '[1-3]']);
    Route::post('/orders-save', [SuperAdminOrderController::class, 'saveOrder'])->name('orders.save');
    Route::post('/orders-add/{step}/{id?}', [SuperAdminOrderController::class, 'saveOrderStep'])->name('orders.save.step')->where(['step' => '[1-3]']);
    Route::post('/orders-customers-select', [BillingController::class, 'customersSelect'])->name('orders.customers_select');
    Route::get('/orders-transfer-code', [SuperAdminOrderController::class, 'transferCode'])->name('orders.transfer_code');
    Route::post('/orders-aadhaar-init', [BillingController::class, 'aadharVerifyInit'])->name('orders.aadhaar_init');
    Route::post('/orders-aadhaar-otp-verify', [BillingController::class, 'aadharVerifyOtp'])->name('orders.aadhaar_verify');
    // Add this inside the superadmin middleware group
    Route::post('/orders-save-aadhaar', [B2bNewPlanController::class, 'saveAadhaar'])->name('orders.save.aadhaar');
    Route::get('/orders-partner-context/{partner_id}', [B2bNewPlanController::class, 'partnerContext'])->name('orders.partner_context')->whereNumber('partner_id');
    Route::get('/orders-provider-customers/{partner_id}/{provider_id}', [B2bNewPlanController::class, 'providerCustomers'])
        ->name('orders.provider_customers')
        ->whereNumber(['partner_id', 'provider_id']);
    Route::get('/orders-plans-by-provider', [BillingController::class, 'orderPlansByProvider'])->name('orders.plans_by_provider');
    Route::get('/orders-partner-customers/{partner_id}', [BillingController::class, 'partnerCustomers'])->name('orders.partner_customers')->whereNumber('partner_id');
    Route::get('/orders-customer-domains/{id}', [BillingController::class, 'customerDomains'])->name('orders.customer_domains');
    Route::get('/orders-customer-meta/{id}', [BillingController::class, 'customerOrderMeta'])->name('orders.customer_meta');
    Route::get('/orders-check-domain', [BillingController::class, 'checkDomainAvailability'])->name('orders.domain_check');
    Route::post('/generate-upgrade-order', [BillingController::class, 'generateUpgradeOrder'])->name('api.partner.generate-upgrade-order');
    Route::post('/orders-store', [BillingController::class, 'store'])->name('orders.store');
    Route::get('/orders-edit/{id}', [BillingController::class, 'edit'])->name('orders.edit');
    Route::post('/orders-update/{id}', [BillingController::class, 'update'])->name('orders.update');
    Route::post('/orders-delete/{id}', [BillingController::class, 'destroy'])->name('orders.delete');
    Route::post('/orders-action/{id}', [BillingController::class, 'orderAction'])->name('orders.action');
    Route::get('/orders-details/{id}/', [BillingController::class, 'orders_details'])->name('orders.details');
    Route::post('/orders-details/{id}/generate-credit-note', [BillingController::class, 'generateCreditNote'])
        ->name('orders.generate_credit_note')
        ->whereNumber('id');
    Route::post('/orders-details/{id}/regenerate-credit-note', [BillingController::class, 'regenerateCreditNotePdf'])
        ->name('orders.regenerate_credit_note')
        ->whereNumber('id');
    Route::get('/orders/{id}/po-pdf', [BillingController::class, 'orderPoPdf'])->name('orders.po_pdf')->whereNumber('id');
    Route::get('/orders-invoice/{id}/', [BillingController::class, 'invoice'])->name('orders.invoice');
    Route::get('/orders-invoice-download/{id}/', [BillingController::class, 'downloadInvoice'])->name('orders.invoice.download');
    // Sandbox.co.in e-invoicing — cancel an issued IRN for an order.
    Route::post('/orders/{id}/einvoice-cancel', [BillingController::class, 'cancelEInvoice'])->name('orders.einvoice.cancel');
    Route::post('/gst-invoices-store', [BillingController::class, 'saveGstInvoice'])->name('gst_invoices.store');
    Route::get('/subscriptions',[StubController::class, 'subscriptions'])->name('subscriptions.index');
    // B2B Billing (Partner Customers Orders)
    Route::get('/b2b-billing', [BillingController::class, 'b2bBillingIndex'])->name('b2b_billing.index');
    Route::post('/b2b-billing-list-table', [BillingController::class, 'b2bBillingListTable'])->name('b2b_billing.list_table');
    Route::post('/b2b-billing-generate-invoices', [BillingController::class, 'b2bBillingBulkInvoices'])->name('b2b_billing.bulk_invoices');
    //Promotion & Coupens
    Route::get('/promotion-dashboard', [PromotionsCouponsController::class, 'promotion_Dashboard'])->name('promotion_dashboard.index');
    Route::get('/promotions-coupens', [PromotionsCouponsController::class, 'index'])->name('promostions_coupens.index');
    Route::get('/promotions-coupens-list', [PromotionsCouponsController::class, 'list'])->name('promotions_coupons.list');
    Route::post('/promotions-coupens-save', [PromotionsCouponsController::class, 'save'])->name('promotions_coupons.save');
    Route::get('/promotions-coupens-edit/{id}', [PromotionsCouponsController::class, 'edit'])->name('promotions_coupons.edit');
    Route::delete('/promotions-coupens-delete/{id}', [PromotionsCouponsController::class, 'destroy'])->name('promotions_coupons.destroy');
    Route::post('/promotions-coupens-status/{id}', [PromotionsCouponsController::class, 'changeStatus'])->name('promotions_coupens.change_status');
    // ── Operations ───────────────────────────────────────────────
    Route::get('/provisioning',[StubController::class, 'provisioning'])->name('provisioning.index');
    Route::get('/notifications',[StubController::class, 'notifications'])->name('notifications.index');
    // Support Center Routes
    Route::get('/support/tickets', [SuperAdminTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SuperAdminTicketController::class, 'create'])->name('support.create');
    Route::post('/support/store', [SuperAdminTicketController::class, 'save'])->name('support.store');
    Route::get('/support/details/{id}', [SuperAdminTicketController::class, 'details'])->name('support.details');
    Route::post('/support/details/{id}/activity', [SuperAdminTicketController::class, 'addActivity'])->name('support.activity');
    // Live chat dashboard
    Route::get('/support/chats', [SupportChatController::class, 'index'])->name('support.chats');
    Route::get('/support/chats/feed', [SupportChatController::class, 'feed'])->name('support.chats.feed');
    Route::post('/support/chats/{id}/send', [SupportChatController::class, 'send'])->whereNumber('id')->name('support.chats.send');
    Route::post('/support/chats/{id}/transfer', [SupportChatController::class, 'transfer'])->whereNumber('id')->name('support.chats.transfer');
    Route::post('/support/chats/{id}/close', [SupportChatController::class, 'close'])->whereNumber('id')->name('support.chats.close');
    Route::post('/support/details/{id}/assign', [SuperAdminTicketController::class, 'assignAgent'])->name('support.assign');
    Route::get('/support/details/{id}/conversation', [SuperAdminTicketController::class, 'conversation'])->name('support.conversation');
    Route::get('/support/edit/{id}', [SuperAdminTicketController::class, 'edit'])->name('support.edit');
    Route::patch('/support/close/{id}', [SuperAdminTicketController::class, 'close'])->name('support.close');
    Route::match(['post', 'put'], '/support/update/{id}', [SuperAdminTicketController::class, 'save'])->name('support.update');
    Route::get('/support/attachment/download/{id}', [SuperAdminTicketController::class, 'downloadAttachment'])->name('support.attachment.download');
    Route::delete('/support/attachment/delete/{id}', [SuperAdminTicketController::class, 'deleteAttachment'])->name('support.attachment.delete');
    // Support agent presence (Active Now / Last active) + manual Active/Inactive/Away toggle
    Route::get('/support/agents/presence', [SupportAgentController::class, 'presence'])->name('support.agents.presence');
    Route::post('/support/agents/heartbeat', [SupportAgentController::class, 'heartbeat'])->name('support.agents.heartbeat');
    Route::post('/support/agents/{id}/status', [SupportAgentController::class, 'updateStatus'])->name('support.agents.status')->whereNumber('id');
    Route::get('/support/report', [SupportAgentController::class, 'report'])->name('support.report');

    Route::get('/settings', [SuperAdminSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SuperAdminSettingController::class, 'update'])->name('settings.update');

    // B2C Customer User Management
    Route::get('/b2c-customers-list', [CustomerController::class, 'customer_list'])->name('b2c_customers.list');
    Route::post('/b2c-customers-list-table', [CustomerController::class, 'customer_list_table'])->name('b2c_customers.list_table');
    Route::get('/b2c-customers-add', [CustomerController::class, 'add_customer'])->name('b2c_customers.add');
    Route::get('/b2c-customers-edit/{id}', [CustomerController::class, 'add_customer'])->name('b2c_customers.edit');
    Route::post('/b2c-customers-validation', [CustomerController::class, 'customer_validation']);
    Route::post('/b2c-customers-delete', [CustomerController::class, 'delete_customer'])->name('b2c_customers.delete');

    // B2B Partner Management
    Route::get('/b2b-partners-list', [B2bPartnerController::class, 'partner_list'])->name('b2b_partners.list');
    Route::post('/b2b-partners-list-table', [B2bPartnerController::class, 'partner_list_table'])->name('b2b_partners.list_table');
    Route::get('/b2b-partner-add', [B2bPartnerController::class, 'add_partner'])->name('b2b_partners.add');
    Route::get('/b2b-partner-edit/{id}', [B2bPartnerController::class, 'add_partner'])->name('b2b_partners.edit');
    Route::get('/partners-credit-history/{id}', [B2bPartnerController::class, 'creditHistory'])->name('b2b_partners.credit_history');
    Route::post('/partners-credit-history-apply/{id}', [B2bPartnerController::class, 'creditHistoryApply'])->name('admin.partners.credit_history.apply');
    Route::post('/partners-credit-history-limit/{id}', [B2bPartnerController::class, 'creditHistoryUpdateLimit'])->name('admin.partners.credit_history.update_limit');
    Route::post('/partners-credit-history-discount/{id}', [B2bPartnerController::class, 'creditHistoryUpdateDiscount'])->name('admin.partners.credit_history.update_discount');
    Route::get('/b2b-partner-wallet-statement/{id}', [B2bPartnerController::class, 'walletStatement'])->name('b2b_partners.wallet_statement');
    Route::get('/b2b-partner-cart/{id}', [B2bPartnerController::class, 'partnerCart'])->name('b2b_partners.cart');
    Route::get('/b2b-partner-approve/{id}', [B2bPartnerController::class, 'approve_partner'])->name('b2b_partners.approve');
    Route::post('/b2b-partner-update-status/{id}', [B2bPartnerController::class, 'update_status'])->name('b2b_partners.update_status');
    Route::post('/b2b-partner-validation', [B2bPartnerController::class, 'partner_validation']);
    Route::post('/b2b-partner-delete', [B2bPartnerController::class, 'delete_partner'])->name('b2b_partners.delete');
    Route::get('/partner-requests', [PartnerRequestController::class, 'index'])->name('partner_requests.index');
    Route::post('/partner-requests-list-table', [PartnerRequestController::class, 'listTable'])->name('partner_requests.list_table');
    Route::get('/partner-requests/{id}', [PartnerRequestController::class, 'show'])->name('partner_requests.show');
    Route::post('/partner-requests/{id}/approve', [PartnerRequestController::class, 'approve'])->name('partner_requests.approve');
    Route::post('/partner-requests/{id}/reject', [PartnerRequestController::class, 'reject'])->name('partner_requests.reject');
    Route::post('/partner-requests/{id}/credit-limit', [PartnerRequestController::class, 'updateCreditLimit'])->name('partner_requests.credit_limit');
    Route::post('/partner-requests/{id}/manual-credit', [PartnerRequestController::class, 'addManualCredit'])->name('partner_requests.manual_credit');
    Route::post('/get-transfer-code', [B2bPartnerController::class, 'getTransferCode'])->name('get_transfer_code');
    Route::get('/credit-requests', [CreditRequestController::class, 'index'])->name('credit_requests.index');
    Route::post('/credit-requests/{id}/approve', [CreditRequestController::class, 'approve'])->name('credit_requests.approve')->whereNumber('id');
    Route::post('/credit-requests/{id}/reject', [CreditRequestController::class, 'reject'])->name('credit_requests.reject')->whereNumber('id');
    Route::get('/license-decrease-requests', [LicenseDecreaseRequestController::class, 'index'])->name('license_decrease_requests.index');
    Route::post('/license-decrease-requests/{id}/approve', [LicenseDecreaseRequestController::class, 'approve'])->name('license_decrease_requests.approve')->whereNumber('id');
    Route::post('/license-decrease-requests/{id}/reject', [LicenseDecreaseRequestController::class, 'reject'])->name('license_decrease_requests.reject')->whereNumber('id');
    Route::post('/license-decrease-requests/{id}/revert', [LicenseDecreaseRequestController::class, 'revert'])->name('license_decrease_requests.revert')->whereNumber('id');
    // Purchase / Upgrade Requests (from partners - Google/Tizzy approval flow)
    Route::get('/purchase-requests', [PurchaseRequest::class, 'index'])->name('purchase_requests.index');
    Route::post('/purchase-requests/{id}/approve', [PurchaseRequest::class, 'approve'])->name('purchase_requests.approve')->whereNumber('id');
    Route::post('/purchase-requests/{id}/reject', [PurchaseRequest::class, 'reject'])->name('purchase_requests.reject')->whereNumber('id');
    Route::get('/purchase-requests-show/{id}', [PurchaseRequest::class,'purchase_requests_show']);
    Route::post('/purchase-requests-list-table', [PurchaseRequest::class,'purchaserequestslisttable']);
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('/refunds', [RefundController::class, 'store'])->name('refunds.store');
    Route::post('/refunds/{id}/retry', [RefundController::class, 'retry'])->name('refunds.retry')->whereNumber('id');
    Route::get('/b2b-partner-notification-preference/{customer_id}',[B2bPartnerController::class, 'notificationPreference'])->name('b2b_partners.notification_preference')->whereNumber('customer_id');
    Route::post('/b2b-partner-notification-preference-update',[B2bPartnerController::class, 'updateNotificationPreference'])->name('b2b_partners.notification_preference_update');
    
    Route::get('/b2b-add-subscription/{partner_id}', [B2bNewPlanController::class, 'create'])->name('b2b.add_subscription')->whereNumber('partner_id');
    Route::post('/b2b-add-subscription/{partner_id}', [B2bNewPlanController::class, 'store'])->name('b2b.add_subscription.store')->whereNumber('partner_id');

    // Domain Transfer Orders
    Route::get('/domain-transfer-orders', [DomainTransferController::class, 'index'])->name('domain_transfer_orders.index');
    Route::get('/domain-transfer-orders-list-table', [DomainTransferController::class, 'listTable'])->name('domain_transfer_orders.list_table');
    // Route::get('/domain-transfer-orders/{id}',[DomainTransferController::class, 'show'])->name('domain_transfer_orders.show');
    // // Route::get('/domain-transfer-orders/{id}/edit',[DomainTransferController::class, 'edit'])->name('domain_transfer_orders.edit');
    // Route::put('/domain-transfer-orders/{id}',[DomainTransferController::class, 'update'])->name('domain_transfer_orders.update');
    // Route::post('/domain-transfer-orders/domain-check',[DomainTransferController::class, 'domainCheck'])->name('domain_transfer_orders.domain.check');
    // Route::post('/domain-transfer-orders', [DomainTransferController::class, 'store'])->name('domain_transfer_orders.store');
    // Route::post('/domain-transfer-orders/{id}/update-status', [DomainTransferController::class, 'updateStatus'])->name('domain_transfer_orders.update_status'); // Add this line

    // Partner Customers Management
    Route::get('/b2b-partner-customers/{partner_id}', [PartnerCustomerController::class, 'index'])->name('b2b_partners.customers.list');
    Route::post('/b2b-partner-customers-list-table/{partner_id}', [PartnerCustomerController::class, 'b2b_partner_customers_list_table'])->name('b2b_partners.customers.list_table');
    Route::get('/b2b-partner-customers-renew/{partner_id}/{customer_id}', [PartnerCustomerController::class, 'startRenewal'])->name('b2b_partners.customers.renew');
    Route::get('/b2b-partner-customers-add', [PartnerCustomerController::class, 'b2b_partner_customers_add_open'])->name('b2b_partners.customers.add_open');
    Route::get('/b2b-partner-customers-add/{partner_id}', [PartnerCustomerController::class, 'b2b_partner_customers_add'])->name('b2b_partners.customers.add');
    Route::get('/b2b-partner-customers-edit/{partner_id}/{id}', [PartnerCustomerController::class, 'b2b_partner_customers_add'])->name('b2b_partners.customers.edit');
    Route::post('/b2b-partner-customers-validation/{partner_id}', [PartnerCustomerController::class, 'b2b_partner_customers_validation'])->name('b2b_partners.customers.validation');
    Route::post('/b2b-partner-customers-delete/{partner_id}', [PartnerCustomerController::class, 'b2b_partner_customers_delete'])->name('b2b_partners.customers.delete');
    Route::get('/b2b-partner-customers-subscriptions/{partner_id}', [PartnerCustomerController::class, 'subscriptionsByPartner'])->name('b2b_partners.customers.subscriptions_partner');
    Route::get('/b2b-partner-customers-subscriptions/{partner_id}/{customer_id}', [PartnerCustomerController::class, 'subscriptions'])->name('b2b_partners.customers.subscriptions');
    Route::post('/b2b-partner-customers-subscriptions-table/{partner_id}/{customer_id}', [PartnerCustomerController::class, 'subscriptionsTable'])->name('b2b_partners.customers.subscriptions_table');
    Route::get('/b2b-partner-domain-history/{partner_id}', [PartnerCustomerController::class, 'domainHistoryPage'])->name('b2b_partners.domain_history');
    Route::get('/b2b-partner-customers-billing/{partner_id}/{customer_id}', [PartnerCustomerController::class, 'billing'])->name('b2b_partners.customers.billing');
    Route::get('/b2b-partner-customers-update-plan/{partner_id}/{subscription_id}', [PartnerCustomerController::class, 'updatePlan'])->name('b2b_partners.customers.update_plan');
    Route::get('/upgrade', [ApiPartnerAuthController::class, 'upgrade'])->name('api.partner.upgrade');
    Route::post('/b2b-partner-customers-update-plan/{partner_id}/{subscription_id}', [PartnerCustomerController::class, 'renewUsingCredit'])->name('b2b_partners.customers.renew_with_credit');
    Route::post('/b2b-partner-customers-upgrade-plan/{partner_id}/{subscription_id}', [PartnerCustomerController::class, 'upgradeUsingCredit'])->name('b2b_partners.customers.upgrade_with_credit');
    Route::post('/b2b-partner-customers-downgrade-plan/{partner_id}/{subscription_id}', [PartnerCustomerController::class, 'downgradeUsingCredit'])->name('b2b_partners.customers.downgrade_with_credit');
    Route::get('/b2b-partner-customers-subscription-plan-modal/{partner_id}/{subscription_id}', [PartnerCustomerController::class, 'subscriptionPlanModal'])->name('b2b_partners.customers.subscription_plan_modal');
    Route::get('/renewals', [PartnerCustomerController::class, 'renewalsIndex'])->name('renewals.index');
    Route::get('/renewals-table', [PartnerCustomerController::class, 'renewalsIndexTable'])->name('renewals.index.table');
    Route::get('/renewals/{partner_id}', [PartnerCustomerController::class, 'renewalsByPartner'])->name('renewals.partner');
    Route::get('/renewals/{partner_id}/table', [PartnerCustomerController::class, 'renewalsByPartnerTable'])->name('renewals.partner.table');

    Route::get('/partner-invoices', [InvoiceManagementController::class, 'index'])->name('partner_invoices.index');
    Route::get('/partner-invoices/{id}', [InvoiceManagementController::class, 'partner'])->name('partner_invoices.partner')->whereNumber('id');
    Route::post('/partner-invoices-initiate', [InvoiceManagementController::class, 'initiatePayment'])->name('partner_invoices.initiate');

    Route::get('check-domain', [BillingController::class, 'checkDomainAvailability'])->name('b2b.domain_check');
    Route::get('/partners', [PartnerAdminController::class, 'partnersIndex'])->name('partners.index');
    Route::get('/gst-details', [PartnerAdminController::class, 'gstIndex'])->name('gst.index');
    Route::post('/search-pincode', [PartnerAdminController::class, 'searchPincode'])->name('search-pincode');
    Route::get('/partner-logs', [PartnerLoginLogController::class, 'index'])->name('partner_logs.index');
    Route::post('/partner-logs-table', [PartnerLoginLogController::class, 'table'])->name('partner_logs.table');
    Route::get('/domaintransferrequests', [DomainTransferController::class, 'index'])->name('domaintransferrequests');
    Route::get('/po-detail/{id}',[BillingController::class,'po_details'])->name('po_details');
    });
});
