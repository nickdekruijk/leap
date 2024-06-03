<?php

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use NickDeKruijk\Leap\Traits\CanLog;

class LogoutController extends Controller
{
    use CanLog;

    public function __invoke()
    {
        $this->log('logout');
        Auth::guard(config('leap.guard'))->logout();
        Auth2FAController::validateSession(false);
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('leap.home');
    }
}
