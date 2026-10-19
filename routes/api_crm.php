<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SuperAdmin\Crm\Api\AuthController;
use App\Http\Controllers\Crm\JustDialLeadController;
use App\Http\Controllers\SuperAdmin\Crm\Api\DashboardController;
use App\Http\Controllers\SuperAdmin\Crm\Api\LeadsController;
use App\Http\Controllers\SuperAdmin\Crm\Api\LocationController;
use App\Http\Controllers\SuperAdmin\Crm\Api\QuotationController;
use App\Http\Controllers\SuperAdmin\Crm\Api\FollowUpController;
use App\Http\Controllers\SuperAdmin\Crm\Api\RenewalController;
use App\Http\Controllers\SuperAdmin\Crm\Api\EmployeeController;
use App\Http\Controllers\SuperAdmin\Crm\Api\SettingController;
use App\Http\Controllers\SuperAdmin\Crm\Api\ProviderController;
use App\Http\Controllers\SuperAdmin\Crm\Api\ReportController;
use App\Http\Controllers\SuperAdmin\Crm\Api\ChatController;

Route::prefix('crm')->name('crm.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | JustDial
    |--------------------------------------------------------------------------
    */

    Route::match(
        ['get', 'post'],
        '/leads/justdial',
        [JustDialLeadController::class, 'store']
    )->name('leads.justdial');


    /*
    |--------------------------------------------------------------------------
    | Public Auth
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->name('auth.')->group(function () {

        Route::post('/login', [AuthController::class, 'login'])
            ->name('login');

        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->name('forgot-password');

        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->name('reset-password');
    });


    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:crm')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Auth
        |--------------------------------------------------------------------------
        */

        Route::prefix('auth')->name('auth.')->group(function () {

            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('logout');

            Route::get('/profile', [AuthController::class, 'profile'])
                ->name('profile');
        });


        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

        Route::get('/countries', [LocationController::class, 'countries'])->name('countries');
        Route::get('/states', [LocationController::class, 'states'])->name('states');
        Route::get('/cities', [LocationController::class, 'cities'])->name('cities');


        /*
        |--------------------------------------------------------------------------
        | Leads
        |--------------------------------------------------------------------------
        */

        Route::post('/lead-create', [LeadsController::class, 'store'])->name('lead-create');
        Route::post('/quotation-create', [QuotationController::class, 'store'])->name('quotation-create');

        Route::prefix('leads')->name('leads.')->group(function () {
            Route::get('/check-domain', [LeadsController::class, 'checkDomain'])->name('check-domain');
            Route::post('/assign', [LeadsController::class, 'assignEmployees'])->name('assign');
            Route::get('/create', [LeadsController::class, 'create'])->name('create');
            Route::get('/uploads/sample', [LeadsController::class, 'downloadSample'])->name('uploads.sample');
            Route::post('/uploads', [LeadsController::class, 'upload'])->name('upload');
            Route::match(['get', 'post'], '/download', [LeadsController::class, 'download'])->name('download');
            Route::post('/delete', [LeadsController::class, 'bulkDelete'])->name('delete');
            Route::get('/', [LeadsController::class, 'index'])->name('index');
            Route::post('/', [LeadsController::class, 'store'])->name('store');

            Route::post('/{lead}/update', [LeadsController::class, 'update'])->name('update');
            Route::post('/{lead}/followup', [LeadsController::class, 'updateFollowUp'])->name('followup');
            Route::post('/{lead}/follow-up', [LeadsController::class, 'updateFollowUp'])->name('follow-up');
            Route::post('/{lead}/contacts', [LeadsController::class, 'storeContact'])->name('contacts.store');
            Route::post('/{lead}/contacts/{contact}/update', [LeadsController::class, 'updateContact'])->name('contacts.update');
            Route::post('/{lead}/contacts/{contact}/delete', [LeadsController::class, 'destroyContact'])->name('contacts.destroy');
            Route::post('/{lead}/services', [LeadsController::class, 'storeService'])->name('services.store');
            Route::post('/{lead}/services/{leadService}/update', [LeadsController::class, 'updateService'])->name('services.update');
            Route::post('/{lead}/services/{leadService}/delete', [LeadsController::class, 'destroyService'])->name('services.destroy');
            Route::post('/{lead}/documents', [LeadsController::class, 'storeDocuments'])->name('documents.store');
            Route::post('/{lead}/documents/delete', [LeadsController::class, 'destroyDocument'])->name('documents.destroy');
            Route::post('/{lead}/delete', [LeadsController::class, 'destroy'])->name('destroy');
            Route::get('/{lead}', [LeadsController::class, 'show'])->name('show');
        });


        /*
        |--------------------------------------------------------------------------
        | Quotations
        |--------------------------------------------------------------------------
        */

        Route::prefix('quotations')->name('api.quotations.')->group(function () {
            Route::get('/', [QuotationController::class, 'index'])->name('index');
            Route::get('/create', [QuotationController::class, 'create'])->name('create');
            Route::post('/', [QuotationController::class, 'store'])->name('store');
            Route::post('/send-mail', [QuotationController::class, 'send'])->name('send');
            Route::post('/send-invoice-mail', [QuotationController::class, 'sendInvoice'])->name('invoice.send');
            Route::post('/{quotation}/update', [QuotationController::class, 'update'])->name('update');
            Route::post('/{quotation}/status', [QuotationController::class, 'updateStatuses'])->name('status');
            Route::get('/{quotation}/activity', [QuotationController::class, 'activity'])->name('activity');
            Route::get('/{quotation}/download', [QuotationController::class, 'download'])->name('download');
            Route::get('/{quotation}/invoice', [QuotationController::class, 'invoice'])->name('invoice');
            Route::get('/{quotation}/invoice/download', [QuotationController::class, 'downloadInvoice'])->name('invoice.download');
            Route::get('/{quotation}', [QuotationController::class, 'show'])->name('show');
        });


        /*
        |--------------------------------------------------------------------------
        | Follow-ups
        |--------------------------------------------------------------------------
        */

        Route::prefix('followups')->name('api.followups.')->group(function () {
            Route::get('/', [FollowUpController::class, 'index'])->name('index');
            Route::get('/{lead}', [FollowUpController::class, 'show'])->name('show');
            Route::post('/{lead}', [FollowUpController::class, 'store'])->name('store');
        });

        Route::prefix('renewals')->name('api.renewals.')->group(function () {
            Route::get('/', [RenewalController::class, 'index'])->name('index');
            Route::get('/{id}', [RenewalController::class, 'show'])
                ->whereNumber('id')
                ->name('show');
        });

        Route::prefix('chat')->name('api.chat.')->group(function () {
            Route::get('/', [ChatController::class, 'index'])->name('index');
            Route::post('/create', [ChatController::class, 'store'])->name('create');
            Route::get('/{id}', [ChatController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/{id}/message', [ChatController::class, 'message'])->whereNumber('id')->name('message');
        });

        Route::prefix('reports')->name('api.reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/{report}', [ReportController::class, 'show'])
                ->where('report', 'sales|pipeline|won|lost|leads|sources|conversion|unassigned|deals|deals-month|deals-quarter|quotations|sent|discounted|renewals|scheduled|overdue|activities')
                ->name('show');
        });


        /*
        |--------------------------------------------------------------------------
        | Manage
        |--------------------------------------------------------------------------
        */

        Route::prefix('employees')->name('api.employees.')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->name('index');
            Route::post('/create', [EmployeeController::class, 'store'])->name('create');
            Route::get('/permissions', [EmployeeController::class, 'permissions'])->name('permissions');
            Route::post('/permissions/save', [EmployeeController::class, 'savePermissions'])->name('permissions.save');
            Route::get('/attendance', [EmployeeController::class, 'attendance'])->name('attendance');
            Route::get('/{id}', [EmployeeController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/{id}/update', [EmployeeController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/delete', [EmployeeController::class, 'destroy'])->whereNumber('id')->name('delete');
        });

        Route::prefix('settings')->name('api.settings.')->group(function () {
            Route::get('/lead-sources', [SettingController::class, 'sources'])->name('sources');
            Route::post('/lead-sources/create', [SettingController::class, 'storeSource'])->name('sources.create');
            Route::post('/lead-sources/{id}/update', [SettingController::class, 'updateSource'])->whereNumber('id')->name('sources.update');
            Route::post('/lead-sources/{id}/delete', [SettingController::class, 'deleteSource'])->whereNumber('id')->name('sources.delete');

            Route::get('/lead-status', [SettingController::class, 'statuses'])->name('statuses');
            Route::post('/lead-status/create', [SettingController::class, 'storeStatus'])->name('statuses.create');
            Route::post('/lead-status/{id}/update', [SettingController::class, 'updateStatus'])->whereNumber('id')->name('statuses.update');
            Route::post('/lead-status/{id}/delete', [SettingController::class, 'deleteStatus'])->whereNumber('id')->name('statuses.delete');

            Route::get('/lead-bifurcation', [SettingController::class, 'bifurcations'])->name('bifurcations');
            Route::post('/lead-bifurcation/create', [SettingController::class, 'storeBifurcation'])->name('bifurcations.create');
            Route::post('/lead-bifurcation/{id}/update', [SettingController::class, 'updateBifurcation'])->whereNumber('id')->name('bifurcations.update');
            Route::post('/lead-bifurcation/{id}/delete', [SettingController::class, 'deleteBifurcation'])->whereNumber('id')->name('bifurcations.delete');

            Route::get('/lead-priority', [SettingController::class, 'priorities'])->name('priorities');
            Route::post('/lead-priority/create', [SettingController::class, 'storePriority'])->name('priorities.create');
            Route::post('/lead-priority/{id}/update', [SettingController::class, 'updatePriority'])->whereNumber('id')->name('priorities.update');
            Route::post('/lead-priority/{id}/delete', [SettingController::class, 'deletePriority'])->whereNumber('id')->name('priorities.delete');

            Route::get('/designation', [SettingController::class, 'designations'])->name('designations');
            Route::post('/designation/create', [SettingController::class, 'storeDesignation'])->name('designations.create');
            Route::post('/designation/{id}/update', [SettingController::class, 'updateDesignation'])->whereNumber('id')->name('designations.update');
            Route::post('/designation/{id}/delete', [SettingController::class, 'deleteDesignation'])->whereNumber('id')->name('designations.delete');
        });

        Route::prefix('providers')->name('api.providers.')->group(function () {
            Route::get('/', [ProviderController::class, 'index'])->name('index');
            Route::get('/{provider}/plans', [ProviderController::class, 'plans'])->name('plans');
        });


        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */

        Route::middleware('admin')
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {

                Route::get('/dashboard', function () {

                    return response()->json([
                        'success' => true,
                        'message' => 'Admin Dashboard',
                        'data' => null,
                    ]);

                })->name('dashboard');
            });
    });
});


/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
*/

Route::fallback(function () {

    return response()->json([
        'success' => false,
        'error' => true,
        'message' => 'CRM API endpoint not found.',
        'data' => null,
    ], 404);

});