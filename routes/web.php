<?php

use App\Models\ExtractionRun;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // One landing page per role. The middleware alias registered in bootstrap/app.php takes
    // its roles after the colon — `role:ap,admin` would admit either.
    Route::get('ap', fn () => view('ap', [
        'purchaseOrderCount' => PurchaseOrder::count(),
    ]))->middleware('role:ap')->name('ap');

    Route::get('admin', fn () => view('admin', [
        'vendorCount' => Vendor::count(),
        'userCount' => User::count(),
    ]))->middleware('role:admin')->name('admin');

    Route::get('trainer', fn () => view('trainer', [
        'extractionRunCount' => ExtractionRun::count(),
    ]))->middleware('role:trainer')->name('trainer');

});

require __DIR__.'/settings.php';
