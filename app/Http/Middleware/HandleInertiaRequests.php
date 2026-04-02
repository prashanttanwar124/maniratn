<?php

namespace App\Http\Middleware;

use App\Models\DailyRegister;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();
        $lastClosedRegister = DailyRegister::query()
            ->whereNotNull('closed_at')
            ->latest('date')
            ->latest('id')
            ->first();
        $todayRegister = DailyRegister::query()
            ->whereDate('date', Carbon::today())
            ->latest('id')
            ->first();
        $hasAnyRegister = DailyRegister::query()->exists();
        $cashVault = Vault::query()->where('type', 'CASH')->value('balance') ?? 0;
        $goldVault = Vault::query()->where('type', 'GOLD')->value('balance') ?? 0;
        $silverVault = Vault::query()->where('type', 'SILVER')->value('balance') ?? 0;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'dayStatus' => [
                'is_open' => (bool) ($todayRegister && $todayRegister->closed_at === null),
                'opened_at' => optional($todayRegister?->created_at)?->toDateTimeString(),
                'closed_at' => optional($todayRegister?->closed_at)?->toDateTimeString(),
                'has_register' => (bool) $todayRegister,
                'is_initial_setup' => ! $hasAnyRegister,
                'expected_opening_cash' => (float) ($lastClosedRegister?->closing_cash ?? 0),
                'expected_opening_gold' => (float) ($lastClosedRegister?->closing_gold ?? 0),
                'expected_opening_silver' => (float) ($lastClosedRegister?->closing_silver ?? 0),
                'expected_opening_date' => optional($lastClosedRegister?->date)?->toDateString(),
                'current_vault_cash' => (float) $cashVault,
                'current_vault_gold' => (float) $goldVault,
                'current_vault_silver' => (float) $silverVault,
            ],
            'auth' => [
                'user' => $user,
                'role' => $user ? $user->getRoleNames()->first() : null,

                // DYNAMIC PERMISSION LOADING
                'can' => $user ? (
                    $user->hasRole('admin')
                    ? \Spatie\Permission\Models\Permission::all()->mapWithKeys(fn($p) => [$p->name => true])
                    : $user->getAllPermissions()->mapWithKeys(fn($p) => [$p->name => true])
                ) : [],
            ],
        ];
    }
}
