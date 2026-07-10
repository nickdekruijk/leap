<?php

namespace NickDeKruijk\Leap\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use NickDeKruijk\Leap\Traits\CanLog;

class LogoutController extends Controller
{
    use CanLog;

    public function __invoke()
    {
        $this->log('logout');
        Auth::guard(config('leap.guard'))->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('leap.home');
    }
}
