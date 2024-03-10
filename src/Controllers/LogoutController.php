<?php

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke()
    {
        Auth::guard(config('leap.guard'))->logout();
        Auth2FAController::validateSession(false);
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('leap.home');
    }
}
