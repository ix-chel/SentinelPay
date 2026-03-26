<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/dashboard/merchants/{id}', [DashboardController::class, 'show'])->name('dashboard.show');
