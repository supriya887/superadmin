<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SuperAdmin\Crm\Api\AuthController;
use App\Http\Controllers\Crm\JustDialLeadController;
use App\Http\Controllers\SuperAdmin\Crm\Api\DashboardController;
use App\Http\Controllers\SuperAdmin\Crm\Api\LeadsController;
use App\Http\Controllers\SuperAdmin\Crm\Api\LocationController;
use App\Http\Controllers\SuperAdmin\Crm\Api\QuotationController;

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