<?php

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\PriceSourceController;
use App\Http\Controllers\Admin\TourController as AdminTourController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OutboundClickController;
use App\Http\Controllers\PriceAlertController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tours/{tour}', [HomeController::class, 'show'])->name('tours.show');
Route::get('/go/{source}', OutboundClickController::class)->middleware('throttle:30,1')->name('outbound.click');
Route::post('/tours/{tour}/price-alerts', [PriceAlertController::class, 'store'])->middleware('throttle:5,1')->name('price-alerts.store');
Route::get('/price-alerts/unsubscribe/{token}', [PriceAlertController::class, 'unsubscribe'])->middleware('throttle:10,1')->name('price-alerts.unsubscribe');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::redirect('/', '/admin/tours')->name('dashboard');
    Route::resource('tours', AdminTourController::class)->except('show');
    Route::get('agencies', [AgencyController::class, 'index'])->name('agencies.index');
    Route::put('agencies/featured', [PriceSourceController::class, 'updateAgencyFeatured'])->name('agencies.featured');
    Route::put('agencies/{agency}', [AgencyController::class, 'update'])->name('agencies.update');
    Route::post('agencies/{agency}/balance', [AgencyController::class, 'adjustBalance'])->name('agencies.balance');
    Route::post('tours/{tour}/crawl', [AdminTourController::class, 'crawl'])->name('tours.crawl');
    Route::post('tours/{tour}/crawl-content', [AdminTourController::class, 'crawlContent'])->name('tours.crawl-content');
    Route::post('tours/{tour}/official-sources', [PriceSourceController::class, 'official'])->name('sources.official');
    Route::post('tours/{tour}/sources', [PriceSourceController::class, 'store'])->name('sources.store');
    Route::put('sources/{source}', [PriceSourceController::class, 'update'])->name('sources.update');
    Route::delete('sources/{source}', [PriceSourceController::class, 'destroy'])->name('sources.destroy');
    Route::post('sources/{source}/crawl', [PriceSourceController::class, 'crawl'])->name('sources.crawl');
});
