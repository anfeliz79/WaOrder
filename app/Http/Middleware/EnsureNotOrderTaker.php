<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotOrderTaker
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isOrderTaker()) {
            return redirect('/console');
        }

        return $next($request);
    }
}
