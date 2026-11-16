<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/events')->name('dashboard');
    Route::resource('events', EventController::class);
});

require __DIR__.'/settings.php';
