<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class RunScheduleController extends Controller
{
    /**
     * Run all due scheduled commands.
     *
     * Invoked by an external cron service (e.g. Supabase Cron) because the
     * hosting platform's free tier provides no native scheduler. Protected
     * by a shared secret sent in the X-Cron-Secret header.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.cron.secret');

        abort_unless(
            is_string($secret)
                && $secret !== ''
                && hash_equals($secret, (string) $request->header('X-Cron-Secret')),
            403,
            'Invalid cron secret.'
        );

        $exitCode = Artisan::call('schedule:run');

        return response()->json([
            'status' => 'ok',
            'exit_code' => $exitCode,
            'ran_at' => now()->toIso8601String(),
        ]);
    }
}
