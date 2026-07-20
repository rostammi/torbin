<?php

use App\Http\Controllers\Admin\PriceSourceController;
use App\Http\Controllers\Admin\TourController as AdminTourController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tours/{tour}', [HomeController::class, 'show'])->name('tours.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::redirect('/', '/admin/tours')->name('dashboard');
    Route::resource('tours', AdminTourController::class)->except('show');
    Route::post('tours/{tour}/crawl', [AdminTourController::class, 'crawl'])->name('tours.crawl');
    Route::post('tours/{tour}/official-sources', [PriceSourceController::class, 'official'])->name('sources.official');
    Route::post('tours/{tour}/sources', [PriceSourceController::class, 'store'])->name('sources.store');
    Route::put('sources/{source}', [PriceSourceController::class, 'update'])->name('sources.update');
    Route::delete('sources/{source}', [PriceSourceController::class, 'destroy'])->name('sources.destroy');
    Route::post('sources/{source}/crawl', [PriceSourceController::class, 'crawl'])->name('sources.crawl');
});
