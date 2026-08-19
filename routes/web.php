<?php

use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\ComparisonSourceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PriceSourceController;
use App\Http\Controllers\Admin\StaticPageController as AdminStaticPageController;
use App\Http\Controllers\Admin\SyncController;
use App\Http\Controllers\Admin\TourController as AdminTourController;
use App\Http\Controllers\Admin\TourSuggestionController;
use App\Http\Controllers\AdvertisementClickController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OutboundClickController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tours', [HomeController::class, 'category'])->defaults('category_key', 'tour')->name('tours.index');
Route::get('/hotels', [HomeController::class, 'category'])->defaults('category_key', 'hotel')->name('hotels.index');
Route::get('/stays', [HomeController::class, 'category'])->defaults('category_key', 'stay')->name('stays.index');
Route::get('/visas', [HomeController::class, 'category'])->defaults('category_key', 'visa')->name('visas.index');
Route::get('/about-us', [StaticPageController::class, 'show'])->defaults('slug', 'about-us')->name('pages.about');
Route::get('/contact-us', [StaticPageController::class, 'show'])->defaults('slug', 'contact-us')->name('pages.contact');
Route::get('/faq', [StaticPageController::class, 'show'])->defaults('slug', 'faq')->name('pages.faq');
Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:30,1')->name('search.index');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->middleware('throttle:60,1')->name('search.suggestions');
Route::get('/providers/{provider}', [ProviderController::class, 'show'])->name('providers.show');
Route::get('/tours/{tour}', [HomeController::class, 'show'])->name('tours.show');
Route::get('/hotels/{tour}', [HomeController::class, 'show'])->defaults('category_key', 'hotel')->name('hotels.show');
Route::get('/stays/{tour}', [HomeController::class, 'show'])->defaults('category_key', 'stay')->name('stays.show');
Route::get('/visas/{tour}', [HomeController::class, 'show'])->defaults('category_key', 'visa')->name('visas.show');
Route::get('/go/{source}', OutboundClickController::class)->middleware('throttle:30,1')->name('outbound.click');
Route::get('/ads/{advertisement}/click', AdvertisementClickController::class)->middleware('throttle:60,1')->name('advertisements.click');
Route::post('/tours/{tour}/price-alerts', [PriceAlertController::class, 'store'])->middleware('throttle:5,1')->name('price-alerts.store');
Route::get('/price-alerts/unsubscribe/{token}', [PriceAlertController::class, 'unsubscribe'])->middleware('throttle:10,1')->name('price-alerts.unsubscribe');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('admin.only')->group(function () {
        Route::resource('tours', AdminTourController::class)->except('show');
        Route::resource('advertisements', AdvertisementController::class)->except('show');
        Route::resource('static-pages', AdminStaticPageController::class)->only(['index', 'edit', 'update']);
        Route::post('comparison-sources/{comparison_source}/scan', [ComparisonSourceController::class, 'scan'])->name('comparison-sources.scan');
        Route::resource('comparison-sources', ComparisonSourceController::class)->except('show');
        Route::get('suggestions', [TourSuggestionController::class, 'index'])->name('suggestions.index');
        Route::post('suggestions/discover', [TourSuggestionController::class, 'discover'])->name('suggestions.discover');
        Route::post('suggestions/build-all', [TourSuggestionController::class, 'storeAll'])->name('suggestions.store-all');
        Route::post('suggestions/{suggestion}/create-tour', [TourSuggestionController::class, 'store'])->name('suggestions.store');
        Route::get('sync', [SyncController::class, 'index'])->name('sync.index');
        Route::post('sync', [SyncController::class, 'run'])->name('sync.run');
        Route::get('agencies', [AgencyController::class, 'index'])->name('agencies.index');
        Route::put('agencies/comparison-contact', [AgencyController::class, 'updateComparisonContact'])->name('agencies.comparison-contact');
        Route::put('agencies/featured', [PriceSourceController::class, 'updateAgencyFeatured'])->name('agencies.featured');
        Route::put('agencies/{agency}', [AgencyController::class, 'update'])->name('agencies.update');
        Route::post('agencies/{agency}/balance', [AgencyController::class, 'adjustBalance'])->name('agencies.balance');
        Route::post('agencies/{agency}/access', [AgencyController::class, 'saveAccess'])->name('agencies.access');
        Route::post('tours/{tour}/crawl', [AdminTourController::class, 'crawl'])->name('tours.crawl');
        Route::post('tours/{tour}/crawl-content', [AdminTourController::class, 'crawlContent'])->name('tours.crawl-content');
        Route::post('tours/{tour}/add-images', [AdminTourController::class, 'addImages'])->name('tours.add-images');
        Route::post('tours/{tour}/upload-images', [AdminTourController::class, 'uploadImages'])->name('tours.upload-images');
        Route::put('tours/{tour}/reorder-images', [AdminTourController::class, 'reorderImages'])->name('tours.reorder-images');
        Route::post('tours/{tour}/refresh-images', [AdminTourController::class, 'refreshImages'])->name('tours.refresh-images');
        Route::post('tours/{tour}/official-sources', [PriceSourceController::class, 'official'])->name('sources.official');
        Route::post('tours/{tour}/sources', [PriceSourceController::class, 'store'])->name('sources.store');
        Route::put('sources/{source}', [PriceSourceController::class, 'update'])->name('sources.update');
        Route::delete('sources/{source}', [PriceSourceController::class, 'destroy'])->name('sources.destroy');
        Route::post('sources/{source}/crawl', [PriceSourceController::class, 'crawl'])->name('sources.crawl');
    });
});
