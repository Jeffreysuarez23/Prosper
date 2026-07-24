<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUltraPlan
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $user->plan !== 'ultra') {
            return response()->json(['error' => 'Acceso denegado. Esta acción requiere el plan Ultra.'], 403);
        }
        return $next($request);
    }
}
