<?php

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ConsultationCertificateController;
use App\Http\Controllers\PatientHistoryController;
use App\Http\Controllers\StockTakeSheetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function () {
    Route::get('patients/{patient}/history', PatientHistoryController::class)->name('patients.history');
    Route::get('stock-takes/{stockTake}/sheet', StockTakeSheetController::class)->name('stock-takes.sheet');
    Route::get('calendar/feed', CalendarFeedController::class)->name('calendar.feed');
    Route::get('consultations/{consultation}/certificate', ConsultationCertificateController::class)->name('consultations.certificate');
});
