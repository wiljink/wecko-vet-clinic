<?php

use App\Http\Controllers\PatientHistoryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('patients/{patient}/history', PatientHistoryController::class)
    ->middleware('auth')
    ->name('patients.history');
