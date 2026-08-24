<?php

namespace App\Services\Ai\Actions;

use App\Models\Product;
use App\Models\SilverProduct;
use App\Services\Ai\Contracts\AiActionInterface;

class StockCheckAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $q = trim((string) ($args['query'] ?? ''));
        $catFilter = trim((string) ($args['category'] ?? ''));
        $metalFilter = strtoupper(trim((string) ($args['metal'] ?? '')));
        $purityFilter = trim((string) ($args['purity'] ?? ''));
        $targetWeight = isset($args['weight']) && is_numeric($args['weight']) ? (float) $args['weight'] : null;
        $minWeight = isset($args['min_weight']) && is_numeric($args['min_weight']) ? (float) $args['min_weight'] : null;
        $maxWeight = isset($args['max_weight']) && is_numeric($args['max_weight']) ? (float) $args['max_weight'] : null;

        // Smart extraction from query if category or weight wasn't explicitly structured
        if (empty($catFilter) && ! empty($q)) {
            $knownCategories = ['chain', 'ring', 'bangle', 'necklace', 'pendant', 'earrings', 'coin', 'payal', 'anklet', 'idol', 'gift'];
            foreach ($knownCategories as $kc) {
                if (stripos($q, $kc) !== false) {
                    $catFilter = ucfirst($kc);
                    break;
                }
            }
        }
        if ($targetWeight === null && ! empty($q)) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:g|gm|gram)/i', $q, $m)) {
                $targetWeight = (float) $m[1];
            }
        }

        // Determine whether to search Gold, Silver, or Both
        $searchSilver = false;
        $searchGold = true;
        if ($metalFilter === 'SILVER' || stripos($catFilter, 'silver') !== false || stripos($q, 'silver') !== false || stripos($catFilter, 'payal') !== false) {
            $searchSilver = true;
            $searchGold = false;
        } elseif ($metalFilter === 'GOLD') {
            $searchGold = true;
            $searchSilver = false;
        } elseif (empty($catFilter) && empty($q) && empty($targetWeight)) {
            $searchGold = true;
            $searchSilver = true;
        }

        $items = [];
        $exactWeightFound = null;
        $matchedGoldCount = 0;
        $matchedGoldWeight = 0;
        $matchedSilverCount = 0;
        $matchedSilverWeight = 0;

        // 1. Search Gold Inventory
        if ($searchGold) {
            $goldQuery = Product::where('is_sold', false);

            if (! empty($catFilter)) {
                $goldQuery->where(function ($query) use ($catFilter) {
                    $query->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$catFilter}%"))
                        ->orWhere('name', 'like', "%{$catFilter}%");
                });
            }
            if (! empty($purityFilter)) {
                $goldQuery->whereHas('purity', fn ($p) => $p->where('name', 'like', "%{$purityFilter}%"));
            }
            if ($minWeight !== null) {
                $goldQuery->where('net_weight', '>=', $minWeight);
            }
            if ($maxWeight !== null) {
                $goldQuery->where('net_weight', '<=', $maxWeight);
            }

            if ($targetWeight !== null && $targetWeight > 0) {
                $closeCount = (clone $goldQuery)->whereBetween('net_weight', [$targetWeight * 0.75, $targetWeight * 1.25])->count();
                if ($closeCount > 0) {
                    $goldQuery->whereBetween('net_weight', [$targetWeight * 0.75, $targetWeight * 1.25]);
                    $exactWeightFound = true;
                } else {
                    $exactWeightFound = false;
                }
                $goldQuery->orderByRaw("ABS(net_weight - ?)", [$targetWeight]);
            } elseif (! empty($q)) {
                $goldQuery->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
                $goldQuery->latest();
            } else {
                $goldQuery->latest();
            }

            $matchedGoldCount = (clone $goldQuery)->count();
            $matchedGoldWeight = (clone $goldQuery)->sum('net_weight');
            $goldItems = $goldQuery->with(['category', 'purity'])->take(8)->get();

            foreach ($goldItems as $g) {
                $items[] = [
                    'barcode' => $g->barcode,
                    'name' => $g->name,
                    'metal' => 'GOLD',
                    'purity' => $g->purity?->name ?? '22K',
                    'weight' => $g->net_weight . ' g',
                    'category' => $g->category?->name ?? 'General',
                    'making' => '₹' . $g->making_charge . '/g',
                ];
            }
        }

        // 2. Search Silver Inventory
        if ($searchSilver) {
            $silverQuery = SilverProduct::where('is_sold', false);

            if (! empty($catFilter)) {
                $silverQuery->where(function ($query) use ($catFilter) {
                    $query->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$catFilter}%"))
                        ->orWhere('name', 'like', "%{$catFilter}%");
                });
            }
            if ($minWeight !== null) {
                $silverQuery->where('net_weight', '>=', $minWeight);
            }
            if ($maxWeight !== null) {
                $silverQuery->where('net_weight', '<=', $maxWeight);
            }

            if ($targetWeight !== null && $targetWeight > 0) {
                $closeCount = (clone $silverQuery)->whereBetween('net_weight', [$targetWeight * 0.75, $targetWeight * 1.25])->count();
                if ($closeCount > 0) {
                    $silverQuery->whereBetween('net_weight', [$targetWeight * 0.75, $targetWeight * 1.25]);
                    $exactWeightFound = true;
                } else {
                    $exactWeightFound = false;
                }
                $silverQuery->orderByRaw("ABS(net_weight - ?)", [$targetWeight]);
            } elseif (! empty($q)) {
                $silverQuery->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
                $silverQuery->latest();
            } else {
                $silverQuery->latest();
            }

            $matchedSilverCount = (clone $silverQuery)->count();
            $matchedSilverWeight = (clone $silverQuery)->sum('net_weight');
            $silverItems = $silverQuery->with('category')->take(8)->get();

            foreach ($silverItems as $s) {
                $items[] = [
                    'barcode' => $s->barcode,
                    'name' => $s->name,
                    'metal' => 'SILVER',
                    'purity' => 'Silver',
                    'weight' => $s->net_weight . ' g',
                    'category' => $s->category?->name ?? 'Silver',
                    'making' => '₹' . $s->making_charge,
                ];
            }
        }

        $totalMatchedCount = $matchedGoldCount + $matchedSilverCount;
        $totalMatchedWeight = round($matchedGoldWeight + $matchedSilverWeight, 3);

        return [
            'query' => ! empty($catFilter) ? "{$catFilter} Stock" : (! empty($q) ? $q : 'All Showroom Stock'),
            'category' => $catFilter ?: ($searchGold ? 'Gold Jewellery' : 'Silver Jewellery'),
            'target_weight' => $targetWeight,
            'exact_weight_found' => $exactWeightFound,
            'total_items' => $totalMatchedCount,
            'total_weight' => $totalMatchedWeight . ' g',
            'gold_count' => $matchedGoldCount,
            'gold_weight' => round($matchedGoldWeight, 3) . ' g',
            'silver_count' => $matchedSilverCount,
            'silver_weight' => round($matchedSilverWeight, 3) . ' g',
            'items' => $items,
            'status' => 'REAL_ERP_INVENTORY',
        ];
    }
}
