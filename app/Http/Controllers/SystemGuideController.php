<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SystemGuideController extends Controller
{
    public function index(): View
    {
        return view('system-guide.index', [
            'user' => Auth::user(),
            'environment' => config('myinvois.environment', 'sandbox'),
            'myInvoisEnabled' => (bool) config('myinvois.enabled'),
            'productionEnabled' => (bool) config('myinvois.production_enabled'),
            'queueConnection' => config('queue.default'),
        ]);
    }
}
