<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('suppliers.index', ['suppliers' => Supplier::query()->withCount('payables')->latest('id')->paginate(15)]);
    }

    public function create(): View { return view('suppliers.create', ['supplier' => new Supplier(['is_active' => true])]); }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create([
            'name' => $request->string('name')->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
            'address' => $request->filled('address') ? $request->string('address')->toString() : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier): View { return view('suppliers.edit', compact('supplier')); }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update([
            'name' => $request->string('name')->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
            'address' => $request->filled('address') ? $request->string('address')->toString() : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $hasHistory = $supplier->payables()->exists();
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', $hasHistory
            ? 'Supplier dinonaktifkan karena memiliki riwayat utang.'
            : 'Supplier berhasil dinonaktifkan.');
    }
}
