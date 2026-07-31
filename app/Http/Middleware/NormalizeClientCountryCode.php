<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeClientCountryCode
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('country_code')) {
            $country = strtoupper(trim((string) $request->input('country_code')));

            // The existing clients table/controller still accepts ISO alpha-2.
            // MyInvois UBL generation normalizes Malaysia back to ISO alpha-3 MYS.
            if ($country === 'MYS') {
                $request->merge(['country_code' => 'MY']);
            }
        }

        return $next($request);
    }
}
