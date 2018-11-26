<?php

namespace App\Http\Middleware;

use Closure;

class VerifyClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!$request->session()->exists('client_id')) {
            // user value cannot be found in session
//            return redirect('/access_denied');
          //  dd('Access Denied');
        }

        return $next($request);
    }
}
