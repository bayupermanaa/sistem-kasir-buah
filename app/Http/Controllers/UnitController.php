<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $units = Unit::latest()->paginate(10);

        return view('units.index', compact('units'));
    }

    public function create(): View
    {
        return view('units.create');
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        Unit::create([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('units.index')->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function edit(Unit $unit): View
    {
        return view('units.edit', compact('unit'));
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('units.index')->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return redirect()->route('units.index')->with('success', 'Satuan berhasil dihapus.');
    }
}
