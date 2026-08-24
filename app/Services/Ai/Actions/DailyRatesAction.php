<?php

namespace App\Services\Ai\Actions;

use App\Models\DailyRate;
use App\Services\Ai\Contracts\AiActionInterface;
use Carbon\Carbon;

class DailyRatesAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $rate = DailyRate::whereDate('date', Carbon::today())
            ->where('gold_sell', '>', 0)
            ->first();

        if (! $rate) {
            return [
                'found' => false,
                'date' => date('Y-m-d'),
                'message' => 'Aaj ka live bhav add nahi hai.',
                'status' => 'RATE_NOT_SET_TODAY',
            ];
        }

        $gold24k = floatval($rate->gold_sell);
        $gold22k = round($gold24k * 0.916, 2);
        $gold18k = round($gold24k * 0.750, 2);
        $silver = floatval($rate->silver_sell);

        return [
            'found' => true,
            'date' => $rate->date ?? date('Y-m-d'),
            'gold_24k_per_gm' => $gold24k,
            'gold_22k_per_gm' => $gold22k,
            'gold_18k_per_gm' => $gold18k,
            'silver_per_gm' => $silver,
            'status' => 'REAL_ERP_DATABASE',
        ];
    }
}
