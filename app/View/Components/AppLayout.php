<?php

namespace App\View\Components;

use App\Enums\UserRoleEnum;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Render the correct shell for the central SaaS portal, superadmin panel,
     * or a tenant invoicing workspace.
     */
    public function render(): View
    {
        $centralDomain = (string) config('saas.central_domain');
        $isCentralDomain = request()->getHost() === $centralDomain;
        $user = auth()->user();

        if ($isCentralDomain && $user?->role === UserRoleEnum::SuperAdmin) {
            return view('layouts.superadmin');
        }

        if ($isCentralDomain) {
            return view('layouts.client-portal');
        }

        return view('layouts.app');
    }
}
