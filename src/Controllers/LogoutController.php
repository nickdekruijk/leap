<?php

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct()
    {
        Auth::shouldUse(config('leap.guard'));
    }

    public function __invoke()
    {
        Auth::logout();
        Auth2FAController::validateSession(false);
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('leap.login');
    }
}
