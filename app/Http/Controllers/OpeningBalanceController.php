<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Services\OpeningBalanceService;
use App\Services\SingleOutletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    public function __construct(
        private readonly SingleOutletService $singleOutletService,
        private readonly OpeningBalanceService $openingBalanceService,
    ) {
    }

    public function create(): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        return view('accounting.opening-balance', [
            'outlet' => $outlet,
            'cutoverDate' => app(\App\Services\AccountingLedgerService::class)->getCutoverDate($outlet->id),
            'openingEntry' => $this->openingBalanceService->openingEntry($outlet->id),
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request): RedirectResponse
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();
        $this->openingBalanceService->post(
            user: $request->user(),
            outlet: $outlet,
            cutoverDate: $request->date('cutover_date')->toDateString(),
            balances: $request->only([
                'cash_balance', 'bank_balance', 'inventory_balance',
                'fixed_asset_balance', 'liabilities_balance', 'capital_balance',
            ]),
        );

        return redirect()->route('accounting.opening-balance.create')->with('success', 'Saldo awal berhasil diposting.');
    }
}
