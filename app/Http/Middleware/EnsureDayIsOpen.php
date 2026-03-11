<?php

namespace App\Http\Middleware;

use App\Models\DailyRegister;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDayIsOpen
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isDayOpen = DailyRegister::query()
            ->whereDate('date', Carbon::today())
            ->whereNull('closed_at')
            ->exists();

        if ($isDayOpen) {
            return $next($request);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'Open the shop day first by entering opening cash and gold balances.');
    }
}
