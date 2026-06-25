<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarningAcknowledgementController extends Controller
{
    // #80: Show warning page
    public function show()
    {
        return view('auth.warning-acknowledgement');
    }

    // #80: Handle acknowledgement
    public function acknowledge(Request $request)
    {
        $user = Auth::user();
        $user->update(['is_acknowledged' => true]);

        return redirect()->route('dashboard');
    }
}
