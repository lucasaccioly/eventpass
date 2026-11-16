<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Demo accounts are shared-looking throwaways: no taking them over by changing email or password. */
class BlockDemoAccountChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(
            $user instanceof User && $user->is_demo
                && ($request->routeIs('user-password.update') || $request->input('email', $user->email) !== $user->email),
            403,
            'Conta demo: e-mail e senha não podem ser alterados.',
        );

        return $next($request);
    }
}
