<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Utilities\WarningAcknowledgementUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarningAcknowledgementController extends Controller
{
    public function __construct(
        protected WarningAcknowledgementUtility $utility
    ) {}

    // #80: Show warning page
    public function show()
    {
        return view('auth.warning-acknowledgement');
    }

    // #80: Handle acknowledgement
    public function acknowledge(Request $request)
    {
        $validated = $request->validate([
            'acknowledge' => 'required|accepted',
        ]);

        $user = Auth::user();

        // Find the first unacknowledged warning for this user
        $warning = $this->utility->getUnacknowledgedWarning($user);

        if ($warning) {
            $this->utility->acknowledge($warning, $user);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Warning acknowledged successfully.');
    }
}
