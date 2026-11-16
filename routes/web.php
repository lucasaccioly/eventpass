<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketPdfController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/events')->name('dashboard');
    Route::resource('events', EventController::class);
});

// Participant side: no account, the event slug and the ticket ULID are the keys.
Route::get('e/{event:slug}', [PublicEventController::class, 'show'])->name('public.events.show');
Route::post('e/{event:slug}/registrations', [RegistrationController::class, 'store'])
    ->middleware('throttle:registrations')
    ->name('registrations.store');
Route::get('t/{registration}', [TicketController::class, 'show'])->name('tickets.show');
Route::get('t/{registration}/pdf', TicketPdfController::class)->name('tickets.pdf');

require __DIR__.'/settings.php';
