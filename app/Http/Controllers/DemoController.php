<?php

namespace App\Http\Controllers;

use App\Actions\CreateDemoSandbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** "Entrar como demo": a private sandbox per visitor, expiring in 24 h. */
class DemoController extends Controller
{
    public function __invoke(Request $request, CreateDemoSandbox $create): RedirectResponse
    {
        $user = $create->handle();

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('events.show', $user->events()->sole());
    }
}
