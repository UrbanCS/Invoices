<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessSettingController;
use App\Http\Controllers\ClientCategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DailyRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MonthlyInvoiceController;
use App\Http\Controllers\PortalInvoiceController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/language/{locale}', LocaleController::class)->name('language.switch');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:super_admin,employee'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('daily-records', DailyRecordController::class)->parameters(['daily-records' => 'dailyRecord'])->except('destroy');
    Route::post('/daily-records/{dailyRecord}/review', [DailyRecordController::class, 'review'])->name('daily-records.review');
    Route::post('/daily-records/{dailyRecord}/attachments', [DailyRecordController::class, 'attachments'])->name('daily-records.attachments');

    Route::resource('monthly-invoices', MonthlyInvoiceController::class)->parameters(['monthly-invoices' => 'invoice'])->except('destroy');
    Route::post('/monthly-invoices/{invoice}/approve', [MonthlyInvoiceController::class, 'approve'])->name('monthly-invoices.approve');
    Route::post('/monthly-invoices/{invoice}/generate-pdf', [MonthlyInvoiceController::class, 'generatePdf'])->name('monthly-invoices.generate-pdf');
    Route::get('/monthly-invoices/{invoice}/download', [MonthlyInvoiceController::class, 'download'])->name('monthly-invoices.download');
    Route::get('/monthly-invoices/{invoice}/export', [MonthlyInvoiceController::class, 'export'])->name('monthly-invoices.export');
    Route::post('/monthly-invoices/{invoice}/attachments', [MonthlyInvoiceController::class, 'attachments'])->name('monthly-invoices.attachments');
    Route::post('/monthly-invoices/{invoice}/mark-sent', [MonthlyInvoiceController::class, 'markSent'])->name('monthly-invoices.mark-sent');
    Route::post('/monthly-invoices/{invoice}/mark-paid', [MonthlyInvoiceController::class, 'markPaid'])->name('monthly-invoices.mark-paid');
    Route::post('/monthly-invoices/{invoice}/cancel', [MonthlyInvoiceController::class, 'cancel'])->name('monthly-invoices.cancel');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/invoices', [ReportController::class, 'invoices'])->name('reports.invoices');
    Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
    Route::get('/reports/category-totals', [ReportController::class, 'categoryTotals'])->name('reports.category-totals');
    Route::get('/reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
});

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::resource('clients', ClientController::class);
    Route::get('/clients/{client}/categories', [ClientCategoryController::class, 'index'])->name('clients.categories.index');
    Route::post('/clients/{client}/categories', [ClientCategoryController::class, 'store'])->name('clients.categories.store');
    Route::get('/settings/business', [BusinessSettingController::class, 'edit'])->name('settings.business.edit');
    Route::put('/settings/business', [BusinessSettingController::class, 'update'])->name('settings.business.update');
});

Route::middleware(['auth', 'role:client'])->group(function () {
    Route::get('/portal/invoices', [PortalInvoiceController::class, 'index'])->name('portal.invoices.index');
    Route::get('/portal/invoices/{invoice}', [PortalInvoiceController::class, 'show'])->name('portal.invoices.show');
    Route::get('/portal/invoices/{invoice}/download', [PortalInvoiceController::class, 'download'])->name('portal.invoices.download');
});
