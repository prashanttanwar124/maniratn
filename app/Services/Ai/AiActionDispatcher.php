<?php

namespace App\Services\Ai;

use App\Services\Ai\Actions\AddProductAction;
use App\Services\Ai\Actions\CreateBillAction;
use App\Services\Ai\Actions\CustomerKhataAction;
use App\Services\Ai\Actions\DailyRatesAction;
use App\Services\Ai\Actions\EstimateQuotationAction;
use App\Services\Ai\Actions\OldGoldEstimateAction;
use App\Services\Ai\Actions\SalesSummaryAction;
use App\Services\Ai\Actions\SearchInvoicesAction;
use App\Services\Ai\Actions\StockCheckAction;
use App\Services\Ai\Actions\TaskAction;
use App\Services\Ai\Actions\UpdateDailyRatesAction;
use App\Services\Ai\Actions\VaultBalanceAction;
use App\Services\Ai\Contracts\AiActionInterface;
use InvalidArgumentException;

class AiActionDispatcher
{
    /**
     * Map of tool names to their dedicated Action classes.
     *
     * @var array<string, class-string<AiActionInterface>>
     */
    protected array $actions = [
        'get_daily_rates' => DailyRatesAction::class,
        'update_daily_rates' => UpdateDailyRatesAction::class,
        'add_product' => AddProductAction::class,
        'get_vault_balance' => VaultBalanceAction::class,
        'calculate_estimate' => EstimateQuotationAction::class,
        'calculate_old_gold' => OldGoldEstimateAction::class,
        'old_gold_estimate' => OldGoldEstimateAction::class,
        'create_bill' => CreateBillAction::class,
        'create_invoice' => CreateBillAction::class,
        'check_stock' => StockCheckAction::class,
        'get_customer_khata' => CustomerKhataAction::class,
        'customer_khata' => CustomerKhataAction::class,
        'search_invoices' => SearchInvoicesAction::class,
        'get_customer_invoices' => SearchInvoicesAction::class,
        'get_sales_summary' => SalesSummaryAction::class,
        'daily_sales_report' => SalesSummaryAction::class,
        'get_tasks' => TaskAction::class,
        'create_task' => TaskAction::class,
    ];

    /**
     * Dispatch and execute tool call on ERP backend.
     */
    public function dispatch(string $tool, array $args = []): array
    {
        if (! isset($this->actions[$tool])) {
            throw new InvalidArgumentException("Unsupported AI Tool: [{$tool}]");
        }

        $actionClass = $this->actions[$tool];
        /** @var AiActionInterface $handler */
        $handler = app($actionClass);

        return $handler->handle($args);
    }
}
