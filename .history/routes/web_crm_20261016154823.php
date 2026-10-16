<?php

use App\Http\Controllers\SuperAdmin\Crm\DashboardController;
use App\Http\Controllers\SuperAdmin\Crm\EmployeeController;
use App\Http\Controllers\SuperAdmin\Crm\FollowUpController;
use App\Http\Controllers\SuperAdmin\Crm\LeadsController;
use App\Http\Controllers\SuperAdmin\Crm\QuotationController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadSourceController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadStatusController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadBifurcationController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadPriorityController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\DesignationController;
use App\Http\Controllers\SuperAdmin\Crm\ServicesProviderController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| CRM Web Routes
|--------------------------------------------------------------------------
|
| CRM routes are accessed from the Super Admin panel.
|
*/

Route::prefix('crm')
    ->middleware('adminLoggedIn')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | CRM Dashboard
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('crm.dashboard');
        Route::get('/reports', [DashboardController::class, 'reports'])
            ->name('crm.reports');
        //Leads
        Route::get('/leads', [LeadsController::class, 'index'])
            ->name('crm.leads');

        Route::get('/leads/create', [LeadsController::class, 'create'])
            ->name('crm.leads.create');

        Route::post('/leads', [LeadsController::class, 'store'])
            ->name('crm.leads.store');

        Route::get('/leads/uploads/sample', [LeadsController::class, 'downloadSample'])
            ->name('crm.leads.uploads.sample');

        Route::get('/leads/uploads', [LeadsController::class, 'uploads'])
            ->name('crm.leads.uploads');

        Route::post('/leads/uploads', [LeadsController::class, 'upload'])
            ->name('crm.leads.upload');

        Route::get('/leads/check-domain', [LeadsController::class, 'checkDomain'])
            ->name('crm.leads.check-domain');

        Route::post('/leads/assign', [LeadsController::class, 'assignEmployees'])
            ->name('crm.leads.assign');
        Route::post('/leads/download', [LeadsController::class, 'download'])
            ->name('crm.leads.download');
        Route::post('/leads/delete', [LeadsController::class, 'bulkDelete'])
            ->name('crm.leads.delete');
        Route::post('/leads/{lead}/update', [LeadsController::class, 'update'])
            ->name('crm.leads.update');
        Route::post('/leads/{lead}/followup', [LeadsController::class, 'updateFollowUp'])
            ->name('crm.leads.followup');
        Route::post('/leads/{lead}/contacts', [LeadsController::class, 'storeContact'])
            ->name('crm.leads.contacts.store');
        Route::post('/leads/{lead}/contacts/{contact}/update', [LeadsController::class, 'updateContact'])
            ->name('crm.leads.contacts.update');
        Route::post('/leads/{lead}/contacts/{contact}/delete', [LeadsController::class, 'destroyContact'])
            ->name('crm.leads.contacts.destroy');
        Route::post('/leads/{lead}/services', [LeadsController::class, 'storeService'])
            ->name('crm.leads.services.store');
        Route::post('/leads/{lead}/services/{leadService}/update', [LeadsController::class, 'updateService'])
            ->name('crm.leads.services.update');
        Route::post('/leads/{lead}/services/{leadService}/delete', [LeadsController::class, 'destroyService'])
            ->name('crm.leads.services.destroy');
        Route::post('/leads/{lead}/documents', [LeadsController::class, 'storeDocuments'])
            ->name('crm.leads.documents.store');
        Route::post('/leads/{lead}/documents/delete', [LeadsController::class, 'destroyDocument'])
            ->name('crm.leads.documents.destroy');

        Route::get('/leads/{lead}', [LeadsController::class, 'show'])
            ->name('crm.leads.show');
        Route::get('/followups', [FollowUpController::class, 'index'])
            ->name('crm.followups');
        Route::get('/followups/{lead}', [FollowUpController::class, 'show'])
            ->name('crm.followups.show');
        Route::post('/followups/{lead}', [FollowUpController::class, 'store'])
            ->name('crm.followups.store');
        Route::get('/quotations', [QuotationController::class, 'index'])
            ->name('crm.quotations');
        Route::get('/deals', [QuotationController::class, 'deals'])
            ->name('crm.deals');
        Route::get('/deals/{quotation}/view', [QuotationController::class, 'showDeal'])
            ->name('crm.deals.view');
        Route::get('/deals/orders/{id}', [QuotationController::class, 'showDealOrder'])
            ->whereNumber('id')
            ->name('crm.deals.order');
        Route::post('/deals/{quotation}/convert', [QuotationController::class, 'convertToOrder'])
            ->name('crm.deals.convert');        Route::post('/deals/{quotation}/po', [QuotationController::class, 'saveDealPo'])
            ->name('crm.deals.po.save');
        Route::get('/deals/{quotation}/po', [QuotationController::class, 'dealPoPdf'])
            ->name('crm.deals.po');
        Route::get('/quotations/create', [QuotationController::class, 'create'])
            ->name('crm.quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])
            ->name('crm.quotations.store');
        Route::post('/quotations/send-mail', [QuotationController::class, 'send'])
            ->name('crm.quotations.send');
        Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])
            ->name('crm.quotations.edit');
        Route::post('/quotations/{quotation}/update', [QuotationController::class, 'update'])
            ->name('crm.quotations.update');
        Route::post('/quotations/{quotation}/status', [QuotationController::class, 'updateStatuses'])
            ->name('crm.quotations.status');
        Route::get('/quotations/{quotation}/download', [QuotationController::class, 'download'])
            ->name('crm.quotations.download');
        Route::get('/quotations/{quotation}/activity', [QuotationController::class, 'activity'])
            ->name('crm.quotations.activity');
        Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])
            ->name('crm.quotations.show');
        Route::get('/quotations/{quotation}/invoice', [QuotationController::class, 'invoice'])
            ->name('crm.quotations.invoice');
        Route::get('/quotations/{quotation}/invoice/download', [QuotationController::class, 'downloadInvoice'])
            ->name('crm.quotations.invoice.download');
        Route::post('/quotations/send-invoice-mail', [QuotationController::class, 'sendInvoice'])
            ->name('crm.quotations.invoice.send');

        // Employee Management
        Route::get('/employee', [EmployeeController::class, 'index'])->name('crm.employees');
        Route::get('/employee/att', [EmployeeController::class, 'attindex'])->name('crm.employee.att');
        Route::get('/employee/permissions', [EmployeeController::class, 'permissions'])->name('crm.employee.permissions');
        Route::post('/employee/permissions', [EmployeeController::class, 'savePermissions'])->name('crm.employee.permissions.save');
        Route::get('/employee/create', [EmployeeController::class, 'create'])->name('crm.employee.create');
        Route::post('/employee/store', [EmployeeController::class, 'store'])->name('crm.employee.store');
        Route::get('/employee/{id}/view', [EmployeeController::class, 'show'])->whereNumber('id')->name('crm.employee.view');
        Route::get('/employee/{id}/edit', [EmployeeController::class, 'edit'])->whereNumber('id')->name('crm.employee.edit');
        Route::post('/employee/{id}/update', [EmployeeController::class, 'update'])->whereNumber('id')->name('crm.employee.update');
        Route::post('/employee/{id}/delete', [EmployeeController::class, 'destroy'])->whereNumber('id')->name('crm.employee.destroy');

        Route::get('/providers', [ServicesProviderController::class, 'index'])->name('crm.providers');
        Route::get('/providers/{provider}/plans', [ServicesProviderController::class, 'plans'])->name('crm.providers.plans');

        // CRM Settings - Lead Sources

        Route::get('/settings/lead-sources', [LeadSourceController::class, 'index'])->name('crm.lead-sources.index');
        Route::get('/crm-settings', [LeadSourceController::class, 'index'])->name('crm.crm-settings');
        Route::get('/crm-settings.php', [LeadSourceController::class, 'index']);
        Route::get('/employee/crm-settings.php', [LeadSourceController::class, 'index']);
        Route::get('/settings/lead-sources/create', [LeadSourceController::class, 'create'])->name('crm.lead-sources.create');
        Route::post('/lead-sources/store', [LeadSourceController::class, 'store'])->name('crm.lead-sources.store');
        Route::get('/lead-sources/{id}/edit', [LeadSourceController::class, 'edit'])->whereNumber('id')->name('crm.lead-sources.edit');
        Route::post('/lead-sources/{id}/update', [LeadSourceController::class, 'update'])->whereNumber('id')->name('crm.lead-sources.update');
        Route::post('/lead-sources/{id}/delete', [LeadSourceController::class, 'destroy'])->whereNumber('id')->name('crm.lead-sources.destroy');

        //Lead-status
        Route::get('/settings/lead-status', [LeadStatusController::class, 'index'])->name('crm.lead-status.index');
        Route::get('/settings/lead-status/create', [LeadStatusController::class, 'create'])->name('crm.lead-status.create');
        Route::post('/lead-status/store', [LeadStatusController::class, 'store'])->name('crm.lead-status.store');
        Route::get('/lead-status/{id}/edit', [LeadStatusController::class, 'edit'])->whereNumber('id')->name('crm.lead-status.edit');
        Route::post('/lead-status/{id}/update', [LeadStatusController::class, 'update'])->whereNumber('id')->name('crm.lead-status.update');
        Route::post('/lead-status/{id}/delete', [LeadStatusController::class, 'destroy'])->whereNumber('id')->name('crm.lead-status.destroy');

        // CRM Settings - Lead Bifurcation
        Route::get('/settings/lead-bifurcation', [LeadBifurcationController::class, 'index'])->name('crm.lead-bifurcation.index');
        Route::get('/settings/lead-bifurcation/create', [LeadBifurcationController::class, 'create'])->name('crm.lead-bifurcation.create');
        Route::post('/lead-bifurcation/store', [LeadBifurcationController::class, 'store'])->name('crm.lead-bifurcation.store');
        Route::get('/lead-bifurcation/{id}/edit', [LeadBifurcationController::class, 'edit'])->whereNumber('id')->name('crm.lead-bifurcation.edit');
        Route::post('/lead-bifurcation/{id}/update', [LeadBifurcationController::class, 'update'])->whereNumber('id')->name('crm.lead-bifurcation.update');
        Route::post('/lead-bifurcation/{id}/delete', [LeadBifurcationController::class, 'destroy'])->whereNumber('id')->name('crm.lead-bifurcation.destroy');

        // CRM Settings - Lead Priority
        Route::get('/settings/lead-priority', [LeadPriorityController::class, 'index'])->name('crm.lead-priority.index');
        Route::get('/settings/lead-priority/create', [LeadPriorityController::class, 'create'])->name('crm.lead-priority.create');
        Route::post('/lead-priority/store', [LeadPriorityController::class, 'store'])->name('crm.lead-priority.store');
        Route::get('/lead-priority/{id}/edit', [LeadPriorityController::class, 'edit'])->whereNumber('id')->name('crm.lead-priority.edit');
        Route::post('/lead-priority/{id}/update', [LeadPriorityController::class, 'update'])->whereNumber('id')->name('crm.lead-priority.update');
        Route::post('/lead-priority/{id}/delete', [LeadPriorityController::class, 'destroy'])->whereNumber('id')->name('crm.lead-priority.destroy');

        // CRM Settings - Designation
        Route::get('/settings/designation', [DesignationController::class, 'index'])->name('crm.designation.index');
        Route::get('/settings/designation/create', [DesignationController::class, 'create'])->name('crm.designation.create');
        Route::post('/designation/store', [DesignationController::class, 'store'])->name('crm.designation.store');
        Route::get('/designation/{id}/edit', [DesignationController::class, 'edit'])->whereNumber('id')->name('crm.designation.edit');
        Route::post('/designation/{id}/update', [DesignationController::class, 'update'])->whereNumber('id')->name('crm.designation.update');
        Route::post('/designation/{id}/delete', [DesignationController::class, 'destroy'])->whereNumber('id')->name('crm.designation.destroy');
    });

Route::get('/crm/quotation/{quotation}/respond/{action}', [QuotationController::class, 'respond'])
    ->middleware('signed')
    ->where('action', 'approve|reject')
    ->name('crm.quotations.respond');
Route::get('/crm/quotation/{quotation}/respond/{action}/confirm', [QuotationController::class, 'respondApply'])
    ->middleware('signed')
    ->where('action', 'approve|reject')
    ->name('crm.quotations.respond.apply');

Route::get('/crm/quotation/{quotation}/invoice-respond/{action}', [QuotationController::class, 'respondInvoice'])
    ->middleware('signed')
    ->where('action', 'approve|reject')
    ->name('crm.quotations.invoice.respond');
Route::get('/crm/quotation/{quotation}/invoice-respond/{action}/confirm', [QuotationController::class, 'respondInvoiceApply'])
    ->middleware('signed')
    ->where('action', 'approve|reject')
    ->name('crm.quotations.invoice.respond.apply');
