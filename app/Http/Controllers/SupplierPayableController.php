<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayablePaymentRequest;
use App\Http\Requests\StoreSupplierPayableRequest;
use App\Models\PayablePayment;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Services\SingleOutletService;
use App\Services\SupplierPayableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierPayableController extends Controller
{
    public function __construct(
        private readonly SingleOutletService $singleOutletService,
        private readonly SupplierPayableService $payableService,
    ) {
    }

    public function index(Request $request): View
    {
        $outletId = $this->singleOutletService->getDefaultOutletId();
        $status = $request->string('status')->toString();
        $supplierId = $request->integer('supplier_id') ?: null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $query = SupplierPayable::query()
            ->with('supplier')
            ->where('outlet_id', $outletId)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($dateFrom, fn ($q) => $q->whereDate('debt_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('debt_date', '<=', $dateTo))
            ->latest('debt_date')->latest('id');
        $summary = clone $query;

        return view('payables.index', [
            'payables' => $query->paginate(15)->withQueryString(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'filters' => compact('status', 'supplierId', 'dateFrom', 'dateTo'),
            'statuses' => SupplierPayable::STATUSES,
            'summary' => [
                'original' => (float) $summary->sum('original_amount'),
                'paid' => (float) $summary->sum('paid_amount'),
                'outstanding' => (float) $summary->sum('outstanding_amount'),
                'due' => (clone $summary)->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->where('status', '!=', 'paid')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('payables.create', ['suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(StoreSupplierPayableRequest $request): RedirectResponse
    {
        $this->payableService->createPayable($request->user(), $this->singleOutletService->getDefaultOutlet(), $request->validated());

        return redirect()->route('payables.index')->with('success', 'Utang supplier berhasil dicatat.');
    }

    public function show(SupplierPayable $payable): View
    {
        $this->ensureDefaultOutlet($payable);
        return view('payables.show', ['payable' => $payable->load(['supplier', 'payments.creator'])]);
    }

    public function pay(SupplierPayable $payable): View
    {
        $this->ensureDefaultOutlet($payable);
        return view('payables.pay', ['payable' => $payable->load('supplier'), 'paymentMethods' => PayablePayment::PAYMENT_METHODS]);
    }

    public function storePayment(StorePayablePaymentRequest $request, SupplierPayable $payable): RedirectResponse
    {
        $this->ensureDefaultOutlet($payable);
        $result = $this->payableService->pay($request->user(), $payable, $request->validated());
        $message = 'Pembayaran utang berhasil disimpan.';
        if (! $result['journaled']) {
            $request->session()->flash('warning', 'Pembayaran tersimpan, tetapi belum dibuat jurnal karena pembukuan belum aktif.');
        } else {
            $message .= ' Jurnal pembayaran berhasil dibuat.';
        }

        return redirect()->route('payables.show', $payable)->with('success', $message);
    }

    private function ensureDefaultOutlet(SupplierPayable $payable): void
    {
        abort_unless((int) $payable->outlet_id === $this->singleOutletService->getDefaultOutletId(), 404);
    }
}
