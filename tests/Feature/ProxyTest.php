<?php

use Illuminate\Support\Facades\Route;

it('trusts the platform proxy for the scheme', function () {
    // Render terminates TLS and forwards plain HTTP; links must still be https.
    Route::get('_scheme', fn () => request()->getScheme());

    $this->get('_scheme', ['X-Forwarded-Proto' => 'https'])->assertSee('https');
});
