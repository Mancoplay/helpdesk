<?php

namespace App\Livewire\Actions;

use App\Support\SessionAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(SessionAccessService $sessionAccessService)
    {
        $sessionAccessService->clearSessionById((string) Session::getId());
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
