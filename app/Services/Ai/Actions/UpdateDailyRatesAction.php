<?php

namespace App\Services\Ai\Actions;

use App\Services\Ai\Contracts\AiActionInterface;

class UpdateDailyRatesAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $today = date('Y-m-d');
        $goldSell = floatval($args['gold_24k_sell'] ?? 7450);
        $goldBuy = floatval($args['gold_24k_buy'] ?? round($goldSell * 0.98, 2));
        $silverSell = floatval($args['silver_sell'] ?? 88.50);

        // ALWAYS RETURN DRAFT PREVIEW FOR USER VERIFICATION FIRST
        return [
            'found' => true,
            'is_preview' => true,
            'action_type' => 'UPDATE_DAILY_RATES',
            'date' => $today,
            'gold_24k_sell' => $goldSell,
            'gold_24k_buy' => $goldBuy,
            'silver_sell' => $silverSell,
            'status' => 'CONFIRMATION_REQUIRED',
        ];
    }
}
